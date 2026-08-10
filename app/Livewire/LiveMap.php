<?php

namespace App\Livewire;

use App\Models\ActivityLog;
use App\Models\Geofence;
use App\Models\Machine;
use App\Models\MineArea;
use App\Traits\RealtimeUpdates;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
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

    public string $selectedStatus = '';

    public ?int $selectedMineAreaId = null;

    public function mount(): void
    {
        // Try to center on first machine location if available, else default to South Africa
        $team = Auth::user()->currentTeam;
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
        return MineArea::forTeam($team->id)
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
                    'center_latitude' => (float) $geofence->center_latitude,
                    'center_longitude' => (float) $geofence->center_longitude,
                    'coordinates' => is_string($geofence->coordinates) ? json_decode($geofence->coordinates, true) : $geofence->coordinates ?? [],
                ];
            });
    }

    public function render()
    {
        $machines = $this->getMachines();
        $geofences = $this->getGeofences();
        $machineStatuses = Machine::where('team_id', Auth::user()->currentTeam->id)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        return view('livewire.live-map', [
            'machines' => $machines,
            'geofences' => $geofences,
            'machineStatuses' => $machineStatuses,
            'mineAreas' => $this->getMineAreas(),
        ]);
    }
}
