<?php

namespace App\Livewire;

use App\Models\Geofence;
use App\Models\MineArea;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class GeofenceManager extends Component
{
    use WithPagination;

    public string $search = '';

    public string $sortBy = 'name';

    public string $sortDirection = 'asc';

    public bool $showCreateModal = false;

    // Form properties
    public ?int $editingGeofenceId = null;

    public ?int $teamId = null;

    public ?int $mineAreaId = null;

    public string $name = '';

    public string $description = '';

    public string $type = 'pit';

    public float $centerLatitude = 0;

    public float $centerLongitude = 0;

    public array $coordinates = [];

    /** @var array<string, string> */
    protected $listeners = ['geofenceCreated' => 'geofenceCreated'];

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function mount(): void
    {
        $this->teamId = Auth::user()->currentTeam->id ?? null;
    }

    public function toggleSort(string $column): void
    {
        if ($this->sortBy === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDirection = 'asc';
        }
    }

    public function openCreateModal(): void
    {
        $this->resetForm();
        $this->showCreateModal = true;
    }

    public function closeModal(): void
    {
        $this->showCreateModal = false;
        $this->resetForm();
    }

    public function resetForm(): void
    {
        $this->editingGeofenceId = null;
        $this->teamId = Auth::user()->currentTeam->id ?? null;
        $this->mineAreaId = null;
        $this->name = '';
        $this->description = '';
        $this->type = 'pit';
        $this->centerLatitude = 0;
        $this->centerLongitude = 0;
        $this->coordinates = [];
    }

    public function editGeofence(Geofence $geofence): void
    {
        $this->editingGeofenceId = $geofence->id;
        $this->teamId = auth()->user()->current_team_id;
        $this->mineAreaId = $geofence->mine_area_id;
        $this->name = $geofence->name;
        $this->description = $geofence->description ?? '';
        $this->type = $geofence->type;
        $this->centerLatitude = (float) $geofence->center_latitude;
        $this->centerLongitude = (float) $geofence->center_longitude;
        $this->coordinates = is_string($geofence->coordinates) ? json_decode($geofence->coordinates, true) : $geofence->coordinates ?? [];
        $this->showCreateModal = true;
    }

    public function saveGeofence(): void
    {
        $this->validate([
            'mineAreaId' => 'required|exists:mine_areas,id',
            'teamId' => 'required|exists:teams,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'type' => 'required|in:pit,stockpile,dump,facility',
            'centerLatitude' => 'required|numeric',
            'centerLongitude' => 'required|numeric',
        ]);

        $team = Auth::user()->currentTeam;

        $data = [
            'team_id' => $this->teamId,
            'mine_area_id' => $this->mineAreaId,
            'name' => $this->name,
            'description' => $this->description ?: null,
            'type' => $this->type,
            'center_latitude' => $this->centerLatitude,
            'center_longitude' => $this->centerLongitude,
            'coordinates' => json_encode($this->coordinates),
        ];

        if ($this->editingGeofenceId) {
            $geofence = Geofence::where('team_id', $team->id)->findOrFail($this->editingGeofenceId);
            $this->authorize('update', $geofence);
            $geofence->update($data);
            $this->dispatch('notify', ['message' => 'Geofence updated successfully', 'type' => 'success']);
        } else {
            $this->authorize('create', Geofence::class);
            $data['team_id'] = $team->id;
            Geofence::create($data);
            $this->dispatch('notify', ['message' => 'Geofence created successfully', 'type' => 'success']);
        }

        $this->closeModal();
    }

    public function deleteGeofence(Geofence $geofence): void
    {
        $this->authorize('delete', $geofence);

        $geofenceName = $geofence->name;
        $geofence->delete();
        $this->dispatch('notify', ['message' => "Geofence '{$geofenceName}' deleted successfully", 'type' => 'success']);
    }

    public function render()
    {
        $team = Auth::user()->currentTeam;

        $geofencesQuery = Geofence::where('team_id', $team->id)
            ->when($this->search, function ($query) {
                return $query->where('name', 'like', "%{$this->search}%")
                    ->orWhere('description', 'like', "%{$this->search}%");
            })
            ->orderBy($this->sortBy, $this->sortDirection)
            ->paginate(10);

        // Get entry/exit counts for each geofence
        $geofenceStats = [];
        foreach ($geofencesQuery as $geofence) {
            $geofenceStats[$geofence->id] = [
                'entries' => $geofence->entries()->count(),
                'machines' => $geofence->entries()
                    ->select('machine_id')
                    ->distinct()
                    ->count(),
            ];
        }

        // Get all mine areas for the team
        $mineAreas = MineArea::where('team_id', $team->id)
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        // AI-powered recommendations (placeholder for future AI integration)
        $aiRecommendations = collect([]);
        $aiInsights = collect([]);

        return view('livewire.geofence-manager', [
            'geofences' => $geofencesQuery,
            'geofenceStats' => $geofenceStats,
            'mineAreas' => $mineAreas,
            'aiRecommendations' => $aiRecommendations,
            'aiInsights' => $aiInsights,
        ]);
    }
}
