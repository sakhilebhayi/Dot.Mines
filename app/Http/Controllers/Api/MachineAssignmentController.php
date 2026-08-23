<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\MachineResource;
use App\Models\Machine;
use App\Models\MachineAreaAssignment;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MachineAssignmentController extends Controller
{
    /**
     * Get machines available for assignment
     */
    public function available(Request $request): JsonResponse
    {
        $team = auth()->user()?->currentTeam;

        // Machine has no mineAreas() belongsToMany -- assignment state
        // lives in machine_area_assignments; "available" means no open
        // assignment row. The old whereDoesntHave('mineAreas') threw a
        // RelationNotFoundException on every request.
        $machines = Machine::where('team_id', $team?->id)
            ->whereDoesntHave('areaAssignments', function (Builder $query) {
                $query->whereNull('unassigned_at');
            })
            ->paginate(is_numeric($request->input('per_page')) ? (int) $request->input('per_page') : 15);

        return ApiResponse::paginated($machines, MachineResource::class);
    }

    /**
     * Get assignment history for a machine
     */
    public function history(Machine $machine): JsonResponse
    {
        // The old $machine->mineAreas() pivot query referenced a relation
        // and a mine_area_machine table that never existed (fatal on call);
        // machine_area_assignments is the real assignment ledger.
        $historyRelation = $machine->areaAssignments();
        $historyRelation->with('mineArea:id,name')->orderByDesc('assigned_at');
        $history = $historyRelation->get()
            ->map(fn (MachineAreaAssignment $assignment): array => [
                'name' => $assignment->mineArea?->name,
                'assigned_at' => $assignment->assigned_at,
                'unassigned_at' => $assignment->unassigned_at,
                'notes' => $assignment->notes,
            ])
            ->values();

        return ApiResponse::collection($history->all());
    }
}
