<?php

namespace App\Livewire;

use App\Models\ActivityLog;
use App\Models\Geofence;
use App\Models\Machine;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class FleetMovementReplay extends Component
{
    /**
     * The selected machine's ID. Every consumer (queries, blade, JS) treats
     * this as an id; it was previously typed ?Machine, so the select's
     * wire:model threw a TypeError the moment any machine was chosen --
     * the page's core workflow was unusable.
     */
    public ?int $selectedMachine = null;

    public array $activityFeed = [];

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

    /** @var array<string, string> */
    protected $listeners = [
        'playback-stopped' => 'handlePlaybackStopped',
        'position-updated' => 'handlePositionUpdated',
        // Allow client to emit a loadReplay event to trigger the existing method
        'loadReplay' => 'loadReplay',
    ];

    public function mount()
    {
        $this->isLoading = true;
        $this->startDate = now()->subDays(1)->format('Y-m-d');
        $this->endDate = now()->format('Y-m-d');
        $this->loadActivityFeed();
        $this->isLoading = false;
    }

    public function loadActivityFeed()
    {
        $team = Auth::user()->currentTeam;
        $this->activityFeed = ActivityLog::where('team_id', $team->id)
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

    public function showRecentActivities()
    {
        $team = Auth::user()->currentTeam;

        // If a machine is selected, filter activities for that machine and date range
        $query = ActivityLog::where('team_id', $team->id)->latest('created_at');

        if ($this->selectedMachine !== null) {
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

    public function hideRecentActivities()
    {
        $this->showActivities = false;
        $this->machineActivities = [];
    }

    public function showRoutes()
    {
        // Trigger frontend to highlight routes and center
        $this->dispatch('show-routes');
    }

    public function render()
    {
        $team = Auth::user()->currentTeam;
        $machines = Machine::where('team_id', $team->id)
            ->orderBy('machine_type')
            ->orderBy('name')
            ->get()
            ->groupBy('machine_type');

        $locationHistory = [];
        $pathCoordinates = [];
        $geofences = [];
        $routes = [];
        $selectedMachineDetails = null;

        // Tenant isolation: only proceed when the selected id is a machine
        // of the CURRENT team -- the raw metrics query below is keyed by
        // machine_id, so acting on an unverified id would replay another
        // team's GPS history.
        if ($this->selectedMachine !== null) {
            $selectedMachineDetails = Machine::where('team_id', $team->id)->find($this->selectedMachine);
        }

        if ($selectedMachineDetails) {
            $start = Carbon::parse($this->startDate.' '.$this->startTime);
            $end = Carbon::parse($this->endDate.' '.$this->endTime);

            // Real GPS history from telemetry. recorded_at is the provider's
            // own reading timestamp (Bell: TelemetryDate); created_at is only
            // when the row was synced -- replaying by sync time distorts the
            // timeline whenever a batch sync lands hours of readings at once.
            $locationHistory = DB::table('machine_metrics')
                ->where('machine_id', $this->selectedMachine)
                ->whereBetween(DB::raw('COALESCE(recorded_at, created_at)'), [$start, $end])
                ->whereNotNull('latitude')
                ->whereNotNull('longitude')
                ->orderByRaw('COALESCE(recorded_at, created_at)')
                ->get();

            $this->totalPositions = $locationHistory->count();

            // Build path coordinates
            $pathCoordinates = $locationHistory->map(function ($location) {
                return [
                    'lat' => $location->latitude,
                    'lng' => $location->longitude,
                    'timestamp' => Carbon::parse((string) ($location->recorded_at ?? $location->created_at))->format('Y-m-d H:i:s'),
                    'speed' => $location->speed ?? 0,
                    'heading' => $location->heading ?? 0,
                ];
            })->toArray();

            // Get geofences for the team
            $geofences = Geofence::where('team_id', $team->id)
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
                ->where('routes.team_id', $team->id)
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

            // NOTE: this used to fall back to synthesizing an
            // "Auto-calculated Route" via an external OSRM call when no saved
            // routes existed. A replay is a record of where the machine
            // ACTUALLY went -- drawing a road-routing engine's guess over the
            // history both fabricates a path the machine may never have taken
            // and fired an external HTTP request inside render() on every
            // Livewire update (each play/pause/seek). Removed: the replay
            // shows real telemetry positions and real saved routes only.

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

    public function setMachine($machineId)
    {
        $this->selectedMachine = $machineId;
        $this->currentPosition = 0;
        $this->isPlaying = false;
        $this->dispatch('machine-selected');
    }

    public function loadReplay()
    {
        $this->currentPosition = 0;
        $this->isPlaying = false;
        $this->dispatch('replay-loaded');
    }

    public function play()
    {
        $this->isPlaying = true;
        $this->dispatch('replay-play', speed: $this->playbackSpeed);
    }

    public function pause()
    {
        $this->isPlaying = false;
        $this->dispatch('replay-pause');
    }

    public function stop()
    {
        $this->isPlaying = false;
        $this->currentPosition = 0;
        $this->dispatch('replay-stop');
    }

    public function updated($propertyName)
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

    public function setSpeed($speed)
    {
        $this->playbackSpeed = $speed;
        if ($this->isPlaying) {
            $this->dispatch('replay-speed-change', speed: $speed);
        }
    }

    public function seekTo($position)
    {
        $this->currentPosition = $position;
        $this->dispatch('replay-seek', position: $position);
    }

    public function handlePlaybackStopped()
    {
        $this->isPlaying = false;
    }

    public function handlePositionUpdated($data)
    {
        $this->currentPosition = $data['position'] ?? 0;
    }

    public function nextFrame()
    {
        if ($this->currentPosition < $this->totalPositions - 1) {
            $this->currentPosition++;
            $this->dispatch('replay-seek', position: $this->currentPosition);
        }
    }

    public function previousFrame()
    {
        if ($this->currentPosition > 0) {
            $this->currentPosition--;
            $this->dispatch('replay-seek', position: $this->currentPosition);
        }
    }

    public function loadRecentReplay()
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

    public function exportReplayData()
    {
        if ($this->selectedMachine === null) {
            session()->flash('error', 'Please select a machine to export.');

            return;
        }

        $team = Auth::user()->currentTeam;
        $machine = Machine::where('team_id', $team->id)->find($this->selectedMachine);

        if (! $machine) {
            session()->flash('error', 'Machine not found');

            return;
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

            return;
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
