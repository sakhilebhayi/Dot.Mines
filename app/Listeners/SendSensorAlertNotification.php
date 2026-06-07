<?php

namespace App\Listeners;

use App\Events\SensorReadingRecorded;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class SendSensorAlertNotification implements ShouldQueue
{
    public string $queue = 'notifications';

    public int $tries = 2;

    public function handle(SensorReadingRecorded $event): void
    {
        // Only notify on anomalous readings to avoid noise from normal sensor data.
        if (! ($event->reading['is_anomaly'] ?? false)) {
            return;
        }

        NotificationService::dispatch([
            'team_id' => $event->teamId,
            'type' => NotificationService::TYPE_ALERT,
            'title' => "Sensor Anomaly: {$event->sensor->name}",
            'message' => "Anomalous reading detected — {$event->reading['value']} {$event->reading['unit']} from sensor {$event->sensor->name}.",
            'alert_level' => NotificationService::LEVEL_WARNING,
            'data' => [
                'sensor_id' => $event->sensor->id,
                'sensor_name' => $event->sensor->name,
                'sensor_type' => $event->sensor->sensor_type,
                'value' => $event->reading['value'],
                'unit' => $event->reading['unit'],
                'event' => 'sensor_anomaly',
            ],
            'action_url' => "/iot/sensors/{$event->sensor->id}",
            'notify_roles' => ['admin', 'fleet_manager'],
        ]);
    }

    public function failed(SensorReadingRecorded $event, \Throwable $exception): void
    {
        Log::error('SendSensorAlertNotification failed', [
            'sensor_id' => $event->sensor->id ?? null,
            'error' => $exception->getMessage(),
        ]);
    }
}
