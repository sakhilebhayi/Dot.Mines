<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserFeedPreference extends Model
{
    protected $fillable = [
        'user_id',
        'team_id',
        'category_preferences',
        'notify_on_comment',
        'notify_on_reply',
        'notify_on_approval',
        'notify_on_mention',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category_preferences' => 'array',
            'notify_on_comment' => 'boolean',
            'notify_on_reply' => 'boolean',
            'notify_on_approval' => 'boolean',
            'notify_on_mention' => 'boolean',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function wantsCategory(string $category): bool
    {
        $prefs = $this->category_preferences ?? [];

        // default to true if not explicitly set
        return (bool) ($prefs[$category] ?? true);
    }
}
