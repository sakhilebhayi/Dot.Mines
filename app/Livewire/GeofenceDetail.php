<?php

namespace App\Livewire;

use App\Models\ActivityLog;
use App\Models\Geofence;
use App\Models\Machine;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class GeofenceDetail extends Component
{
    public Geofence $geofence;

    public function mount(Geofence $geofence): void
    {
        if ($geofence->team_id !== Auth::user()->currentTeam->id) {
            abort(403);
        }
        $this->geofence = $geofence;
    }

    public function render(): View
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
                ->select(['machine_type', DB::raw('count(*) as cnt')])
                ->groupBy('machine_type')
                ->pluck('cnt', 'machine_type')
                ->toArray();
        }

        // Team machine counts for tracked/untracked calculation
        $teamMachineCount = Machine::where('team_id', $team->id)->count();
        $machinesTracked = $machineCount;
        $machinesUntracked = max(0, $teamMachineCount - $machinesTracked);

        // Loads: batch-fetch entries and activity logs in two queries (no N+1).
        $recentLoadEntries = $this->geofence->entries()
            ->with('machine')
            ->latest('entry_time')
            ->take(20)
            ->get();

        // Single query for relevant activity logs across all machines in the result set.
        $machineNames = $recentLoadEntries
            ->map(fn ($e) => $e->machine?->name)
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        /** @var Collection<string, ActivityLog|null> $activityLogsByMachine */
        $activityLogsByMachine = collect();
        if (! empty($machineNames)) {
            $logs = ActivityLog::where('team_id', $team->id)
                ->where(function ($q) use ($machineNames): void {
                    foreach ($machineNames as $name) {
                        $q->orWhere('description', 'like', '%'.$name.'%');
                    }
                })
                ->where(function ($q): void {
                    $q->where('action', 'like', '%authoriz%')
                        ->orWhere('description', 'like', '%authoriz%');
                })
                ->latest('created_at')
                ->with('user')
                ->get();

            foreach ($machineNames as $name) {
                $activityLogsByMachine[$name] = $logs->first(
                    fn ($log) => str_contains((string) $log->description, $name)
                );
            }
        }

        $loads = $recentLoadEntries->map(function ($entry) use ($activityLogsByMachine) {
            $machineName = $entry->machine?->name;
            $author = null;
            if ($machineName && $activityLogsByMachine->has($machineName)) {
                $possible = $activityLogsByMachine[$machineName];
                if ($possible?->user) {
                    $author = $possible->user->name;
                }
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
