<?php

namespace App\Livewire;

use App\Models\HaulDispatch;
use App\Models\Machine;
use App\Models\Alert;
use App\Models\Geofence;
use App\Services\QueryCacheService;
use Livewire\Component;
use Illuminate\Support\Facades\Auth;

class Dashboard extends Component
{
    public int $totalMachines = 0;
    public int $activeMachines = 0;
    public int $activeAlerts = 0;
    public int $totalGeofences = 0;
    public array $recentAlerts = [];
    public array $machineStatus = [];
    public array $activityFeed = [];
    public bool $isLoading = true;

    // ── Haul Dispatch Tracker properties ──────────────────────────────────────
    /** @var array<int, array<string, mixed>> */
    public array $activeDispatches = [];
    /** @var array<int, array<string, mixed>> */
    public array $mapDispatches = [];
    public bool $haulDispatchLoading = true;
    public string $statusFilter = 'all';
    public ?int $selectedDispatchId = null;

    public function mount(): void
    {
        $this->loadDashboardData();
        $this->loadDispatches();
    }

    public function loadDashboardData(): void
    {
        $this->isLoading = true;
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

    // ── Haul Dispatch Tracker methods ─────────────────────────────────────────

    public function loadDispatches(): void
    {
        $this->haulDispatchLoading = true;

        $team = Auth::user()->currentTeam;

        if ($team === null) {
            $this->haulDispatchLoading = false;
            return;
        }

        $query = HaulDispatch::forTeam($team->id)
            ->active()
            ->with(['machine', 'mineArea'])
            ->latest('started_at');

        if ($this->statusFilter !== 'all') {
            $query->where('status', $this->statusFilter);
        }

        $dispatches = $query->get();

        $this->activeDispatches = $dispatches->map(function (HaulDispatch $d): array {
            $capacity        = $d->fuel_capacity_litres ?? $d->machine?->fuel_capacity ?? 0;
            $fuelLevel       = $d->current_fuel_level_litres ?? 0;
            $machineCapacity = $d->machine?->capacity ?? 0;

            return [
                'id'                        => $d->id,
                'machine_id'                => $d->machine_id,
                'machine_name'              => $d->machine?->name ?? 'Unknown',
                'machine_type'              => $d->machine?->machine_type ?? 'haul_truck',
                'status'                    => $d->status,
                'origin_name'               => $d->origin_name ?? 'Loading Point',
                'destination_name'          => $d->destination_name ?? 'Dump Point',
                'current_latitude'          => $d->current_latitude,
                'current_longitude'         => $d->current_longitude,
                'current_heading'           => $d->current_heading ?? 0,
                'current_speed_kmh'         => $d->current_speed_kmh ?? 0,
                'current_tonnage'           => $d->current_tonnage ?? 0,
                'machine_capacity'          => $machineCapacity,
                'fuel_percentage'           => $d->fuel_percentage,
                'current_fuel_level_litres' => $fuelLevel,
                'fuel_capacity_litres'      => $capacity,
                'eta_formatted'             => $d->eta_formatted,
                'started_at'                => $d->started_at?->diffForHumans() ?? 'Not started',
                'total_distance_km'         => $d->total_distance_km ?? 0,
                'distance_remaining_km'     => $d->distance_remaining_km ?? 0,
                'mine_area'                 => $d->mineArea?->name ?? '—',
                'origin_lat'                => $d->origin_latitude,
                'origin_lng'                => $d->origin_longitude,
                'dest_lat'                  => $d->destination_latitude,
                'dest_lng'                  => $d->destination_longitude,
                'path_coordinates'          => $d->path_coordinates ?? [],
            ];
        })->toArray();

        $this->mapDispatches = array_map(fn (array $d): array => [
            'id'           => $d['id'],
            'machine_name' => $d['machine_name'],
            'status'       => $d['status'],
            'current_lat'  => $d['current_latitude'],
            'current_lng'  => $d['current_longitude'],
            'heading'      => $d['current_heading'],
            'origin_lat'   => $d['origin_lat'],
            'origin_lng'   => $d['origin_lng'],
            'origin_name'  => $d['origin_name'],
            'dest_lat'     => $d['dest_lat'],
            'dest_lng'     => $d['dest_lng'],
            'dest_name'    => $d['destination_name'],
            'path'         => $d['path_coordinates'],
        ], $this->activeDispatches);

        $this->dispatch('haul-dispatch:map-data', dispatches: $this->mapDispatches);

        $this->haulDispatchLoading = false;
    }

    public function filterByStatus(string $status): void
    {
        $allowed = ['all', 'loading', 'hauling', 'dumping', 'returning', 'idle'];
        if (!in_array($status, $allowed, true)) {
            return;
        }

        $this->statusFilter = $status;
        $this->loadDispatches();
    }

    public function selectDispatch(int $id): void
    {
        $this->selectedDispatchId = $this->selectedDispatchId === $id ? null : $id;
        $this->dispatch('haul-dispatch:select', id: $this->selectedDispatchId);
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

    public function render()
    {
        return view('livewire.dashboard');
    }
}
