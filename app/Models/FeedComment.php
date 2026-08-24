<?php

namespace App\Models;

use App\Traits\HasTeamFilters;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A comment on a feed item.
 *
 * @property int $id
 * @property int $team_id
 * @property int $feed_item_id
 * @property int $user_id
 * @property string $body
 * @property Carbon $created_at
 * @property-read User|null $user
 */
class FeedComment extends Model
{
    use HasTeamFilters, SoftDeletes;

    /** @var array<int, string> */
    protected $fillable = ['team_id', 'feed_item_id', 'user_id', 'body'];

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
}
