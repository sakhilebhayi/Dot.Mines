<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * FeedApproval Model
 *
 * One approval record per post (unique on post_id).
 *
 * @property int $id
 * @property int $post_id
 * @property int $approver_id
 * @property string $status pending | approved | rejected
 * @property string|null $reason Required when rejected
 * @property \Carbon\Carbon|null $reviewed_at
 */
class FeedApproval extends Model
{
    public $timestamps = false;

    public const STATUSES = ['pending', 'approved', 'rejected'];

    protected $fillable = [
        'post_id',
        'approver_id',
        'status',
        'reason',
        'reviewed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
        ];
    }

    /** @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\FeedPost, $this> */
    public function post(): BelongsTo
    {
        return $this->belongsTo(FeedPost::class, 'post_id');
    }

    /** @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\User, $this> */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }
}
