<?php

namespace App\Services;

use App\Models\Machine;
use App\Models\MachineAreaAssignment;
use App\Models\ProductionRecord;
use App\Models\Shift;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ShiftService
{
    /**
     * Perform a shift change for a team.
     */
    public function performShiftChange(int $teamId, string $shiftType = 'day', ?int $defaultMineAreaId = null): Shift
    {
        $shift = DB::transaction(function () use ($teamId, $shiftType, $defaultMineAreaId) {
            // Snapshot current machine assignments
            $machines = Machine::where('team_id', $teamId)->get();

            $previousAssignments = $machines->map(function (Machine $m) {
                return [
                    'machine_id' => $m->id,
                    'name' => $m->name,
                    'machine_type' => $m->machine_type,
                    'mine_area_id' => $m->mine_area_id,
                    'excavator_id' => $m->excavator_id,
                    'assigned_to_excavator_at' => $m->assigned_to_excavator_at instanceof Carbon ? $m->assigned_to_excavator_at->toDateTimeString() : null,
                    'status' => $m->status,
                ];
            })->toArray();

            // Gather productivity metrics from ProductionRecord for current date and shift
            $today = now()->toDateString();
            $productionQuery = ProductionRecord::query()
                ->where('team_id', $teamId)
                ->where('record_date', $today)
                ->where('shift', $shiftType);

            $productivityMetrics = [
                'total_quantity' => $productionQuery->sum('quantity_produced'),
                'records_count' => $productionQuery->count(),
                'by_mine_area' => [],
            ];

            // by mine area breakdown
            // data_get() keeps this portable across the analyzers' disagreement
            // about what selectRaw()->groupBy()->get() hydrates (runtime: models).
            $byArea = [];
            foreach ($productionQuery->selectRaw('mine_area_id, SUM(quantity_produced) as qty, COUNT(*) as count')->groupBy('mine_area_id')->get() as $row) {
                $byArea[(int) data_get($row, 'mine_area_id')] = [
                    'quantity' => (float) data_get($row, 'qty'),
                    'count' => (int) data_get($row, 'count'),
                ];
            }

            $productivityMetrics['by_mine_area'] = $byArea;

            // Basic shift performance summary
            $performanceSummary = [
                'machine_count' => $machines->count(),
                'active_machines' => $machines->where('status', 'active')->count(),
                'idle_machines' => $machines->where('status', 'idle')->count(),
                'maintenance' => $machines->where('status', 'maintenance')->count(),
            ];

            // Create shift record
            $shift = Shift::create([
                'team_id' => $teamId,
                'shift_type' => $shiftType,
                'started_at' => now(),
                'previous_assignments' => $previousAssignments,
                'productivity_metrics' => $productivityMetrics,
                'performance_summary' => $performanceSummary,
            ]);

            // Mark active MachineAreaAssignment entries as unassigned (close them)
            MachineAreaAssignment::where('team_id', $teamId)->whereNull('unassigned_at')->update(['unassigned_at' => now()]);

            // Reset machine assignments: excavator and optionally mine_area
            $update = ['excavator_id' => null, 'assigned_to_excavator_at' => null];
            if (is_null($defaultMineAreaId)) {
                $update['mine_area_id'] = null;
            } else {
                $update['mine_area_id'] = $defaultMineAreaId;
            }

            Machine::where('team_id', $teamId)->update($update);

            // Return created shift
            return $shift->fresh();
        });
        assert($shift instanceof Shift);

        return $shift;
    }
}
