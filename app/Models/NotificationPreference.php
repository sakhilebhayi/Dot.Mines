<?php

namespace App\Models;

use App\Services\NotificationService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property int $team_id
 * @property string $notification_type
 * @property bool $email_enabled
 * @property bool $in_app_enabled
 * @property string $min_alert_level
 */
class NotificationPreference extends Model
{
    protected $fillable = [
        'user_id',
        'team_id',
        'notification_type',
        'email_enabled',
        'in_app_enabled',
        'min_alert_level',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'email_enabled' => 'boolean',
            'in_app_enabled' => 'boolean',
        ];
    }

    /** Alert level severity order for threshold comparisons. */
    private const LEVEL_ORDER = [
        NotificationService::LEVEL_INFO => 0,
        NotificationService::LEVEL_WARNING => 1,
        NotificationService::LEVEL_HIGH => 2,
        NotificationService::LEVEL_CRITICAL => 3,
    ];

    /** Returns true when the given alert level meets or exceeds this preference's threshold. */
    public function isAboveMinLevel(string $alertLevel): bool
    {
        $incomingRank = self::LEVEL_ORDER[$alertLevel] ?? 0;
        $minRank = self::LEVEL_ORDER[$this->min_alert_level] ?? 0;

        return $incomingRank >= $minRank;
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
