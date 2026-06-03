<?php

namespace App\Http\Controllers;

use App\Models\Geofence;
use App\Models\Machine;
use App\Models\MineArea;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReportController extends Controller
{
    public function view2(Request $request): View
    {
        $user = Auth::user();

        // The route is inside the auth + ensure_team middleware group, so currentTeam
        // should always be set. If for any reason it is not, return empty collections
        // rather than exposing all records across tenants.
        if ($user && $user->currentTeam) {
            $teamId = $user->currentTeam->id;
            $mineAreas = MineArea::where('team_id', $teamId)->get();
            $geofences = Geofence::whereIn('mine_area_id', $mineAreas->pluck('id'))->get();
            $machines = Machine::where('team_id', $teamId)->get();
        } else {
            $mineAreas = collect();
            $geofences = collect();
            $machines = collect();
        }

        return view('reports.view-2', compact('mineAreas', 'geofences', 'machines'));
    }

    public function generate(Request $request): RedirectResponse
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
