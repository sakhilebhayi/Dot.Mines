<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class FeedMention extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'mentionable_type',
        'mentionable_id',
        'mentioned_user_id',
        'mentioned_by_user_id',
        'team_id',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    /** @return MorphTo<Model, $this> */
    public function mentionable(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<User, $this> */
    public function mentionedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mentioned_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function mentionedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mentioned_by_user_id');
    }

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
