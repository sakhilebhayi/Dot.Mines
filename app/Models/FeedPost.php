<?php

namespace App\Models;

use App\Traits\HasTeamFilters;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * FeedPost Model
 *
 * Represents a structured activity stream post scoped to a team (mine).
 *
 * @property int $id
 * @property int $team_id
 * @property int $author_id
 * @property int|null $mine_area_id
 * @property string|null $shift A | B | C
 * @property string $category breakdown | shift_update | safety_alert | production | general
 * @property string $priority normal | high | critical
 * @property string $body
 * @property array<string, mixed>|null $meta Category-specific structured fields
 * @property int $like_count
 * @property int $comment_count
 * @property int $acknowledgement_count
 * @property bool $is_pinned
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 * @property-read \App\Models\User $author
 * @property-read \App\Models\MineArea|null $mineArea
 * @property-read \App\Models\Team $team
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\FeedComment> $comments
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\FeedAttachment> $attachments
 * @property-read \App\Models\FeedApproval|null $approval
 */
class FeedPost extends Model
{
    use HasFactory, HasTeamFilters, SoftDeletes;

    public const CATEGORIES = [
        'breakdown',
        'shift_update',
        'safety_alert',
        'production',
        'general',
    ];

    public const PRIORITIES = ['normal', 'high', 'critical'];

    public const SHIFTS = ['A', 'B', 'C'];

    protected $fillable = [
        'team_id',
        'author_id',
        'mine_area_id',
        'shift',
        'category',
        'priority',
        'body',
        'meta',
        'like_count',
        'comment_count',
        'acknowledgement_count',
        'is_pinned',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'is_pinned' => 'boolean',
            'like_count' => 'integer',
            'comment_count' => 'integer',
            'acknowledgement_count' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    // ── Relationships ──────────────────────────────────────────────────────────

    /** @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /** @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /** @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\MineArea, $this> */
    public function mineArea(): BelongsTo
    {
        return $this->belongsTo(MineArea::class, 'mine_area_id');
    }

    /** @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Models\FeedAcknowledgement, $this> */
    public function acknowledgements(): HasMany
    {
        return $this->hasMany(FeedAcknowledgement::class, 'post_id');
    }

    public function attachments(): HasMany
    {
        // Exclude file_data (BLOB) from eager-loaded listings.
        // Binary content is fetched on-demand in FeedAttachmentController::serve()
        // to prevent loading megabytes of blobs during feed pagination.
        return $this->hasMany(FeedAttachment::class, 'post_id')
            ->select([
                'id', 'post_id', 'uploader_id', 'storage_type', 'file_url',
                'file_type', 'file_name', 'file_size', 'uploaded_at',
            ]);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(FeedComment::class, 'post_id')->whereNull('parent_comment_id');
    }

    /** @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Models\FeedComment, $this> */
    public function allComments(): HasMany
    {
        return $this->hasMany(FeedComment::class, 'post_id');
    }

    /** @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Models\FeedLike, $this> */
    public function likes(): HasMany
    {
        return $this->hasMany(FeedLike::class, 'post_id');
    }

    /** @return \Illuminate\Database\Eloquent\Relations\HasOne<\App\Models\FeedApproval, $this> */
    public function approval(): HasOne
    {
        return $this->hasOne(FeedApproval::class, 'post_id');
    }
}
