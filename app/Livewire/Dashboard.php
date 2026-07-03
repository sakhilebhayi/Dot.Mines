<?php

namespace App\Livewire;

use App\Models\ActivityLog;
use App\Models\Alert;
use App\Models\Geofence;
use App\Models\Machine;
use App\Services\Integration\BellTeamInsightsService;
use App\Services\MachineKpiService;
use App\Services\MachineTelemetryService;
use App\Services\QueryCacheService;
use Illuminate\Contracts\View\View;
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

    /** @var array<string, mixed> */
    public array $bellOverview = [];

    /** Live Bell telemetry stats — available for all teams with Bell equipment. */
    public int $runningMachines = 0;

    public int $offlineMachines = 0;

    /** Average fuel level across machines with Bell telemetry (null = no data). */
    public ?float $avgFuelPercent = null;

    /** Loads completed today from Bell daily KPIs. */
    public int $loadsToday = 0;

    /** Payload moved today (tonnes) from Bell daily KPIs. */
    public float $payloadTodayTonnes = 0.0;

    /** True when at least one machine has Bell telemetry. */
    public bool $hasBellTelemetry = false;

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
        $this->activityFeed = ActivityLog::where('team_id', $team->id)
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

        if ($team->id === (int) config('integrations.bell.team_id')) {
            $this->bellOverview = app(BellTeamInsightsService::class)->getTeamOverview($team->id);
        }

        // ── Bell live telemetry stats (available for every team with Bell machines) ──
        $this->loadBellTelemetryStats($team->id);

        $this->isLoading = false;
    }

    /**
     * Load live Bell telemetry aggregates (running machines, avg fuel %,
     * today's loads/payload) for any team that has Bell equipment linked.
     * Gracefully produces zero/null values when no Bell data exists.
     */
    private function loadBellTelemetryStats(int $teamId): void
    {
        $machineIds = Machine::where('team_id', $teamId)->pluck('id')->all();

        if (empty($machineIds)) {
            return;
        }

        // Per-machine live telemetry (two-query bulk lookup).
        $telemetry = app(MachineTelemetryService::class)->forMachines($machineIds);

        $hasTelemetry = false;
        $running = 0;
        $offline = 0;
        $fuelValues = [];

        foreach ($telemetry as $data) {
            if ($data['status'] === 'offline' && $data['equipment_key'] === null) {
                continue; // No Bell equipment linked to this machine.
            }

            $hasTelemetry = true;

            if ($data['engine_running']) {
                $running++;
            }

            if ($data['status'] === 'offline') {
                $offline++;
            }

            if ($data['fuel_remaining_percent'] !== null) {
                $fuelValues[] = $data['fuel_remaining_percent'];
            }
        }

        if (! $hasTelemetry) {
            return;
        }

        $this->hasBellTelemetry = true;
        $this->runningMachines = $running;
        $this->offlineMachines = $offline;
        $this->avgFuelPercent = ! empty($fuelValues)
            ? round(array_sum($fuelValues) / count($fuelValues), 1)
            : null;

        // Today's production KPIs — aggregated from all OEM sources.
        $todayKpis = app(MachineKpiService::class)->getTodayKpis($machineIds);
        $this->loadsToday = $todayKpis['total_loads'];
        $this->payloadTodayTonnes = $todayKpis['total_payload_tonnes'];
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

    public function render(): View
    {
        return view('livewire.dashboard');
    }
}
