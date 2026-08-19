<?php

namespace App\Livewire;

use App\Models\ActivityLog;
use App\Models\Geofence;
use App\Models\Machine;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class GeofenceDetail extends Component
{
    public Geofence $geofence;

    public function mount(Geofence $geofence)
    {
        if ($geofence->team_id !== Auth::user()->currentTeam->id) {
            abort(403);
        }
        $this->geofence = $geofence;
    }

    public function render()
    {
        $recentEntries = $this->geofence->entries()
            ->with('machine')
            ->latest('entry_time')
            ->take(10)
            ->get();

        $machineCount = $this->geofence->entries()
            ->select('machine_id')
            ->distinct()
            ->count();

        $totalEntries = $this->geofence->entries()->count();

        $team = Auth::user()->currentTeam;

        // Count machine types present in entries (e.g., excavator, articulatd_hauler, dozer)
        $machineIds = $this->geofence->entries()->distinct('machine_id')->pluck('machine_id')->toArray();

        $machineTypeCounts = [];
        if (! empty($machineIds)) {
            $machineTypeCounts = Machine::whereIn('id', $machineIds)
                ->select('machine_type', DB::raw('count(*) as cnt'))
                ->groupBy('machine_type')
                ->pluck('cnt', 'machine_type')
                ->toArray();
        }

        // Team machine counts for tracked/untracked calculation
        $teamMachineCount = Machine::where('team_id', $team->id)->count();
        $machinesTracked = $machineCount;
        $machinesUntracked = max(0, $teamMachineCount - $machinesTracked);

        // Loads: recent entries with tonnage and try to infer authorizer from ActivityLog
        $loads = $this->geofence->entries()->with('machine')->latest('entry_time')->take(20)->get()->map(function ($entry) use ($team) {
            $author = null;

            // Attempt to find an activity log that references this machine and mentions authorization
            $possible = ActivityLog::where('team_id', $team->id)
                ->where(function ($q) use ($entry) {
                    $q->where('description', 'like', "%{$entry->machine->name}%")
                        ->orWhere('action', 'like', '%authoriz%')
                        ->orWhere('description', 'like', '%authoriz%');
                })
                ->orderBy('created_at', 'desc')
                ->first();

            if ($possible && $possible->user) {
                $author = $possible->user->name;
            }

            return [
                'id' => $entry->id,
                'machine' => $entry->machine,
                'entry_time' => $entry->entry_time,
                'exit_time' => $entry->exit_time,
                'tonnage_loaded' => $entry->tonnage_loaded,
                'material_type' => $entry->material_type,
                'authorizer' => $author,
            ];
        });

        return view('livewire.geofence-detail', [
            'recentEntries' => $recentEntries,
            'machineCount' => $machineCount,
            'totalEntries' => $totalEntries,
            'machineTypeCounts' => $machineTypeCounts,
            'machinesTracked' => $machinesTracked,
            'machinesUntracked' => $machinesUntracked,
            'loads' => $loads,
        ]);
    }
}
