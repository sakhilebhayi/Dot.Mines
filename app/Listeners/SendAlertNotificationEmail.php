<?php

namespace App\Listeners;

use App\Events\AlertTriggered;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class SendAlertNotificationEmail implements ShouldQueue
{
    public string $queue = 'notifications';

    public int $tries = 2;

    public function handle(AlertTriggered $event): void
    {
        $alert = $event->alert;

        // Map alert priority to notification alert level
        $alertLevel = match ($alert->priority ?? 'medium') {
            'critical' => NotificationService::LEVEL_CRITICAL,
            'high' => NotificationService::LEVEL_HIGH,
            'medium' => NotificationService::LEVEL_WARNING,
            default => NotificationService::LEVEL_INFO,
        };

        // Only email for high/critical priority to avoid noise
        $email = in_array($alertLevel, [NotificationService::LEVEL_CRITICAL, NotificationService::LEVEL_HIGH], true);

        NotificationService::dispatch([
            'team_id' => $alert->team_id,
            'type' => NotificationService::TYPE_ALERT,
            'title' => $alert->title,
            'message' => $alert->description ?? $alert->message ?? $alert->title,
            'alert_level' => $alertLevel,
            'data' => [
                'alert_id' => $alert->id,
                'alert_type' => $alert->type,
                'priority' => $alert->priority,
                'machine' => $alert->machine?->name,
                'machine_id' => $alert->machine_id,
                'mine_area' => $alert->mineArea?->name,
                'triggered_at' => $alert->triggered_at?->format('Y-m-d H:i'),
            ],
            'action_url' => '/alerts',
            'notify_roles' => ['admin', 'fleet_manager'],
            'email' => $email,
        ]);
    }

    public function failed(AlertTriggered $event, \Throwable $exception): void
    {
        Log::error('SendAlertNotificationEmail failed', [
            'alert_id' => $event->alert->id ?? null,
            'error' => $exception->getMessage(),
        ]);
    }
}
