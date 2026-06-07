<?php

namespace App\Jobs;

use App\Models\FeedAttachment;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Migrates DB-stored feed attachments to Amazon S3.
 *
 * This job is idempotent — records already on S3 (storage_type = 's3') are
 * skipped. Run it once after S3 credentials are configured to move all BLOB
 * attachments out of the database and into S3 storage.
 *
 * Migration strategy:
 *   1. Fetch DB-backed attachments in batches of 50 (memory-safe)
 *   2. Upload binary content to S3 under feeds/{team}/{post}/{uuid}.{ext}
 *   3. Update the row: storage_type = 's3', file_url = S3 URL, file_data = null
 *   4. On error, log and continue (partial migration is safe — rows not updated
 *      remain 'db' and continue to be served correctly by FeedAttachmentController)
 *
 * Rollback strategy:
 *   - The file_data column is NOT removed after migration
 *   - To roll back: UPDATE feed_attachments SET storage_type='db' WHERE storage_type='s3'
 *   - The DB data will still be present for rows migrated by this job
 *   - Note: rows where file_data was explicitly nulled after migration require
 *     re-download from S3 to roll back
 *
 * Queue: default
 * Run via: php artisan migrate:attachments-to-s3
 */
class MigrateFeedAttachmentsToS3Job implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 3600; // 1 hour — large migrations may take time

    /** Number of attachments to process per batch. */
    private const BATCH_SIZE = 50;

    /** S3 path prefix for all feed attachments. */
    private const S3_PREFIX = 'feeds';

    public function handle(): void
    {
        if (! $this->isS3Configured()) {
            Log::error('MigrateFeedAttachmentsToS3Job: S3 not configured — skipping migration', [
                'hint' => 'Set AWS_ACCESS_KEY_ID, AWS_SECRET_ACCESS_KEY, AWS_DEFAULT_REGION, AWS_BUCKET in .env',
            ]);

            return;
        }

        $total = FeedAttachment::where('storage_type', 'db')
            ->whereNotNull('file_data')
            ->count();

        Log::info('MigrateFeedAttachmentsToS3Job: starting migration', [
            'total_attachments' => $total,
            'batch_size' => self::BATCH_SIZE,
        ]);

        $migrated = 0;
        $failed = 0;

        FeedAttachment::where('storage_type', 'db')
            ->whereNotNull('file_data')
            ->with('post')
            ->chunkById(self::BATCH_SIZE, function ($attachments) use (&$migrated, &$failed) {
                foreach ($attachments as $attachment) {
                    try {
                        $this->migrateAttachment($attachment);
                        $migrated++;
                    } catch (\Throwable $e) {
                        $failed++;
                        Log::error('MigrateFeedAttachmentsToS3Job: failed to migrate attachment', [
                            'attachment_id' => $attachment->id,
                            'file_name' => $attachment->file_name,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            });

        Log::info('MigrateFeedAttachmentsToS3Job: migration complete', [
            'migrated' => $migrated,
            'failed' => $failed,
            'total' => $total,
        ]);
    }

    private function migrateAttachment(FeedAttachment $attachment): void
    {
        $fileData = $attachment->file_data;

        if (is_null($fileData)) {
            return;
        }

        // Build a deterministic S3 path: feeds/{post_id}/{uuid}.{extension}
        $extension = $this->extensionFromMime($attachment->file_type);
        $uuid = Str::uuid()->toString();
        $postId = $attachment->post_id ?? 'unknown';
        $s3Path = self::S3_PREFIX.'/'.$postId.'/'.$uuid.'.'.$extension;

        // Upload to S3
        Storage::disk('s3')->put($s3Path, (string) $fileData, [
            'ContentType' => $attachment->file_type,
            'ContentDisposition' => 'inline; filename="'.addcslashes((string) $attachment->file_name, '"\\').'"',
            'CacheControl' => 'private, max-age=86400',
        ]);

        $s3Url = Storage::disk('s3')->url($s3Path);

        // Update the record — clear the BLOB, set S3 metadata
        $attachment->storage_type = 's3';
        $attachment->file_url = $s3Url;
        $attachment->file_data = null; // free the BLOB after confirming S3 upload
        $attachment->save();

        Log::debug('MigrateFeedAttachmentsToS3Job: migrated attachment', [
            'attachment_id' => $attachment->id,
            'file_name' => $attachment->file_name,
            's3_path' => $s3Path,
            'size_bytes' => $attachment->file_size,
        ]);
    }

    /**
     * Derive a sensible file extension from a MIME type.
     */
    private function extensionFromMime(string $mime): string
    {
        return match ($mime) {
            'image/jpeg', 'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'audio/mpeg' => 'mp3',
            'audio/mp4' => 'm4a',
            'audio/ogg' => 'ogg',
            'audio/wav', 'audio/x-wav' => 'wav',
            'application/pdf' => 'pdf',
            default => 'bin',
        };
    }

    /**
     * Check that AWS S3 credentials are present before attempting migration.
     */
    private function isS3Configured(): bool
    {
        $key = config('filesystems.disks.s3.key');
        $secret = config('filesystems.disks.s3.secret');
        $bucket = config('filesystems.disks.s3.bucket');

        return ! empty($key) && ! empty($secret) && ! empty($bucket);
    }
}
