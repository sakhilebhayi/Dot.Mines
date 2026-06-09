<?php

namespace App\Observers;

use App\Models\Machine;
use App\Services\NotificationService;
use App\Services\QueryCacheService;

class MachineObserver
{
    public function created(Machine $machine): void
    {
        QueryCacheService::invalidateSerialNumbers($machine->team_id);

        NotificationService::notifyManagers(
            teamId: $machine->team_id,
            type: NotificationService::TYPE_MACHINE,
            title: "Machine Added: {$machine->name}",
            message: "A new {$machine->machine_type} ({$machine->manufacturer} {$machine->model}) has been added to the fleet.",
            alertLevel: NotificationService::LEVEL_INFO,
            data: [
                'machine_id' => $machine->id,
                'name' => $machine->name,
                'type' => $machine->machine_type,
                'manufacturer' => $machine->manufacturer,
                'model' => $machine->model,
                'serial_number' => $machine->serial_number,
                'event' => 'created',
            ],
            actionUrl: "/machines/{$machine->id}",
        );
    }

    public function updated(Machine $machine): void
    {
        if ($machine->wasChanged('serial_number')) {
            QueryCacheService::invalidateSerialNumbers($machine->team_id);
        }

        // Only notify on meaningful status or name changes
        $watched = ['status', 'name', 'mine_area_id'];
        $changed = array_intersect($watched, array_keys($machine->getChanges()));

        if (empty($changed)) {
            return;
        }

        $changes = [];
        foreach ($changed as $field) {
            $changes[$field] = [
                'from' => $machine->getOriginal($field),
                'to' => $machine->getAttribute($field),
            ];
        }

        $alertLevel = $machine->wasChanged('status') && $machine->status === 'breakdown'
            ? NotificationService::LEVEL_HIGH
            : NotificationService::LEVEL_INFO;

        NotificationService::notifyManagers(
            teamId: $machine->team_id,
            type: NotificationService::TYPE_MACHINE,
            title: "Machine Updated: {$machine->name}",
            message: "Machine status or assignment has changed for {$machine->name}.",
            alertLevel: $alertLevel,
            data: [
                'machine_id' => $machine->id,
                'name' => $machine->name,
                'status' => $machine->status,
                'event' => 'updated',
            ],
            actionUrl: "/machines/{$machine->id}",
        );
    }

    public function deleted(Machine $machine): void
    {
        QueryCacheService::invalidateSerialNumbers($machine->team_id);

        NotificationService::notifyManagers(
            teamId: $machine->team_id,
            type: NotificationService::TYPE_MACHINE,
            title: "Machine Removed: {$machine->name}",
            message: "{$machine->name} ({$machine->manufacturer} {$machine->model}) has been removed from the fleet.",
            alertLevel: NotificationService::LEVEL_WARNING,
            data: [
                'machine_id' => $machine->id,
                'name' => $machine->name,
                'type' => $machine->machine_type,
                'serial_number' => $machine->serial_number,
                'event' => 'deleted',
            ],
        );
    }
}
