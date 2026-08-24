<?php

namespace App\Models;

use App\Traits\HasTeamFilters;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A comment on a feed item.
 *
 * @property int $id
 * @property int $team_id
 * @property int $feed_item_id
 * @property int|null $parent_id
 * @property int $user_id
 * @property string $body
 * @property Carbon $created_at
 * @property-read User|null $user
 * @property-read Collection<int, FeedComment> $replies
 * @property-read Collection<int, FeedCommentReaction> $reactions
 */
class FeedComment extends Model
{
    use HasTeamFilters, SoftDeletes;

    /**
     * The closed response vocabulary for comments: like, acknowledge, reject.
     *
     * @var array<int, string>
     */
    public const REACTIONS = ['👍', '✅', '❌'];

    /** @var array<int, string> */
    protected $fillable = ['team_id', 'feed_item_id', 'parent_id', 'user_id', 'body'];

    /**
     * @return BelongsTo<User,$this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<FeedItem,$this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(FeedItem::class, 'feed_item_id');
    }

    /**
     * @return BelongsTo<FeedComment,$this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(FeedComment::class, 'parent_id');
    }

    /**
     * @return HasMany<FeedComment,$this>
     */
    public function replies(): HasMany
    {
        return $this->hasMany(FeedComment::class, 'parent_id');
    }

    /**
     * @return HasMany<FeedCommentReaction,$this>
     */
    public function reactions(): HasMany
    {
        return $this->hasMany(FeedCommentReaction::class);
    }
}
