<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * FeedAcknowledgement Model
 *
 * @property int $id
 * @property int $post_id
 * @property int $user_id
 * @property Carbon $acknowledged_at
 */
class FeedAcknowledgement extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'post_id',
        'user_id',
        'acknowledged_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'acknowledged_at' => 'datetime',
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
