<?php

namespace App\Observers;

use App\Models\MineArea;
use App\Services\NotificationService;

class MineAreaObserver
{
    public function created(MineArea $mineArea): void
    {
        NotificationService::notifyManagers(
            teamId: $mineArea->team_id,
            type: NotificationService::TYPE_MINE_AREA,
            title: "Mine Area Added: {$mineArea->name}",
            message: "A new mine area '{$mineArea->name}' has been created.",
            alertLevel: NotificationService::LEVEL_INFO,
            data: [
                'mine_area_id' => $mineArea->id,
                'name' => $mineArea->name,
                'location' => $mineArea->location,
                'status' => $mineArea->status,
                'manager' => $mineArea->manager_name,
                'event' => 'created',
            ],
            actionUrl: '/mine-areas',
        );
    }

    public function updated(MineArea $mineArea): void
    {
        $watched = ['name', 'status', 'manager_name', 'location'];
        $changed = array_intersect($watched, array_keys($mineArea->getChanges()));

        if (empty($changed)) {
            return;
        }

        $changes = [];
        foreach ($changed as $field) {
            $changes[$field] = [
                'from' => $mineArea->getOriginal($field),
                'to' => $mineArea->getAttribute($field),
            ];
        }

        NotificationService::notifyManagers(
            teamId: $mineArea->team_id,
            type: NotificationService::TYPE_MINE_AREA,
            title: "Mine Area Updated: {$mineArea->name}",
            message: "Mine area '{$mineArea->name}' details have been updated.",
            alertLevel: NotificationService::LEVEL_INFO,
            data: [
                'mine_area_id' => $mineArea->id,
                'name' => $mineArea->name,
                'changes' => $changes,
                'event' => 'updated',
            ],
            actionUrl: '/mine-areas',
        );
    }

    public function deleted(MineArea $mineArea): void
    {
        NotificationService::notifyManagers(
            teamId: $mineArea->team_id,
            type: NotificationService::TYPE_MINE_AREA,
            title: "Mine Area Removed: {$mineArea->name}",
            message: "Mine area '{$mineArea->name}' has been permanently removed.",
            alertLevel: NotificationService::LEVEL_WARNING,
            data: [
                'mine_area_id' => $mineArea->id,
                'name' => $mineArea->name,
                'event' => 'deleted',
            ],
        );
    }
}
