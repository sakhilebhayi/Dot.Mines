<?php

namespace App\Services;

use App\Events\NotificationCreated;
use App\Jobs\SendNotificationEmailJob;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Central dispatcher for all platform notifications.
 *
 * Creates an in-app Notification record and queues emails to
 * the appropriate role-based recipients.
 *
 * Usage:
 *   NotificationService::dispatch([
 *     'team_id'      => $team->id,
 *     'type'         => 'machine_event',
 *     'title'        => 'Machine Added: CAT 775F',
 *     'message'      => 'A new machine has been added to the fleet.',
 *     'alert_level'  => 'info',
 *     'data'         => ['machine_id' => 1, 'event' => 'created'],
 *     'action_url'   => '/machines/1',
 *     'notify_roles' => ['admin', 'fleet_manager'],
 *   ]);
 */
class NotificationService
{
    // Notification type constants
    public const TYPE_MACHINE = 'machine_event';

    public const TYPE_MAINTENANCE = 'maintenance_event';

    public const TYPE_GEOFENCE_BREACH = 'geofence_breach';

    public const TYPE_ALERT = 'alert_triggered';

    public const TYPE_AI_PREDICTION = 'ai_prediction';

    public const TYPE_FUEL = 'fuel_event';

    public const TYPE_MINE_AREA = 'mine_area_event';

    public const TYPE_USER = 'user_event';

    public const TYPE_CUSTOM = 'custom';

    // Alert level constants
    public const LEVEL_CRITICAL = 'critical';

    public const LEVEL_HIGH = 'high';

    public const LEVEL_WARNING = 'warning';

    public const LEVEL_INFO = 'info';

    /**
     * Dispatch an in-app notification and optionally queue emails.
     *
     * @param array{
     *   team_id: int,
     *   type: string,
     *   title: string,
     *   message: string,
     *   alert_level?: string,
     *   data?: array<string, mixed>,
     *   action_url?: string|null,
     *   notify_roles?: array<string>,
     *   notify_user_ids?: array<int>,
     *   email?: bool,
     * } $payload
     */
    public static function dispatch(array $payload): ?Notification
    {
        try {
            $notification = Notification::create([
                'team_id' => $payload['team_id'],
                'type' => $payload['type'],
                'title' => $payload['title'],
                'message' => $payload['message'],
                'alert_level' => $payload['alert_level'] ?? self::LEVEL_INFO,
                'data' => $payload['data'] ?? null,
                'action_url' => $payload['action_url'] ?? null,
                'is_read' => false,
            ]);

            if ($payload['email'] ?? true) {
                $userIds = self::resolveRecipients($payload);

                if (! empty($userIds)) {
                    SendNotificationEmailJob::dispatch($notification->id, $userIds)
                        ->onQueue('notifications');
                }
            }

            // Broadcast real-time update to the team's notification bell.
            NotificationCreated::dispatch($notification);

            return $notification;
        } catch (\Exception $e) {
            Log::error('NotificationService::dispatch failed', [
                'error' => $e->getMessage(),
                'team_id' => $payload['team_id'] ?? null,
                'type' => $payload['type'] ?? null,
            ]);

            return null;
        }
    }

    /**
     * Notify admin and fleet_manager roles for a team.
     *
     * @param  array<string, mixed>  $data
     */
    public static function notifyManagers(
        int $teamId,
        string $type,
        string $title,
        string $message,
        string $alertLevel = self::LEVEL_INFO,
        array $data = [],
        ?string $actionUrl = null,
    ): ?Notification {
        return static::dispatch([
            'team_id' => $teamId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'alert_level' => $alertLevel,
            'data' => $data,
            'action_url' => $actionUrl,
            'notify_roles' => ['admin', 'fleet_manager'],
        ]);
    }

    /**
     * Notify admin role only for a team.
     *
     * @param  array<string, mixed>  $data
     */
    public static function notifyAdmins(
        int $teamId,
        string $type,
        string $title,
        string $message,
        string $alertLevel = self::LEVEL_WARNING,
        array $data = [],
        ?string $actionUrl = null,
    ): ?Notification {
        return static::dispatch([
            'team_id' => $teamId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'alert_level' => $alertLevel,
            'data' => $data,
            'action_url' => $actionUrl,
            'notify_roles' => ['admin'],
        ]);
    }

    /**
     * Resolve the list of user IDs to notify from payload.
     *
     * @param  array<string, mixed>  $payload
     * @return array<int>
     */
    private static function resolveRecipients(array $payload): array
    {
        $userIds = [];

        if (! empty($payload['notify_roles'])) {
            $roleUsers = User::whereHas('roles', function ($q) use ($payload) {
                $q->where('team_id', $payload['team_id'])
                    ->whereIn('name', $payload['notify_roles']);
            })->pluck('id')->toArray();

            $userIds = array_merge($userIds, $roleUsers);
        }

        if (! empty($payload['notify_user_ids'])) {
            $userIds = array_merge($userIds, $payload['notify_user_ids']);
        }

        return array_values(array_unique($userIds));
    }
}
