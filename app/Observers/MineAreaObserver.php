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
