<?php

namespace App\Models;

use App\Traits\HasTeamFilters;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One person's response to one comment, from the closed comment vocabulary
 * (FeedComment::REACTIONS: like / acknowledge / reject). A database unique
 * per (comment, user, emoji) makes it a toggle, not a counter.
 *
 * @property int $id
 * @property int $team_id
 * @property int $feed_comment_id
 * @property int $user_id
 * @property string $emoji
 */
class FeedCommentReaction extends Model
{
    use HasTeamFilters;

    /** @var array<int, string> */
    protected $fillable = ['team_id', 'feed_comment_id', 'user_id', 'emoji'];

    /**
     * @return BelongsTo<User,$this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
