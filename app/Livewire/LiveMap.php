<?php

namespace App\Livewire;

use App\Models\ActivityLog;
use App\Models\Geofence;
use App\Models\Machine;
use App\Models\MachineMetric;
use App\Models\MineArea;
use App\Models\User;
use App\Services\OperationalSnapshotService;
use App\Support\CurrentUser;
use App\Traits\RealtimeUpdates;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\On;
use Livewire\Component;

class LiveMap extends Component
{
    use RealtimeUpdates;

    public float $centerLat = -28.4793; // South Africa center latitude

    /** @var array<int|string, mixed> */
    public array $activityFeed = [];

    public bool $isLoading = true;

    public float $centerLng = 24.6727; // South Africa center longitude

    public int $zoomLevel = 12;

    public string $mapStyle = 'satellite'; // 'osm' or 'satellite'

    public bool $showGeofences = true;

    public bool $showTrails = false;

    public bool $showMachines = true;

    public string $selectedStatus = '';

    public ?int $selectedMineAreaId = null;

    public function mount(): void
    {
        // Try to center on first machine location if available, else default to South Africa
        $team = CurrentUser::get()?->currentTeam;
        $firstMachine = Machine::where('team_id', $team?->id)
            ->whereNotNull('last_location_latitude')
            ->whereNotNull('last_location_longitude')
            ->first();

        if ($firstMachine) {
            $this->centerLat = (float) $firstMachine->last_location_latitude;
            $this->centerLng = (float) $firstMachine->last_location_longitude;
            $this->zoomLevel = 13;
        } else {
            // South Africa center
            $this->centerLat = -28.4793;
            $this->centerLng = 24.6727;
            $this->zoomLevel = 6;
        }

        $this->loadActivityFeed();

        // Initialize real-time updates
        $this->initializeRealtimeUpdates();
        $this->subscribeToTeamLocations();
    }

    public function loadActivityFeed(): void
    {
        $team = CurrentUser::get()?->currentTeam;
        $this->activityFeed = ActivityLog::where('team_id', $team?->id)
            ->latest('created_at')
            ->take(10)
            ->get()
            ->map(fn (ActivityLog $log): array => [
                'user' => $log->user?->name ?? 'System',
                'action' => $log->action,
                'description' => $log->description,
                'created_at' => $log->created_at->diffForHumans(),
            ])
            ->toArray();
    }

    /**
     * WebSocket delivery isn't guaranteed while disconnected -- machine
     * location/status updates broadcast during a drop are simply lost, not
     * queued for replay. When the connection recovers (dispatched by
     * livewire-realtime.js's connection monitor), re-fetch from the
     * database -- the authoritative source of truth -- instead of trusting
     * whatever the map happened to have before the drop.
     */
    #[On('realtime-reconnected')]
    public function reconcileAfterReconnect(): void
    {
        $this->dispatch('map-updated', [
            'mapStyle' => $this->mapStyle,
            'machines' => $this->showMachines ? $this->getMachines() : [],
            'geofences' => $this->showGeofences ? $this->getGeofences() : [],
        ]);
    }

    /**
     * Polled position refresh (wire:poll on the map root): pushes the
     * current DB positions to the map JS as a browser event so markers
     * move in place -- the map itself is never reloaded for a coordinate
     * change. Positions land in the DB on the 5-minute location cadence
     * (and the 15-minute full sync), so a 60s visible-only poll keeps the
     * map within a minute of the freshest data this app can honestly show.
     */
    public function refreshPositions(): void
    {
        if (! $this->showMachines) {
            return;
        }

        $this->dispatch('machines-positions-updated', machines: $this->getMachines()->toArray());
    }

    /**
     * Toggle 24-hour travelled-path overlays: the haul-road network as the
     * fleet's own GPS history reveals it (brief §9) -- real recorded
     * positions joined in reading order, nothing routed or invented.
     */
    public function toggleTrails(): void
    {
        $this->showTrails = ! $this->showTrails;
        $this->dispatch('map-updated', [
            'mapStyle' => $this->mapStyle,
            'machines' => $this->showMachines ? $this->getMachines() : [],
            'geofences' => $this->showGeofences ? $this->getGeofences() : [],
            'trails' => $this->showTrails ? $this->getTrails() : [],
        ]);
    }

    /**
     * Per-machine polylines of REAL recorded positions from the last 24
     * hours, consecutive duplicates collapsed. Machines with fewer than two
     * distinct points draw nothing -- a single reading is a dot, not a road.
     *
     * @return list<array{machine_id: int, name: string, points: list<array{lat: float, lng: float}>}>
     */
    public function getTrails(): array
    {
        $user = CurrentUser::get();
        $team = $user instanceof User ? $user->currentTeam : null;

        if ($team === null) {
            return [];
        }

        $metrics = MachineMetric::query()
            ->where('team_id', $team->id)
            ->where('recorded_at', '>=', now()->subDay())
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->orderBy('machine_id')
            ->orderBy('recorded_at')
            ->get(['machine_id', 'latitude', 'longitude']);

        $names = Machine::where('team_id', $team->id)->pluck('name', 'id');

        $trails = [];

        foreach ($metrics->groupBy('machine_id') as $machineId => $rows) {
            $points = [];

            foreach ($rows as $row) {
                $point = ['lat' => (float) $row->latitude, 'lng' => (float) $row->longitude];

                if ($points === [] || end($points) !== $point) {
                    $points[] = $point;
                }
            }

            if (count($points) >= 2) {
                $trails[] = [
                    'machine_id' => $machineId,
                    'name' => ($names[$machineId] ?? ('Machine #'.$machineId)),
                    'points' => $points,
                ];
            }
        }

        return $trails;
    }

    public function toggleGeofences(): void
    {
        $this->showGeofences = ! $this->showGeofences;
        $this->dispatch('map-updated', [
            'mapStyle' => $this->mapStyle,
            'geofences' => $this->showGeofences ? $this->getGeofences() : [],
            'machines' => $this->showMachines ? $this->getMachines() : [],
        ]);
    }

    public function toggleMachines(): void
    {
        $this->showMachines = ! $this->showMachines;
        $this->dispatch('map-updated', [
            'mapStyle' => $this->mapStyle,
            'machines' => $this->showMachines ? $this->getMachines() : [],
            'geofences' => $this->showGeofences ? $this->getGeofences() : [],
        ]);
    }

    public function changeMapStyle(string $style): void
    {
        $this->mapStyle = $style;
        $this->dispatch('map-updated', [
            'mapStyle' => $style,
            'machines' => $this->showMachines ? $this->getMachines() : [],
            'geofences' => $this->showGeofences ? $this->getGeofences() : [],
        ]);
    }

    /**
     * @return Collection<int, Machine>
     */
    public function getMachines()
    {
        $team = CurrentUser::get()?->currentTeam;

        $machinesQuery = Machine::query()
            ->where('team_id', $team?->id)
            ->whereNotNull('last_location_latitude')
            ->whereNotNull('last_location_longitude');

        if ($this->selectedStatus) {
            $machinesQuery->where('status', $this->selectedStatus);
        }

        if (($this->selectedMineAreaId !== null && $this->selectedMineAreaId !== 0)) {
            $machinesQuery->where('mine_area_id', $this->selectedMineAreaId);
        }

        /**
         * @psalm-suppress UnnecessaryVarAnnotation -- phpstan (unlike psalm) loses the Eloquent generics through whereNotNull()
         *
         * @var Collection<int, Machine> $machines
         */
        $machines = $machinesQuery->get();

        return $machines;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getMineAreas(): array
    {
        $team = CurrentUser::get()?->currentTeam;

        if ($team === null) {
            return [];
        }

        // Return active mine areas with coordinates decoded for client-side use
        return MineArea::forTeam($team->id)
            ->byStatus('active')
            ->orderBy('name')
            ->get()
            ->map(function (MineArea $area): array {
                return [
                    'id' => $area->id,
                    'name' => $area->name,
                    'coordinates' => $area->coordinates ?? [],
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  mixed  $value
     */
    public function updatedSelectedMineAreaId($value): void
    {
        // When user selects a mine area, push an update to the map with filtered machines
        $this->dispatch('map-updated', [
            'mapStyle' => $this->mapStyle,
            'machines' => $this->getMachines(),
            'geofences' => $this->showGeofences ? $this->getGeofences() : [],
            'selectedMineAreaId' => $value,
        ]);
    }

    /**
     * @return \Illuminate\Support\Collection<int, array{id: int, name: string, center_latitude: float, center_longitude: float, coordinates: array<string, mixed>}>
     */
    public function getGeofences(): \Illuminate\Support\Collection
    {
        $team = CurrentUser::get()?->currentTeam;

        if ($team === null) {
            return collect();
        }

        return Geofence::where('team_id', $team->id)
            ->get()
            ->map(function (Geofence $geofence): array {
                return [
                    'id' => $geofence->id,
                    'name' => $geofence->name,
                    'center_latitude' => $geofence->center_latitude,
                    'center_longitude' => $geofence->center_longitude,
                    'coordinates' => $geofence->coordinates,
                ];
            })
            ->values();
    }

    public function render(): View
    {
        $machines = $this->getMachines();
        $geofences = $this->getGeofences();
        $machineStatuses = Machine::where('team_id', CurrentUser::get()?->currentTeam?->id)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $user = CurrentUser::get();
        $team = $user instanceof User ? $user->currentTeam : null;
        $snapshots = $team ? app(OperationalSnapshotService::class) : null;

        return view('livewire.live-map', [
            'machines' => $machines,
            'geofences' => $geofences,
            'machineStatuses' => $machineStatuses,
            'mineAreas' => $this->getMineAreas(),
            'telemetryFreshestAt' => $team && $snapshots ? $snapshots->teamTelemetryFreshestAt($team) : null,
            'telemetryStaleAfter' => $team && $snapshots ? $snapshots->staleAfterSeconds($team->id) : 900,
        ]);
    }
}
