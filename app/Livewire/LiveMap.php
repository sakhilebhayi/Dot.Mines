<?php

namespace App\Livewire;

use App\Models\Geofence;
use App\Models\Machine;
use App\Models\MapEvent;
use App\Models\Route;
use App\Traits\RealtimeUpdates;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class LiveMap extends Component
{
    use RealtimeUpdates;

    public float $centerLat = -28.4793; // South Africa center latitude

    public array $activityFeed = [];

    public bool $isLoading = true;

    public float $centerLng = 24.6727; // South Africa center longitude

    public int $zoomLevel = 12;

    public string $mapStyle = 'satellite'; // 'osm' or 'satellite'

    public bool $showGeofences = true;

    public bool $showMachines = true;

    public bool $showRoutes = false;

    public bool $showTMP = false;

    public bool $showHeatMap = false;

    public bool $showEvents = true;

    public string $eventTypeFilter = 'all';

    public string $selectedStatus = '';

    public ?int $selectedMineAreaId = null;

    public function mount(): void
    {
        // Try to center on first machine location if available, else default to South Africa
        $team = Auth::user()->currentTeam;

        if ($team === null) {
            $this->isLoading = false;

            return;
        }

        $firstMachine = Machine::where('team_id', $team->id)
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

    public function loadActivityFeed()
    {
        $team = Auth::user()->currentTeam;

        if ($team === null) {
            $this->activityFeed = [];

            return;
        }

        $this->activityFeed = \App\Models\ActivityLog::where('team_id', $team->id)
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
    }

    public function toggleGeofences(): void
    {
        $this->showGeofences = ! $this->showGeofences;
        $this->dispatch('map-updated', [
            'mapStyle' => $this->mapStyle,
            'geofences' => $this->showGeofences ? $this->getGeofences() : [],
            'machines' => $this->showMachines ? $this->getMachines() : [],
            'routes' => $this->showRoutes ? $this->getRoutes() : [],
        ]);
    }

    public function toggleMachines(): void
    {
        $this->showMachines = ! $this->showMachines;
        $this->dispatch('map-updated', [
            'mapStyle' => $this->mapStyle,
            'machines' => $this->showMachines ? $this->getMachines() : [],
            'geofences' => $this->showGeofences ? $this->getGeofences() : [],
            'routes' => $this->showRoutes ? $this->getRoutes() : [],
        ]);
    }

    public function toggleRoutes(): void
    {
        $this->showRoutes = ! $this->showRoutes;
        $this->dispatch('map-updated', [
            'mapStyle' => $this->mapStyle,
            'machines' => $this->showMachines ? $this->getMachines() : [],
            'geofences' => $this->showGeofences ? $this->getGeofences() : [],
            'routes' => $this->showRoutes ? $this->getRoutes() : [],
        ]);
    }

    public function toggleTMP(): void
    {
        $this->showTMP = ! $this->showTMP;
        $this->dispatch('tmp-layer-toggle', [
            'show' => $this->showTMP,
            'routes' => $this->getRoutes(),
            'geofences' => $this->getGeofencesWithType(),
        ]);
    }

    // ─── Map Events layer ─────────────────────────────────────────────────────

    public function toggleEvents(): void
    {
        $this->showEvents = ! $this->showEvents;
        $this->dispatch('events-layer-toggle', [
            'show' => $this->showEvents,
            'events' => $this->showEvents ? $this->getMapEvents() : [],
        ]);
    }

    public function filterEventType(string $type): void
    {
        $allowed = array_merge(['all'], array_keys(MapEvent::TYPE_CONFIG));
        if (! in_array($type, $allowed, true)) {
            return;
        }
        $this->eventTypeFilter = $type;
        if ($this->showEvents) {
            $this->dispatch('events-layer-toggle', [
                'show' => true,
                'events' => $this->getMapEvents(),
            ]);
        }
    }

    /**
     * Return the last 12 hours of geo-located map events for the current team.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getMapEvents(): array
    {
        $team = Auth::user()->currentTeam;

        $query = MapEvent::forTeam($team->id)
            ->withLocation()
            ->recent(12)
            ->with(['machine', 'mineArea'])
            ->latest('occurred_at')
            ->limit(200);

        if ($this->eventTypeFilter !== 'all') {
            $query->ofType($this->eventTypeFilter);
        }

        return $query->get()->map(function (MapEvent $e): array {
            $cfg = MapEvent::TYPE_CONFIG[$e->event_type]
                ?? ['label' => 'Event', 'color' => '#94a3b8', 'emoji' => '📍'];

            return [
                'id' => $e->id,
                'event_type' => $e->event_type,
                'type_label' => $cfg['label'],
                'color' => $cfg['color'],
                'emoji' => $cfg['emoji'],
                'title' => $e->title,
                'notes' => $e->notes,
                'latitude' => $e->latitude,
                'longitude' => $e->longitude,
                'occurred_at' => $e->occurred_at->toIso8601String(),
                'occurred_human' => $e->occurred_at->diffForHumans(),
                'machine_id' => $e->machine_id,
                'machine_name' => $e->machine?->name ?? '—',
                'mine_area' => $e->mineArea?->name ?? '—',
                'metadata' => $e->metadata ?? [],
            ];
        })->toArray();
    }

    // ─── Heat Map layer ───────────────────────────────────────────────────────

    public function toggleHeatMap(): void
    {
        $this->showHeatMap = ! $this->showHeatMap;
        $this->dispatch('heatmap-toggle', [
            'show' => $this->showHeatMap,
            'points' => $this->showHeatMap ? $this->getHeatMapPoints() : [],
        ]);
    }

    /**
     * Build a weighted point cloud for Leaflet.heat.
     *
     * Each entry is [lat, lng, intensity (0.0–1.0)].
     * Sources:
     *   - Machine last-known positions (weight ∝ activity level)
     *   - Geofence centres (weight ∝ zone type: dump/loading > pit > stockpile)
     *
     * @return array<int, array{float, float, float}>
     */
    public function getHeatMapPoints(): array
    {
        $team = Auth::user()->currentTeam;
        $points = [];

        // ── Machine positions ──────────────────────────────────────────────
        $statusWeights = [
            'active' => 1.0,
            'idle' => 0.4,
            'maintenance' => 0.2,
            'offline' => 0.1,
        ];

        Machine::where('team_id', $team->id)
            ->whereNotNull('last_location_latitude')
            ->whereNotNull('last_location_longitude')
            ->get(['status', 'last_location_latitude', 'last_location_longitude'])
            ->each(function ($m) use (&$points, $statusWeights): void {
                $weight = $statusWeights[$m->status] ?? 0.3;
                $points[] = [
                    (float) $m->last_location_latitude,
                    (float) $m->last_location_longitude,
                    $weight,
                ];
            });

        // ── Geofence centres ───────────────────────────────────────────────
        $geofenceWeights = [
            'dump' => 0.9,
            'loading' => 0.85,
            'pit' => 0.7,
            'stockpile' => 0.6,
            'facility' => 0.45,
            'restricted' => 0.3,
            'safe' => 0.2,
        ];

        Geofence::where('team_id', $team->id)
            ->whereNotNull('center_latitude')
            ->whereNotNull('center_longitude')
            ->get(['type', 'center_latitude', 'center_longitude'])
            ->each(function ($g) use (&$points, $geofenceWeights): void {
                $weight = $geofenceWeights[$g->type] ?? 0.4;
                $points[] = [
                    (float) $g->center_latitude,
                    (float) $g->center_longitude,
                    $weight,
                ];
            });

        return $points;
    }

    public function changeMapStyle(string $style): void
    {
        $this->mapStyle = $style;
        $this->dispatch('map-updated', [
            'mapStyle' => $style,
            'machines' => $this->showMachines ? $this->getMachines() : [],
            'geofences' => $this->showGeofences ? $this->getGeofences() : [],
            'routes' => $this->showRoutes ? $this->getRoutes() : [],
        ]);
    }

    public function getMachines()
    {
        $team = Auth::user()->currentTeam;

        $machinesQuery = Machine::where('team_id', $team->id)
            ->whereNotNull('last_location_latitude')
            ->whereNotNull('last_location_longitude');

        if ($this->selectedStatus) {
            $machinesQuery->where('status', $this->selectedStatus);
        }

        if ($this->selectedMineAreaId) {
            $machinesQuery->where('mine_area_id', $this->selectedMineAreaId);
        }

        return $machinesQuery->get();
    }

    public function getMineAreas()
    {
        $team = Auth::user()->currentTeam;

        // Return active mine areas with coordinates decoded for client-side use
        return \App\Models\MineArea::forTeam($team->id)
            ->byStatus('active')
            ->orderBy('name')
            ->get()
            ->map(function ($area) {
                return [
                    'id' => $area->id,
                    'name' => $area->name,
                    'coordinates' => is_string($area->coordinates) ? json_decode($area->coordinates, true) : $area->coordinates ?? [],
                ];
            })
            ->toArray();
    }

    public function updatedSelectedMineAreaId($value): void
    {
        // When user selects a mine area, push an update to the map with filtered machines
        $this->dispatch('map-updated', [
            'mapStyle' => $this->mapStyle,
            'machines' => $this->getMachines(),
            'geofences' => $this->showGeofences ? $this->getGeofences() : [],
            'routes' => $this->showRoutes ? $this->getRoutes() : [],
            'selectedMineAreaId' => $value,
        ]);
    }

    public function getGeofences()
    {
        $team = Auth::user()->currentTeam;

        return Geofence::where('team_id', $team->id)
            ->get()
            ->map(function ($geofence) {
                return [
                    'id' => $geofence->id,
                    'name' => $geofence->name,
                    'geofence_type' => $geofence->geofence_type ?? 'warning',
                    'center_latitude' => (float) $geofence->center_latitude,
                    'center_longitude' => (float) $geofence->center_longitude,
                    'coordinates' => is_string($geofence->coordinates)
                        ? json_decode($geofence->coordinates, true)
                        : $geofence->coordinates ?? [],
                ];
            });
    }

    public function getGeofencesWithType(): array
    {
        return $this->getGeofences()->toArray();
    }

    public function getRoutes(): array
    {
        $team = Auth::user()->currentTeam;

        return Route::where('team_id', $team->id)
            ->where('status', 'active')
            ->with(['waypoints' => fn ($q) => $q->orderBy('sequence_order')])
            ->get()
            ->map(fn ($route) => [
                'id' => $route->id,
                'name' => $route->name,
                'start_latitude' => (float) $route->start_latitude,
                'start_longitude' => (float) $route->start_longitude,
                'end_latitude' => (float) $route->end_latitude,
                'end_longitude' => (float) $route->end_longitude,
                'total_distance' => (float) $route->total_distance,
                'estimated_time' => (int) $route->estimated_time,
                'route_geometry' => $route->route_geometry,
                'waypoints' => $route->waypoints->map(fn ($w) => [
                    'sequence_order' => $w->sequence_order,
                    'latitude' => (float) $w->latitude,
                    'longitude' => (float) $w->longitude,
                    'waypoint_type' => $w->waypoint_type,
                    'name' => $w->name,
                    'distance_from_previous' => $w->distance_from_previous,
                    'estimated_time_from_previous' => $w->estimated_time_from_previous,
                ])->toArray(),
            ])
            ->toArray();
    }

    public function getTrafficPlanData(): array
    {
        $teamId = Auth::user()->currentTeam->id;

        $restrictedZones = Geofence::where('team_id', $teamId)
            ->where('geofence_type', 'restricted')
            ->count();

        $safeZones = Geofence::where('team_id', $teamId)
            ->where('geofence_type', 'safe')
            ->count();

        $warningZones = Geofence::where('team_id', $teamId)
            ->whereNotIn('geofence_type', ['restricted', 'safe'])
            ->count();

        $activeRoutesQuery = Route::where('team_id', $teamId)->where('status', 'active');
        $activeRoutes = $activeRoutesQuery->count();

        $routesWithSpeedLimit = (clone $activeRoutesQuery)
            ->whereNotNull('speed_limit')
            ->count();

        return [
            'restricted_zones' => $restrictedZones,
            'safe_zones' => $safeZones,
            'warning_zones' => $warningZones,
            'active_routes' => $activeRoutes,
            'routes_with_speed_limit' => $routesWithSpeedLimit,
            'default_speed_limits' => [
                'haul_road' => 40,
                'loading_zone' => 20,
                'shared_zone' => 15,
            ],
            'rules' => [
                'avoid_restricted' => true,
                'one_way_flow' => true,
                'pedestrian_priority_shared_zones' => true,
            ],
        ];
    }

    public function render(): \Illuminate\View\View
    {
        $machines = $this->getMachines();
        $geofences = $this->getGeofences();
        $routes = $this->showRoutes ? $this->getRoutes() : [];
        $machineStatuses = Machine::where('team_id', Auth::user()->currentTeam->id)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        return view('livewire.live-map', [
            'machines' => $machines,
            'geofences' => $geofences,
            'routes' => $routes,
            'showRoutes' => $this->showRoutes,
            'showTMP' => $this->showTMP,
            'showHeatMap' => $this->showHeatMap,
            'showEvents' => $this->showEvents,
            'eventTypeFilter' => $this->eventTypeFilter,
            'mapEvents' => $this->showEvents ? $this->getMapEvents() : [],
            'eventTypeConfig' => MapEvent::TYPE_CONFIG,
            'tmpRoutes' => $this->getRoutes(),
            'trafficPlanData' => $this->getTrafficPlanData(),
            'machineStatuses' => $machineStatuses,
            'mineAreas' => $this->getMineAreas(),
        ]);
    }
}
