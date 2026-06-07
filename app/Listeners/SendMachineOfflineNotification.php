<?php

namespace App\Listeners;

use App\Events\MachineOffline;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class SendMachineOfflineNotification implements ShouldQueue
{
    public string $queue = 'notifications';

    public int $tries = 2;

    public function handle(MachineOffline $event): void
    {
        $machine = $event->machine;
        $reason = $event->reason ?? 'Connection lost';

        NotificationService::dispatch([
            'team_id' => $machine->team_id,
            'type' => NotificationService::TYPE_MACHINE,
            'title' => "Machine Offline: {$machine->name}",
            'message' => "{$machine->name} has gone offline. Reason: {$reason}.",
            'alert_level' => NotificationService::LEVEL_HIGH,
            'data' => [
                'machine_id' => $machine->id,
                'machine_name' => $machine->name,
                'reason' => $reason,
                'last_seen_at' => $machine->last_seen_at?->toIso8601String(),
                'last_location' => $event->lastLocation,
                'event' => 'machine_offline',
            ],
            'action_url' => "/machines/{$machine->id}",
            'notify_roles' => ['admin', 'fleet_manager'],
        ]);
    }

    public function failed(MachineOffline $event, \Throwable $exception): void
    {
        Log::error('SendMachineOfflineNotification failed', [
            'machine_id' => $event->machine->id ?? null,
            'error' => $exception->getMessage(),
        ]);
    }
}
