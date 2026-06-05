<?php

namespace App\Livewire;

use App\Models\ActivityLog;
use App\Models\Geofence;
use App\Models\Machine;
use App\Services\RoutePlanningService;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

class FleetMovementReplay extends Component
{
    public ?int $selectedMachine = null;

    /** @var array<string, mixed> */
    public array $activityFeed = [];

    /** @var array<string, mixed> */
    public array $machineActivities = [];

    public bool $showActivities = false;

    public bool $isLoading = false;

    public string $startDate = '';

    public string $endDate = '';

    public string $startTime = '00:00';

    public string $endTime = '23:59';

    // Playback controls
    public bool $isPlaying = false;

    public float $playbackSpeed = 1.0;

    public int $currentPosition = 0;

    public int $totalPositions = 0;

    public bool $autoReplay = false;

    public bool $showTrail = true;

    public bool $smoothPan = true;

    // Map settings
    public float $centerLat = -26.2041;

    public float $centerLng = 28.0473;

    public int $zoomLevel = 10;

    // Route panel
    public bool $showRoutesPanel = false;

    protected $listeners = [
        'playback-stopped' => 'handlePlaybackStopped',
        'position-updated' => 'handlePositionUpdated',
        // Allow client to emit a loadReplay event to trigger the existing method
        'loadReplay' => 'loadReplay',
    ];

    public function mount(): void
    {
        $this->isLoading = true;
        $this->startDate = now()->subDays(1)->format('Y-m-d');
        $this->endDate = now()->format('Y-m-d');
        $this->loadActivityFeed();
        $this->isLoading = false;
    }

    public function loadActivityFeed(): void
    {
        $team = Auth::user()->currentTeam;
        $teamId = $team?->id ?? 0;
        $this->activityFeed = ActivityLog::where('team_id', $teamId)
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

    public function showRecentActivities(): void
    {
        $team = Auth::user()->currentTeam;
        $teamId = $team?->id ?? 0;

        // If a machine is selected, filter activities for that machine and date range
        $query = ActivityLog::where('team_id', $teamId)->latest('created_at');

        if ($this->selectedMachine) {
            $query->where('machine_id', $this->selectedMachine);
        }

        // Apply date range if provided
        try {
            if (! empty($this->startDate) && ! empty($this->endDate)) {
                $start = Carbon::parse($this->startDate.' '.$this->startTime);
                $end = Carbon::parse($this->endDate.' '.$this->endTime);
                $query->whereBetween('created_at', [$start, $end]);
            }
        } catch (\Exception $e) {
            // ignore invalid dates
        }

        $this->machineActivities = $query->take(50)->get()->map(fn ($log) => [
            'user' => $log->user?->name ?? 'System',
            'action' => $log->action,
            'description' => $log->description,
            'created_at' => $log->created_at->format('Y-m-d H:i:s'),
        ])->toArray();

        $this->showActivities = true;
    }

    public function hideRecentActivities(): void
    {
        $this->showActivities = false;
        $this->machineActivities = [];
    }

    public function showRoutes(): void
    {
        $this->showRoutesPanel = ! $this->showRoutesPanel;
        // Trigger frontend to highlight routes, draw waypoint markers, and center
        $this->dispatch('show-routes', open: $this->showRoutesPanel);
    }

    public function render(): View
    {
        $team = Auth::user()->currentTeam;
        $teamId = $team?->id ?? 0;
        $machines = Machine::where('team_id', $teamId)
            ->orderBy('machine_type')
            ->orderBy('name')
            ->get()
            ->groupBy('machine_type');

        $locationHistory = [];
        $pathCoordinates = [];
        $geofences = [];
        $routes = [];
        $selectedMachineDetails = null;

        if ($this->selectedMachine) {
            $selectedMachineDetails = Machine::where('team_id', $teamId)->find($this->selectedMachine);
            $start = Carbon::parse($this->startDate.' '.$this->startTime);
            $end = Carbon::parse($this->endDate.' '.$this->endTime);

            // Get location history from machine_metrics table (simulating historical data)
            $locationHistory = DB::table('machine_metrics')
                ->where('machine_id', $this->selectedMachine)
                ->whereBetween('created_at', [$start, $end])
                ->whereNotNull('latitude')
                ->whereNotNull('longitude')
                ->orderBy('created_at')
                ->get();

            $this->totalPositions = $locationHistory->count();

            // Build path coordinates
            $pathCoordinates = $locationHistory->map(function ($location) {
                return [
                    'lat' => $location->latitude,
                    'lng' => $location->longitude,
                    'timestamp' => Carbon::parse($location->created_at)->format('Y-m-d H:i:s'),
                    'speed' => $location->speed ?? 0,
                    'heading' => $location->heading ?? 0,
                ];
            })->toArray();

            // Get geofences for the team
            $geofences = Geofence::where('team_id', $teamId)
                ->get()
                ->map(function ($geofence) {
                    $coordinates = $geofence->coordinates ?? [];

                    return [
                        'id' => $geofence->id,
                        'name' => $geofence->name,
                        'type' => $geofence->type,
                        'coordinates' => $coordinates,
                        'color' => $geofence->color ?? '#3b82f6',
                    ];
                })
                ->toArray();

            // Get routes with their waypoints - prioritize machine-specific routes, then team routes
            $machineRoutes = DB::table('routes')
                ->leftJoin('waypoints', 'routes.id', '=', 'waypoints.route_id')
                ->where('routes.team_id', $teamId)
                ->where('routes.status', 'active')
                ->where(function ($query) {
                    $query->where('routes.machine_id', $this->selectedMachine)
                        ->orWhereNull('routes.machine_id');
                })
                ->select('routes.id', 'routes.name', 'routes.start_latitude', 'routes.start_longitude',
                    'routes.end_latitude', 'routes.end_longitude', 'routes.total_distance',
                    'waypoints.latitude', 'waypoints.longitude', 'waypoints.sequence_order')
                ->orderBy('routes.id')
                ->orderBy('waypoints.sequence_order')
                ->get();

            // Group waypoints by route
            $routesMap = [];
            foreach ($machineRoutes as $row) {
                if (! isset($routesMap[$row->id])) {
                    $routesMap[$row->id] = [
                        'id' => $row->id,
                        'name' => $row->name,
                        'waypoints' => [],
                        'color' => '#f59e0b',
                        'start_location' => $row->start_latitude.', '.$row->start_longitude,
                        'end_location' => $row->end_latitude.', '.$row->end_longitude,
                    ];
                }
                if (! is_null($row->latitude) && ! is_null($row->longitude)) {
                    $routesMap[$row->id]['waypoints'][] = [$row->latitude, $row->longitude];
                }
            }

            $routes = array_values($routesMap);

            // Auto-calculate route between start and end points if no routes exist
            // This ensures machine movement follows roads on the map
            if (empty($routes) && ! empty($pathCoordinates) && count($pathCoordinates) >= 2) {
                $start_point = reset($pathCoordinates);
                $end_point = end($pathCoordinates);

                if ($start_point && $end_point) {
                    try {
                        $routePlanningService = new RoutePlanningService;
                        $calculatedRoute = $routePlanningService->calculateOptimalRoute(
                            $start_point['lat'],
                            $start_point['lng'],
                            $end_point['lat'],
                            $end_point['lng'],
                            $this->selectedMachine,
                            $team->id
                        );

                        if ($calculatedRoute && ! empty($calculatedRoute['waypoints'])) {
                            // Add the calculated route to the routes array
                            $routes[] = [
                                'id' => 'auto-'.time(),
                                'name' => 'Auto-calculated Route',
                                'waypoints' => $calculatedRoute['waypoints'],
                                'color' => '#f59e0b',
                                'start_location' => $start_point['lat'].', '.$start_point['lng'],
                                'end_location' => $end_point['lat'].', '.$end_point['lng'],
                            ];
                        }
                    } catch (\Exception $e) {
                        Log::warning('Failed to auto-calculate route for replay', [
                            'machine_id' => $this->selectedMachine,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }

            // Set map center to first position if available
            if ($locationHistory->isNotEmpty()) {
                $first = $locationHistory->first();
                $this->centerLat = $first->latitude;
                $this->centerLng = $first->longitude;
                $this->zoomLevel = 14; // Closer zoom for tracking
            }
        }

        return view('livewire.fleet-movement-replay', [
            'machines' => $machines,
            'locationHistory' => $locationHistory,
            'pathCoordinates' => $pathCoordinates,
            'geofences' => $geofences,
            'routes' => $routes,
            'selectedMachineDetails' => $selectedMachineDetails,
        ]);
    }

    public function setMachine($machineId): void
    {
        $this->selectedMachine = $machineId;
        $this->currentPosition = 0;
        $this->isPlaying = false;
        $this->dispatch('machine-selected');
    }

    public function loadReplay(): void
    {
        $this->currentPosition = 0;
        $this->isPlaying = false;
        $this->dispatch('replay-loaded');
    }

    public function play(): void
    {
        $this->isPlaying = true;
        $this->dispatch('replay-play', speed: $this->playbackSpeed);
    }

    public function pause(): void
    {
        $this->isPlaying = false;
        $this->dispatch('replay-pause');
    }

    public function stop(): void
    {
        $this->isPlaying = false;
        $this->currentPosition = 0;
        $this->dispatch('replay-stop');
    }

    public function updated($propertyName): void
    {
        // When currentPosition is updated via wire:model, dispatch seek event
        if ($propertyName === 'currentPosition') {
            $this->dispatch('replay-seek', position: $this->currentPosition);
        }
        if ($propertyName === 'selectedMachine') {
            // Auto-load replay when machine is selected
            $this->dispatch('machine-selected');
        }
    }

    public function setSpeed($speed): void
    {
        $this->playbackSpeed = $speed;
        if ($this->isPlaying) {
            $this->dispatch('replay-speed-change', speed: $speed);
        }
    }

    public function seekTo($position): void
    {
        $this->currentPosition = $position;
        $this->dispatch('replay-seek', position: $position);
    }

    public function handlePlaybackStopped(): void
    {
        $this->isPlaying = false;
    }

    public function handlePositionUpdated($data): void
    {
        $this->currentPosition = $data['position'] ?? 0;
    }

    public function nextFrame(): void
    {
        if ($this->currentPosition < $this->totalPositions - 1) {
            $this->currentPosition++;
            $this->dispatch('replay-seek', position: $this->currentPosition);
        }
    }

    public function previousFrame(): void
    {
        if ($this->currentPosition > 0) {
            $this->currentPosition--;
            $this->dispatch('replay-seek', position: $this->currentPosition);
        }
    }

    public function loadRecentReplay(): void
    {
        // Load the most recent replay data automatically
        $team = Auth::user()->currentTeam;
        $machine = Machine::where('team_id', $team->id)
            ->orderBy('updated_at', 'desc')
            ->first();

        if ($machine) {
            $this->selectedMachine = $machine->id;
            $this->startDate = now()->subHours(2)->format('Y-m-d');
            $this->endDate = now()->format('Y-m-d');
            $this->startTime = now()->subHours(2)->format('H:i');
            $this->endTime = now()->format('H:i');
            $this->loadReplay();

            session()->flash('message', 'Loaded recent replay for '.$machine->name);
        } else {
            session()->flash('error', 'No machines available');
        }
    }

    public function exportReplayData(): mixed
    {
        if (! $this->selectedMachine) {
            session()->flash('error', 'Please select a machine to export.');

            return null;
        }

        $team = Auth::user()->currentTeam;
        $machine = Machine::where('team_id', $team->id)->find($this->selectedMachine);

        if (! $machine) {
            session()->flash('error', 'Machine not found');

            return null;
        }

        $start = Carbon::parse($this->startDate.' '.$this->startTime);
        $end = Carbon::parse($this->endDate.' '.$this->endTime);

        $locationHistory = DB::table('machine_metrics')
            ->where('machine_id', $this->selectedMachine)
            ->whereBetween('created_at', [$start, $end])
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->orderBy('created_at')
            ->get();

        if ($locationHistory->isEmpty()) {
            session()->flash('error', 'No movement data found for the selected machine and date range.');

            return null;
        }

        $csvData = [];
        $csvData[] = ['Timestamp', 'Latitude', 'Longitude', 'Speed', 'Heading'];

        foreach ($locationHistory as $location) {
            $csvData[] = [
                Carbon::parse($location->created_at)->format('Y-m-d H:i:s'),
                $location->latitude,
                $location->longitude,
                $location->speed ?? 0,
                $location->heading ?? 0,
            ];
        }

        $filename = 'replay_'.$machine->name.'_'.now()->format('Y-m-d_His').'.csv';
        $handle = fopen('php://temp', 'r+');

        if ($handle === false) {
            return response()->streamDownload(function () {}, $filename, ['Content-Type' => 'text/csv']);
        }

        foreach ($csvData as $row) {
            fputcsv($handle, $row);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return response()->streamDownload(function () use ($csv) {
            echo $csv;
        }, $filename, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}
