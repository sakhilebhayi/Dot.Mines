<?php

namespace App\Models;

use App\Traits\HasTeamFilters;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One person's acknowledgement on one feed item.
 *
 * The emoji set is closed (FeedItem::REACTIONS): a fixed operational
 * vocabulary -- seen / done / attention -- not an open palette.
 *
 * @property int $id
 * @property int $team_id
 * @property int $feed_item_id
 * @property int $user_id
 * @property string $emoji
 */
class FeedReaction extends Model
{
    use HasTeamFilters;

    /** @var array<int, string> */
    protected $fillable = ['team_id', 'feed_item_id', 'user_id', 'emoji'];

    /**
     * @return BelongsTo<User,$this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
