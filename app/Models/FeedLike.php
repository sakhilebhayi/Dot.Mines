<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * FeedLike Model
 *
 * @property int $id
 * @property int $post_id
 * @property int $user_id
 * @property Carbon $liked_at
 */
class FeedLike extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'post_id',
        'user_id',
        'liked_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'liked_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<FeedPost, $this> */
    public function post(): BelongsTo
    {
        return $this->belongsTo(FeedPost::class, 'post_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
