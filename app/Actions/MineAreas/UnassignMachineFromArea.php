<?php

namespace App\Actions\MineAreas;

use App\Models\Machine;
use App\Models\MachineAreaAssignment;
use App\Models\MineArea;
use App\Models\Team;

/**
 * Removes a machine from an area by moving it to another ACTIVE area --
 * machines.mine_area_id is NOT NULL, so "unassigned" cannot exist
 * (refactor R3: merged from MineAreaDetail and MachineAssignmentManager,
 * which had drifted -- only one of them wrote the new area's history row).
 * Returns the receiving area, or null when no other active area exists
 * and the move must be refused.
 */
final class UnassignMachineFromArea
{
    public function execute(Team $team, MineArea $fromArea, Machine $machine, int $movedBy): ?MineArea
    {
        $otherArea = MineArea::query()
            ->where('team_id', $team->id)
            ->where('status', 'active')
            ->where('id', '!=', $fromArea->id)
            ->first();

        if ($otherArea === null) {
            return null;
        }

        MachineAreaAssignment::query()
            ->where('machine_id', $machine->id)
            ->whereNull('unassigned_at')
            ->update(['unassigned_at' => now()]);

        $machine->update(['mine_area_id' => $otherArea->id]);

        MachineAreaAssignment::query()->create([
            'team_id' => $team->id,
            'machine_id' => $machine->id,
            'mine_area_id' => $otherArea->id,
            'assigned_by' => $movedBy,
            'assigned_at' => now(),
            'reason' => 'Removed from '.$fromArea->name,
        ]);

        return $otherArea;
    }
}
