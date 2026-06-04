<?php

namespace App\Livewire;

use App\Models\Alert;
use App\Models\Geofence;
use App\Models\Machine;
use App\Services\QueryCacheService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Dashboard extends Component
{
    public int $totalMachines = 0;

    public int $activeMachines = 0;

    public int $activeAlerts = 0;

    public int $totalGeofences = 0;

    /** @var array<string, mixed> */
    public array $recentAlerts = [];

    /** @var array<string, mixed> */
    public array $machineStatus = [];

    /** @var array<string, mixed> */
    public array $activityFeed = [];

    public bool $isLoading = true;

    public function mount(): void
    {
        $this->loadDashboardData();
    }

    public function loadDashboardData(): void
    {
        $team = Auth::user()->currentTeam;

        if ($team === null) {
            $this->isLoading = false;

            return;
        }

        // Use cache service for dashboard statistics
        $stats = QueryCacheService::dashboardStats($team->id, function () use ($team) {
            return [
                'total_machines' => Machine::where('team_id', $team->id)->count(),
                'active_machines' => Machine::where('team_id', $team->id)
                    ->where('status', 'active')
                    ->count(),
                'active_alerts' => Alert::where('team_id', $team->id)
                    ->where('status', 'active')
                    ->count(),
                'total_geofences' => Geofence::where('team_id', $team->id)->count(),
            ];
        });

        $this->totalMachines = $stats['total_machines'];
        $this->activeMachines = $stats['active_machines'];
        // Ensure active alerts count is accurate for the current team (bypass stale cache)
        $this->activeAlerts = Alert::where('team_id', $team->id)
            ->where('status', 'active')
            ->count();
        $this->totalGeofences = $stats['total_geofences'];

        // Recent Alerts (with eager loading)
        $this->recentAlerts = Alert::where('team_id', $team->id)
            ->with('machine')
            ->latest('created_at')
            ->take(5)
            ->get()
            ->map(fn ($alert) => [
                'id' => $alert->id,
                'type' => $alert->type,
                'priority' => $alert->priority,
                'message' => $alert->message,
                'created_at' => $alert->created_at->diffForHumans(),
                'status' => $alert->status,
            ])
            ->toArray();

        // Machine Status Breakdown
        $machineStatuses = Machine::where('team_id', $team->id)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->get();

        $this->machineStatus = $machineStatuses
            ->map(fn ($status) => [
                'status' => ucfirst($status->status),
                'count' => $status->count,
            ])
            ->toArray();

        // Activity Feed
        $this->activityFeed = \App\Models\ActivityLog::where('team_id', $team->id)
            ->with('user')
            ->latest('created_at')
            ->take(10)
            ->get()
            ->map(fn ($log) => [
                'user' => $log->user->name ?? 'System',
                'action' => $log->action,
                'description' => $log->description,
                'created_at' => $log->created_at->diffForHumans(),
            ])
            ->toArray();

        $this->isLoading = false;
    }

    public function acknowledgeAlert(int $alertId): void
    {
        $team = Auth::user()->currentTeam;
        if ($team === null) {
            return;
        }
        $alert = Alert::where('team_id', $team->id)->findOrFail($alertId);

        $alert->update([
            'status' => 'acknowledged',
            'acknowledged_at' => now(),
            'acknowledged_by' => Auth::id(),
        ]);

        $this->loadDashboardData();
        $this->dispatch('alert-updated', message: 'Alert acknowledged successfully');
    }

    public function render(): \Illuminate\View\View
    {
        return view('livewire.dashboard');
    }
}
