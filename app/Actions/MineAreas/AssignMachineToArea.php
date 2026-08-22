<?php

namespace App\Actions\MineAreas;

use App\Models\Machine;
use App\Models\MachineAreaAssignment;
use App\Models\MineArea;
use App\Models\Team;

/**
 * The single authority for putting a machine into a mine area (refactor
 * R3: previously duplicated with drift between MineAreaDetail and
 * MachineAssignmentManager -- one guarded same-area no-ops, neither
 * closed the previous area's open history row, so reassignments left
 * dangling "still assigned" records).
 */
final class AssignMachineToArea
{
    public function execute(
        Team $team,
        MineArea $area,
        Machine $machine,
        int $assignedBy,
        ?string $reason = null,
        ?string $notes = null,
    ): void {
        if ($machine->mine_area_id === $area->id) {
            return;
        }

        // Close any still-open assignment history before opening the new
        // one -- the history table's invariant is one open row per machine.
        MachineAreaAssignment::query()
            ->where('machine_id', $machine->id)
            ->whereNull('unassigned_at')
            ->update(['unassigned_at' => now()]);

        $machine->update(['mine_area_id' => $area->id]);

        MachineAreaAssignment::query()->create([
            'team_id' => $team->id,
            'machine_id' => $machine->id,
            'mine_area_id' => $area->id,
            'assigned_by' => $assignedBy,
            'assigned_at' => now(),
            'reason' => $reason,
            'notes' => $notes,
        ]);
    }
}
