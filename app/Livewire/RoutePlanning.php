<?php

namespace App\Livewire;

use App\Models\Geofence;
use App\Models\Machine;
use App\Models\MineArea;
use App\Models\Route;
use App\Models\Waypoint;
use App\Services\RoutePlanningService;
use App\Support\CurrentUser;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

class RoutePlanning extends Component
{
    public string $name = '';

    public string $description = '';

    public ?int $machineId = null;

    public ?int $mineAreaId = null;

    public string $routeType = 'optimal';

    public ?float $speedLimit = null;

    // Route coordinates
    public ?float $startLat = null;

    public ?float $startLon = null;

    public ?float $endLat = null;

    public ?float $endLon = null;

    // Calculated route data
    public mixed $calculatedRoute = null;

    public mixed $savedRoute = null;

    // UI State
    public bool $showCalculatedRoute = false;

    public bool $isCalculating = false;

    public bool $routeSaved = false;

    public bool $isLoading = false;

    // Map settings
    public float $centerLat = -26.2041;

    public float $centerLng = 28.0473;

    public int $zoomLevel = 10;

    // View mode
    public string $viewMode = 'create'; // create, view

    /** @var array<int, mixed> */
    public array $routes = [];

    public ?int $selectedRouteId = null;

    /** @var array<string, string> */
    protected array $rules = [
        'machineId' => 'nullable|exists:machines,id',
        'mineAreaId' => 'nullable|exists:mine_areas,id',
        'startLat' => 'required|numeric|between:-90,90',
        'startLon' => 'required|numeric|between:-180,180',
        'endLat' => 'required|numeric|between:-90,90',
        'endLon' => 'required|numeric|between:-180,180',
        'routeType' => 'required|in:optimal,shortest,safest,custom',
        'speedLimit' => 'nullable|integer|min:1|max:200',
    ];

    public function mount(): void
    {
        $this->isLoading = true;
        $this->loadRoutes();
        $this->isLoading = false;
    }

    public function render(): View
    {
        $team = CurrentUser::team();

        $machines = Machine::where('team_id', $team->id)
            ->orderBy('name')
            ->get();

        // Fetch mine areas for the current team
        $mineAreas = MineArea::where('team_id', $team->id)
            ->orderBy('name')
            ->get();

        // Convert geofences to plain array for safe JavaScript serialization
        $geofences = Geofence::where('team_id', $team->id)
            ->get()
            ->map(function ($geofence) {
                // Ensure coordinates are in the right format
                $coordinates = $geofence->coordinates;
                // If coordinates is a string, parse it; otherwise use as-is
                if (is_string($coordinates)) {
                    $coordinates = json_decode($coordinates, true);
                }

                return [
                    'id' => $geofence->id,
                    'name' => $geofence->name,
                    'geofence_type' => $geofence->geofence_type,
                    'coordinates' => $coordinates, // Already an array thanks to cast
                ];
            })
            ->toArray();

        return view('livewire.route-planning', [
            'machines' => $machines,
            'mineAreas' => $mineAreas,
            'geofences' => $geofences,
        ]);
    }

    public function calculateRoute(): void
    {
        // Validate only the fields required for route calculation (name is not required here)
        $this->validate([
            'startLat' => 'required|numeric|between:-90,90',
            'startLon' => 'required|numeric|between:-180,180',
            'endLat' => 'required|numeric|between:-90,90',
            'endLon' => 'required|numeric|between:-180,180',
            'routeType' => 'required|in:optimal,shortest,safest,custom',
            'speedLimit' => 'nullable|integer|min:1|max:200',
            'machineId' => 'nullable|exists:machines,id',
        ]);

        if ($this->startLat === null || $this->startLon === null || $this->endLat === null || $this->endLon === null) {
            return;
        }

        $this->isCalculating = true;
        $this->routeSaved = false;

        try {
            $team = CurrentUser::team();
            $service = new RoutePlanningService;

            $this->calculatedRoute = $service->calculateOptimalRoute(
                $this->startLat,
                $this->startLon,
                $this->endLat,
                $this->endLon
            );

            $this->showCalculatedRoute = true;
            // Dispatch a browser event so frontend code can react
            $this->dispatch('routeCalculated', $this->calculatedRoute);

        } catch (\Throwable $e) {
            Log::error('Failed to calculate route', ['machine_id' => $this->machineId, 'error' => $e->getMessage()]);
            session()->flash('error', "We couldn't calculate a route between those points. Please check the start and end locations and try again.");
        } finally {
            $this->isCalculating = false;
        }
    }

    public function saveRoute(): void
    {
        if (! $this->calculatedRoute) {
            session()->flash('error', 'Please calculate a route first.');

            return;
        }

        $this->validate(['name' => 'required|min:3|max:255']);

        try {
            $team = CurrentUser::team();

            DB::beginTransaction();

            // Create the route
            $route = Route::create([
                'team_id' => $team->id,
                'machine_id' => $this->machineId,
                'mine_area_id' => $this->mineAreaId,
                'name' => $this->name,
                'description' => $this->description,
                'start_latitude' => $this->calculatedRoute['start_latitude'],
                'start_longitude' => $this->calculatedRoute['start_longitude'],
                'end_latitude' => $this->calculatedRoute['end_latitude'],
                'end_longitude' => $this->calculatedRoute['end_longitude'],
                'total_distance' => $this->calculatedRoute['total_distance'],
                'estimated_time' => $this->calculatedRoute['estimated_time'],
                'estimated_fuel' => $this->calculatedRoute['estimated_fuel'],
                'route_type' => $this->routeType,
                'speed_limit' => $this->speedLimit,
                'status' => 'active',
                'route_geometry' => $this->calculatedRoute['route_geometry'] ?? null,
            ]);

            // Create waypoints
            foreach ($this->calculatedRoute['waypoints'] as $waypointData) {
                Waypoint::create([
                    'route_id' => $route->id,
                    'sequence_order' => $waypointData['sequence_order'],
                    'latitude' => $waypointData['latitude'],
                    'longitude' => $waypointData['longitude'],
                    'waypoint_type' => $waypointData['waypoint_type'] ?? 'standard',
                    'name' => $waypointData['name'] ?? null,
                    'distance_from_previous' => $waypointData['distance_from_previous'] ?? null,
                    'estimated_time_from_previous' => $waypointData['estimated_time_from_previous'] ?? null,
                ]);
            }

            DB::commit();

            $this->savedRoute = $route;
            $this->routeSaved = true;
            $this->loadRoutes();

            session()->flash('success', 'Route saved successfully!');

            // Reset form
            $this->reset(['name', 'description', 'speedLimit', 'calculatedRoute', 'showCalculatedRoute']);

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Failed to save route', ['team_id' => $team->id ?? null, 'error' => $e->getMessage()]);
            session()->flash('error', "We couldn't save this route. Please try again.");
        }
    }

    public function loadRoutes(): void
    {
        $team = CurrentUser::team();
        $this->routes = Route::where('team_id', $team->id)
            ->with(['machine', 'mineArea', 'waypoints'])
            ->latest()
            ->get()
            ->toArray();
    }

    /**
     * @param  int|string  $routeId
     */
    public function viewRoute($routeId): void
    {
        $team = CurrentUser::team();
        $route = Route::where('team_id', $team->id)
            ->where('id', $routeId)
            ->with('waypoints')
            ->first();

        if ($route) {
            // Keep the list visible — do not switch to a hidden 'view' state.
            $this->selectedRouteId = (int) $routeId;
            // Ensure viewMode stays as 'list' so the routes list remains visible.
            $this->viewMode = 'list';

            // Prepare route data for map
            $routeData = [
                'id' => $route->id,
                'name' => $route->name,
                'start_latitude' => $route->start_latitude,
                'start_longitude' => $route->start_longitude,
                'end_latitude' => $route->end_latitude,
                'end_longitude' => $route->end_longitude,
                'total_distance' => $route->total_distance,
                'estimated_time' => $route->estimated_time,
                'estimated_fuel' => $route->estimated_fuel,
                'route_geometry' => $route->route_geometry,
                'waypoints' => $route->waypoints->map(fn ($w) => [
                    'latitude' => $w->latitude,
                    'longitude' => $w->longitude,
                    'name' => $w->name,
                    'type' => $w->waypoint_type,
                    'distance_from_previous' => $w->distance_from_previous,
                    'estimated_time_from_previous' => $w->estimated_time_from_previous,
                ])->toArray(),
            ];

            // Dispatch as browser event for frontend listeners
            $this->dispatch('viewRoute', $routeData);
        }
    }

    /**
     * @param  int|string  $routeId
     */
    public function deleteRoute($routeId): void
    {
        $team = CurrentUser::team();
        $route = Route::where('team_id', $team->id)
            ->where('id', $routeId)
            ->first();

        if ($route) {
            $route->delete();

            // Reset component state properly after delete
            $this->reset([
                'selectedRouteId',
                'calculatedRoute',
                'showCalculatedRoute',
                'savedRoute',
                'routeSaved',
                'startLat',
                'startLon',
                'endLat',
                'endLon',
            ]);

            $this->loadRoutes();

            // Clear map markers via JavaScript (dispatch browser event)
            $this->dispatch('clearMapMarkers');

            session()->flash('success', 'Route deleted successfully.');
        }
    }

    public function switchToCreateMode(): void
    {
        $this->viewMode = 'create';

        // Reset all form and state variables
        $this->reset([
            'selectedRouteId',
            'calculatedRoute',
            'showCalculatedRoute',
            'savedRoute',
            'routeSaved',
            'startLat',
            'startLon',
            'endLat',
            'endLon',
            'name',
            'description',
            'machineId',
            'mineAreaId',
            'routeType',
        ]);

        // Set default route type
        $this->routeType = 'optimal';

        // Clear map markers
        $this->dispatch('clearMapMarkers');
    }

    /**
     * @param  float|int|string  $lat
     * @param  float|int|string  $lon
     */
    public function updateStartPoint($lat, $lon): void
    {
        $this->startLat = (float) $lat;
        $this->startLon = (float) $lon;
    }

    /**
     * @param  float|int|string  $lat
     * @param  float|int|string  $lon
     */
    public function updateEndPoint($lat, $lon): void
    {
        $this->endLat = (float) $lat;
        $this->endLon = (float) $lon;
    }

    public function clearPoints(): void
    {
        $this->startLat = null;
        $this->startLon = null;
        $this->endLat = null;
        $this->endLon = null;
        $this->calculatedRoute = null;
        $this->showCalculatedRoute = false;
        $this->dispatch('clearMapMarkers');
    }
}
