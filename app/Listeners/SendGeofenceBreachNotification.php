<?php

namespace App\Listeners;

use App\Events\GeofenceEntryDetected;
use App\Events\GeofenceExitDetected;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class SendGeofenceBreachNotification implements ShouldQueue
{
    public string $queue = 'notifications';

    public int $tries = 2;

    public function handleEntry(GeofenceEntryDetected $event): void
    {
        $this->notify($event->entry, 'entered');
    }

    public function handleExit(GeofenceExitDetected $event): void
    {
        $this->notify($event->entry, 'exited');
    }

    private function notify(mixed $entry, string $direction): void
    {
        try {
            $geofence = $entry->geofence;
            $machine = $entry->machine;

            if (! $geofence || ! $machine) {
                return;
            }

            $verb = $direction === 'entered' ? 'entered' : 'exited';
            $title = "{$machine->name} {$verb} geofence: {$geofence->name}";
            $message = "{$machine->name} has {$verb} the '{$geofence->name}' geofence zone.";

            NotificationService::dispatch([
                'team_id' => $geofence->team_id,
                'type' => NotificationService::TYPE_GEOFENCE_BREACH,
                'title' => $title,
                'message' => $message,
                'alert_level' => NotificationService::LEVEL_HIGH,
                'data' => [
                    'geofence_id' => $geofence->id,
                    'geofence_name' => $geofence->name,
                    'machine_id' => $machine->id,
                    'machine_name' => $machine->name,
                    'direction' => $direction,
                    'time' => $entry->entry_time?->format('Y-m-d H:i'),
                    'latitude' => $entry->entry_latitude,
                    'longitude' => $entry->entry_longitude,
                ],
                'action_url' => '/geofences',
                'notify_roles' => ['admin', 'fleet_manager'],
                'email' => true,
            ]);
        } catch (\Exception $e) {
            Log::error('SendGeofenceBreachNotification failed', [
                'error' => $e->getMessage(),
                'direction' => $direction,
            ]);
        }
    }
}
