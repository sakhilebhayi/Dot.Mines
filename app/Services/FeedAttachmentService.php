<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\FeedAttachment;
use App\Models\FeedPost;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

/**
 * FeedAttachmentService
 *
 * Handles validated, sanitised storage of feed attachment files directly into
 * the platform database.  No external services are used.
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

    /**
     * Validate, sanitise, and persist a feed file attachment into the DB.
     *
     * @throws \InvalidArgumentException on MIME or size violations
     * @throws \RuntimeException on read/write failures
     */
    public function store(UploadedFile $file, FeedPost $post, User $uploader): FeedAttachment
    {
        // ── 1. Server-side MIME detection ────────────────────────────────────
        // getMimeType() uses finfo to probe file content, not the
        // client-supplied Content-Type header, preventing MIME spoofing.
        $mime = $file->getMimeType();

        if (! in_array($mime, self::ALLOWED_MIMES, strict: true)) {
            throw new \InvalidArgumentException(
                "File type '{$mime}' is not permitted. Allowed types: "
                .implode(', ', self::ALLOWED_MIMES)
            );
        }

        // ── 2. Size guard (belt-and-suspenders after form validation) ────────
        $size = $file->getSize();

        if ($size === false || $size > self::MAX_BYTES) {
            throw new \InvalidArgumentException(
                'File exceeds the maximum permitted size of 50 MB.'
            );
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

        // ── 5. Persist to database ───────────────────────────────────────────
        try {
            $attachment = FeedAttachment::create([
                'post_id' => $post->id,
                'uploader_id' => $uploader->id,
                'file_name' => $originalName,
                'file_type' => $mime,
                'file_size' => $size,
                'uploaded_at' => now(),
                'storage_type' => 'db',
                'file_url' => null,       // no external URL for DB-stored files
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

            throw new \RuntimeException(
                'The file could not be saved. Please try again.',
                previous: $e
            );
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
}
