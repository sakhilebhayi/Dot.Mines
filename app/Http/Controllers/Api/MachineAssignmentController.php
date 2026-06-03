<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Machine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MachineAssignmentController extends Controller
{
    /**
     * Get machines available for assignment
     */
    public function available(Request $request): JsonResponse
    {
        $team = Auth::user()->currentTeam;

        $machines = Machine::where('team_id', $team->id)
            ->whereDoesntHave('mineAreas')
            ->paginate($request->get('per_page', 15));

        return response()->json($machines);
    }

    /**
     * Get assignment history for a machine
     */
    public function history(Machine $machine): JsonResponse
    {
        $this->authorize('view', $machine);

        $history = $machine->mineAreas()
            ->select('mine_areas.name', 'mine_area_machine.assigned_at', 'mine_area_machine.unassigned_at', 'mine_area_machine.notes')
            ->withPivot('assigned_at', 'unassigned_at', 'notes')
            ->get();

        return response()->json($history);
    }
}
