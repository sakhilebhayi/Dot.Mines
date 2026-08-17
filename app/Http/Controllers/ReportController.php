<?php

namespace App\Http\Controllers;

use App\Models\Geofence;
use App\Models\Machine;
use App\Models\MineArea;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReportController extends Controller
{
    public function view2(Request $request)
    {
        $user = Auth::user();

        // No unscoped fallback here: EnsureTeamContext only aborts when a
        // team_id is present but invalid/unauthorized. A user who belongs to
        // no team at all (e.g. removed from their last team) passes that
        // middleware with no team set, so `currentTeam` can genuinely be
        // null when this action runs. Falling through to `Model::all()` in
        // that case would leak every team's mine areas/geofences/machines,
        // so we abort instead of guessing a scope.
        if (! $user || ! isset($user->currentTeam->id)) {
            abort(403, 'No active team selected.');
        }

        $teamId = $user->currentTeam->id;
        $mineAreas = MineArea::where('team_id', $teamId)->get();
        $geofences = Geofence::whereIn('mine_area_id', $mineAreas->pluck('id'))->get();
        $machines = Machine::where('team_id', $teamId)->get();

        return view('reports.view-2', compact('mineAreas', 'geofences', 'machines'));
    }

    public function generate(Request $request)
    {
        $data = $request->validate([
            'mine_area_id' => 'nullable|exists:mine_areas,id',
            'geofence_id' => 'nullable|exists:geofences,id',
            'machine_id' => 'nullable|exists:machines,id',
        ]);

        // Placeholder: implement actual report generation logic here.
        // For now, redirect back with a flash message and the selected scope.
        return back()->with('status', 'Report generation requested')->with('report_scope', $data);
    }
}
