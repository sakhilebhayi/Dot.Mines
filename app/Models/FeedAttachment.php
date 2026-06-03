<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * FeedAttachment Model
 *
 * Supports two storage backends, selected per-row via `storage_type`:
 *   - 'db'  — binary file content stored in `file_data`; served via FeedAttachmentController
 *   - 's3'  — legacy records with an external AWS S3 URL in `file_url`
 *
 * Always use the `url` accessor instead of `file_url` directly so that both
 * storage backends resolve to a valid, routable URL.
 *
 * @property int $id
 * @property int $post_id
 * @property int|null $uploader_id
 * @property string $storage_type 'db' | 's3'
 * @property string|null $file_url populated for legacy S3 records only
 * @property string|null $file_data raw binary content (DB storage only)
 * @property string $file_type server-verified MIME type
 * @property string|null $file_name sanitised original filename
 * @property int|null $file_size bytes
 * @property \Carbon\Carbon $uploaded_at
 * @property-read string $url            routable URL for serving or downloading this attachment
 * @property-read \App\Models\FeedPost $post
 * @property-read \App\Models\User|null $uploader
 */
class FeedAttachment extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'post_id',
        'uploader_id',
        'storage_type',
        'file_url',
        'file_data',
        'file_type',
        'file_name',
        'file_size',
        'uploaded_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'uploaded_at' => 'datetime',
        ];
    }

    /**
     * Never expose the raw binary blob in JSON serialisation or array output.
     * This also prevents accidental inclusion in API responses.
     */
    protected $hidden = ['file_data'];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function post(): BelongsTo
    {
        return $this->belongsTo(FeedPost::class, 'post_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploader_id');
    }

    // ── Accessors ─────────────────────────────────────────────────────────────

    /**
     * Returns a routable URL suitable for use in <img src>, <a href>, etc.
     *
     * - DB-stored records: routes through FeedAttachmentController which enforces auth
     * - Legacy S3 records: returns the original external URL
     */
    public function getUrlAttribute(): string
    {
        if ($this->storage_type === 's3' && ! empty($this->file_url)) {
            return $this->file_url;
        }

        return route('feed.attachment.serve', $this->id);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function isImage(): bool
    {
        return str_starts_with($this->file_type, 'image/');
    }

    public function isAudio(): bool
    {
        return str_starts_with($this->file_type, 'audio/');
    }

    public function isPdf(): bool
    {
        return $this->file_type === 'application/pdf';
    }

    /**
     * Human-readable file size (e.g. "4.2 MB").
     */
    public function formattedSize(): string
    {
        if (! $this->file_size) {
            return 'Unknown size';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = $this->file_size;
        $i = 0;

        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 1).' '.$units[$i];
    }
}
