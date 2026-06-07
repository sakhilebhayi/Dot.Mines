<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\FeedAttachment;
use App\Models\FeedPost;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * FeedAttachmentService
 *
 * Handles validated, sanitised storage of feed attachment files.
 *
 * Storage backend is selected automatically:
 *   - If S3 is fully configured (AWS_ACCESS_KEY_ID, AWS_SECRET_ACCESS_KEY,
 *     AWS_BUCKET all non-empty), files are uploaded to S3 under feeds/{post}/{uuid}
 *     and only the URL is stored in the database.
 *   - Otherwise, the raw binary content is stored as a BLOB in `file_data`.
 *     This is the default development/self-hosted mode.
 *
 * Both backends produce identical FeedAttachment records — only `storage_type`,
 * `file_url`, and `file_data` differ. The FeedAttachmentController and
 * FeedAttachment::url accessor handle serving from both backends transparently.
 *
 * Security hardening applied:
 *   - MIME type verified from file content (not client-supplied header)
 *   - File size checked against configured maximum
 *   - Original filename sanitised (path traversal, null bytes, non-ASCII stripped)
 *   - Binary content read from temp file only after all validations pass
 *   - Uploader identity always recorded
 */
class FeedAttachmentService
{
    /**
     * Allowed MIME types (verified server-side from file content).
     * Mirrors the Livewire/API upload rules for consistency.
     */
    public const ALLOWED_MIMES = [
        'image/jpeg',
        'image/jpg',
        'image/png',
        'image/gif',
        'image/webp',
        'audio/mpeg',       // .mp3
        'audio/mp4',        // .m4a
        'audio/ogg',        // .ogg
        'audio/wav',        // .wav
        'audio/x-wav',      // alternate wav MIME
        'application/pdf',
    ];

    /**
     * Maximum permitted file size in bytes (50 MB).
     * Matches the Livewire/API validation rule max:51200 (KB).
     */
    public const MAX_BYTES = 52_428_800; // 50 MB

    /** S3 path prefix for all feed attachments. */
    private const S3_PREFIX = 'feeds';

    /**
     * Validate, sanitise, and persist a feed file attachment.
     *
     * Automatically selects S3 storage when AWS credentials are configured,
     * falling back to database BLOB storage otherwise.
     *
     * @throws \InvalidArgumentException on MIME or size violations
     * @throws \RuntimeException on read/write failures
     */
    public function store(UploadedFile $file, FeedPost $post, User $uploader): FeedAttachment
    {
        // ── 1. Server-side MIME detection ────────────────────────────────────
        $mime = $file->getMimeType();

        if (! in_array($mime, self::ALLOWED_MIMES, strict: true)) {
            throw new \InvalidArgumentException(
                "File type '{$mime}' is not permitted. Allowed types: "
                .implode(', ', self::ALLOWED_MIMES)
            );
        }

        // ── 2. Size guard ────────────────────────────────────────────────────
        $size = $file->getSize();

        if ($size === false || $size > self::MAX_BYTES) {
            throw new \InvalidArgumentException('File exceeds the maximum permitted size of 50 MB.');
        }

        if ($size === 0) {
            throw new \InvalidArgumentException('Empty files are not permitted.');
        }

        // ── 3. Filename sanitisation ─────────────────────────────────────────
        $originalName = $this->sanitizeFilename($file->getClientOriginalName());

        // ── 4. Read binary content ───────────────────────────────────────────
        $realPath = $file->getRealPath();

        if ($realPath === false || ! is_readable($realPath)) {
            throw new \RuntimeException('Uploaded file is not readable from temporary storage.');
        }

        $content = file_get_contents($realPath);

        if ($content === false) {
            throw new \RuntimeException('Failed to read uploaded file content.');
        }

        // ── 5. Persist (S3 or DB) ────────────────────────────────────────────
        if ($this->isS3Configured()) {
            $attachment = $this->storeOnS3($content, $post, $uploader, $originalName, $mime, $size);
        } else {
            $attachment = $this->storeInDatabase($content, $post, $uploader, $originalName, $mime, $size);
        }

        // ── 6. Audit trail ───────────────────────────────────────────────────
        AuditService::log(
            AuditLog::FEED_ATTACHMENT_UPLOAD,
            "Uploaded '{$originalName}' ({$this->formatBytes($size)}) to feed post #{$post->id}",
            $attachment,
            [
                'post_id' => $post->id,
                'file_name' => $originalName,
                'file_size' => $size,
                'file_type' => $mime,
            ],
            $uploader->id,
            $uploader->current_team_id
        );

        return $attachment;
    }

    /**
     * Sanitise an uploaded filename so it is safe to store and display.
     *
     * Rules applied:
     *   - Strip directory components  (e.g. "../../etc/passwd" → "..etcpasswd" → stripped further)
     *   - Remove null bytes
     *   - Replace all characters outside [a-zA-Z0-9._-] with underscores
     *   - Collapse leading dots (hidden files on Unix)
     *   - Truncate to 255 characters
     *   - Guarantee at least a non-empty fallback
     */
    public function sanitizeFilename(string $name): string
    {
        // Strip path separators and null bytes
        $name = str_replace(['/', '\\', "\0"], '', $name);

        // Remove leading dots to prevent hidden-file creation
        $name = ltrim($name, '.');

        // Allow only safe characters
        $name = preg_replace('/[^a-zA-Z0-9._\-]/', '_', $name) ?? $name;

        // Collapse consecutive underscores/dots for readability (optional)
        $name = preg_replace('/_{2,}/', '_', $name) ?? $name;

        // Truncate
        $name = substr($name, 0, 255);

        // Fallback if the name is now empty
        return $name !== '' ? $name : 'attachment';
    }

    /** Human-readable file size for audit log messages. */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 1).' '.$units[$i];
    }

    /**
     * Returns true when all required S3 environment variables are configured.
     * This allows local/self-hosted deployments to use DB storage automatically.
     */
    public function isS3Configured(): bool
    {
        return ! empty(config('filesystems.disks.s3.key'))
            && ! empty(config('filesystems.disks.s3.secret'))
            && ! empty(config('filesystems.disks.s3.bucket'));
    }

    /**
     * Upload file content to S3 and create a FeedAttachment record with storage_type='s3'.
     */
    private function storeOnS3(
        string $content,
        FeedPost $post,
        User $uploader,
        string $originalName,
        string $mime,
        int $size
    ): FeedAttachment {
        $extension = $this->extensionFromMime($mime);
        $s3Path = self::S3_PREFIX.'/'.$post->id.'/'.Str::uuid()->toString().'.'.$extension;

        try {
            Storage::disk('s3')->put($s3Path, $content, [
                'ContentType' => $mime,
                'ContentDisposition' => 'inline; filename="'.addcslashes($originalName, '"\\').'"',
                'CacheControl' => 'private, max-age=86400',
            ]);
        } catch (\Throwable $e) {
            Log::error('FeedAttachmentService: S3 upload failed, falling back to DB storage', [
                'post_id' => $post->id,
                'file_name' => $originalName,
                'error' => $e->getMessage(),
            ]);

            // Graceful fallback to DB storage when S3 upload fails
            return $this->storeInDatabase($content, $post, $uploader, $originalName, $mime, $size);
        }

        $s3Url = Storage::disk('s3')->url($s3Path);

        try {
            $attachment = FeedAttachment::create([
                'post_id' => $post->id,
                'uploader_id' => $uploader->id,
                'file_name' => $originalName,
                'file_type' => $mime,
                'file_size' => $size,
                'uploaded_at' => now(),
                'storage_type' => 's3',
                'file_url' => $s3Url,
                'file_data' => null,
            ]);
        } catch (\Throwable $e) {
            // Attempt to clean up the S3 object to avoid orphaned files
            try {
                Storage::disk('s3')->delete($s3Path);
            } catch (\Throwable) {
                // Best effort — log but do not cascade
                Log::warning('FeedAttachmentService: could not delete S3 object after DB failure', [
                    's3_path' => $s3Path,
                ]);
            }

            Log::error('FeedAttachmentService: DB write failed after S3 upload', [
                'post_id' => $post->id,
                'error' => $e->getMessage(),
            ]);

            throw new \RuntimeException('The file could not be saved. Please try again.', previous: $e);
        }

        return $attachment;
    }

    /**
     * Store file content as a BLOB in the database (default/fallback backend).
     */
    private function storeInDatabase(
        string $content,
        FeedPost $post,
        User $uploader,
        string $originalName,
        string $mime,
        int $size
    ): FeedAttachment {
        try {
            return FeedAttachment::create([
                'post_id' => $post->id,
                'uploader_id' => $uploader->id,
                'file_name' => $originalName,
                'file_type' => $mime,
                'file_size' => $size,
                'uploaded_at' => now(),
                'storage_type' => 'db',
                'file_url' => null,
                'file_data' => $content,
            ]);
        } catch (\Throwable $e) {
            Log::error('FeedAttachmentService: DB write failed', [
                'post_id' => $post->id,
                'uploader' => $uploader->id,
                'file_name' => $originalName,
                'file_size' => $size,
                'error' => $e->getMessage(),
            ]);

            throw new \RuntimeException('The file could not be saved. Please try again.', previous: $e);
        }
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
}
