<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * FeedComment Model
 *
 * Supports one level of nesting via parent_comment_id.
 *
 * @property int $id
 * @property int $post_id
 * @property int|null $parent_comment_id
 * @property int $author_id
 * @property string $body
 * @property bool $is_edited
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 * @property-read \App\Models\FeedPost $post
 * @property-read \App\Models\User $author
 * @property-read \App\Models\FeedComment|null $parent
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\FeedComment> $replies
 */
class FeedComment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'post_id',
        'parent_comment_id',
        'author_id',
        'body',
        'is_edited',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_edited' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    // ── Relationships ──────────────────────────────────────────────────────────

    public function post(): BelongsTo
    {
        return $this->belongsTo(FeedPost::class, 'post_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(FeedComment::class, 'parent_comment_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(FeedComment::class, 'parent_comment_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    public function isTopLevel(): bool
    {
        return $this->parent_comment_id === null;
    }

    public function isEditableBy(User $user): bool
    {
        if ($this->author_id !== $user->id) {
            return false;
        }

        // Editable within 5 minutes of creation
        return $this->created_at->diffInMinutes(now()) <= 5;
    }
}
