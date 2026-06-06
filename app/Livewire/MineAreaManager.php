<?php

namespace App\Livewire;

use App\Models\MineArea;
use App\Services\MineAreaService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\WithPagination;

class MineAreaManager extends Component
{
    use WithPagination;

    protected ?MineAreaService $service = null;

    // List properties
    public string $search = '';

    public string $statusFilter = '';

    public string $sortBy = 'created_at';

    public string $sortDirection = 'desc';

    public string $viewMode = 'list'; // list or map

    // Form properties
    public bool $showCreateModal = false;

    public bool $showEditModal = false;

    public ?int $editingMineAreaId = null;

    public string $name = '';

    public string $description = '';

    public string $location = '';

    public ?float $latitude = null;

    public ?float $longitude = null;

    public ?float $area_size_hectares = null;

    public string $status = 'active';

    public string $manager_name = '';

    public string $manager_contact = '';

    // Map properties
    /** @var array<string, mixed> */
    public ?array $boundaryCoordinates = null;

    public float $centerLat = -26.2041;

    public float $centerLng = 28.0473;

    public int $zoomLevel = 10;

    public bool $isDrawing = false;

    /** @var array<string, string|array<mixed>> */
    protected $rules = [
        'name' => 'required|string|max:255',
        'description' => 'nullable|string|max:1000',
        'location' => 'nullable|string|max:255',
        'latitude' => 'nullable|numeric|between:-90,90',
        'longitude' => 'nullable|numeric|between:-180,180',
        'area_size_hectares' => 'nullable|numeric|min:0',
        'status' => 'required|in:active,inactive,planning',
        'manager_name' => 'nullable|string|max:255',
        'manager_contact' => 'nullable|string|max:100',
        'boundaryCoordinates' => 'nullable|array',
    ];

    public function mount(): void
    {
        $this->service = app(MineAreaService::class);
    }

    private function getService(): MineAreaService
    {
        if ($this->service === null) {
            $this->service = app(MineAreaService::class);
        }

        return $this->service;
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function toggleSort(string $column): void
    {
        $allowed = ['name', 'status', 'created_at'];
        if (! in_array($column, $allowed, true)) {
            return;
        }
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

    public function closeCreateModal(): void
    {
        $this->showCreateModal = false;
        $this->resetForm();
    }

    public function openEditModal(MineArea $mineArea): void
    {
        $this->editingMineAreaId = $mineArea->id;
        $this->name = $mineArea->name;
        $this->description = $mineArea->description ?? '';
        $this->location = $mineArea->location ?? '';
        $this->latitude = $mineArea->latitude;
        $this->longitude = $mineArea->longitude;
        $this->area_size_hectares = $mineArea->area_size_hectares;
        $this->status = $mineArea->status;
        $this->manager_name = $mineArea->manager_name ?? '';
        $this->manager_contact = $mineArea->manager_contact ?? '';
        $this->showEditModal = true;
    }

    public function closeEditModal(): void
    {
        $this->showEditModal = false;
        $this->resetForm();
    }

    public function saveMineArea(): void
    {
        $this->validate();

        $team = Auth::user()->currentTeam;
        $data = [
            'name' => $this->name,
            'description' => $this->description,
            'location' => $this->location,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'area_size_hectares' => $this->area_size_hectares,
            'status' => $this->status,
            'manager_name' => $this->manager_name,
            'manager_contact' => $this->manager_contact,
        ];

        try {
            if ($this->editingMineAreaId) {
                $mineArea = $this->getService()->getById($this->editingMineAreaId, $team->id);
                if (! $mineArea) {
                    $this->dispatch('notify', ...['message' => 'Mine area not found', 'type' => 'error']);

                    return;
                }
                $this->getService()->update($mineArea, $data);
                $this->dispatch('notify', ...['message' => 'Mine area updated successfully', 'type' => 'success']);
                $this->showEditModal = false;
            } else {
                // Ensure center coordinates are provided to satisfy non-null DB columns
                $data['center_latitude'] = $this->latitude ?? null;
                $data['center_longitude'] = $this->longitude ?? null;
                $this->getService()->create($team->id, $data);
                $this->dispatch('notify', ...['message' => 'Mine area created successfully', 'type' => 'success']);
                $this->showCreateModal = false;
            }
            $this->resetForm();
            $this->resetPage();
        } catch (\Exception $e) {
            $this->dispatch('notify', ...['message' => 'Error saving mine area: '.$e->getMessage(), 'type' => 'error']);
        }
    }

    public function deleteMineArea(MineArea $mineArea): void
    {
        $team = Auth::user()->currentTeam;
        if ($mineArea->team_id !== $team->id) {
            abort(403);
        }

        try {
            $this->getService()->delete($mineArea);
            $this->dispatch('notify', ...['message' => 'Mine area deleted successfully', 'type' => 'success']);
            $this->resetPage();
        } catch (\Exception $e) {
            $this->dispatch('notify', ...['message' => 'Error deleting mine area: '.$e->getMessage(), 'type' => 'error']);
        }
    }

    protected function resetForm(): void
    {
        $this->editingMineAreaId = null;
        $this->name = '';
        $this->description = '';
        $this->location = '';
        $this->latitude = null;
        $this->longitude = null;
        $this->area_size_hectares = null;
        $this->status = 'active';
        $this->manager_name = '';
        $this->manager_contact = '';
    }

    public function switchToMapMode(): void
    {
        $this->viewMode = 'map';
        $this->showCreateModal = false;
    }

    public function switchToListMode(): void
    {
        $this->viewMode = 'list';
        // Ensure drawing state is cleared so the map/draw UI is not kept active
        $this->isDrawing = false;
        $this->boundaryCoordinates = null;
        $this->showCreateModal = false;
    }

    public function openCreateMapModal(): void
    {
        $this->resetForm();
        $this->boundaryCoordinates = null;
        $this->isDrawing = true;
        $this->switchToMapMode();
    }

    public function closeMapModal(): void
    {
        $this->isDrawing = false;
        $this->boundaryCoordinates = null;
    }

    /** @param array<mixed> $coordinates */
    public function setBoundary(array $coordinates): void
    {
        $this->boundaryCoordinates = $coordinates;
        // Calculate center and approximate area from polygon
        if (! empty($coordinates)) {
            $latitudes = array_map(fn ($coord) => $coord['lat'], $coordinates);
            $longitudes = array_map(fn ($coord) => $coord['lng'], $coordinates);

            $this->latitude = array_sum($latitudes) / count($latitudes);
            $this->longitude = array_sum($longitudes) / count($longitudes);
        }
    }

    public function clearBoundary(): void
    {
        $this->boundaryCoordinates = null;
        $this->latitude = null;
        $this->longitude = null;
    }

    public function saveMineAreaWithBoundary(): void
    {
        $this->validate();

        if (empty($this->boundaryCoordinates)) {
            $this->dispatch('notify', ...['message' => 'Please draw a boundary on the map', 'type' => 'error']);

            return;
        }

        $team = Auth::user()->currentTeam;
        $data = [
            'name' => $this->name,
            'description' => $this->description,
            'location' => $this->location,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'area_size_hectares' => $this->area_size_hectares,
            'status' => $this->status,
            'manager_name' => $this->manager_name,
            'manager_contact' => $this->manager_contact,
            'metadata' => [
                'boundary_coordinates' => $this->boundaryCoordinates,
            ],
        ];

        try {
            // Provide explicit center coordinates so DB NOT NULL columns are satisfied
            $data['center_latitude'] = $this->latitude ?? null;
            $data['center_longitude'] = $this->longitude ?? null;
            $this->getService()->create($team->id, $data);
            $this->dispatch('notify', ...['message' => 'Mine area created successfully', 'type' => 'success']);
            $this->isDrawing = false;
            $this->switchToListMode();
            $this->resetForm();
            $this->resetPage();
        } catch (\Exception $e) {
            $this->dispatch('notify', ...['message' => 'Error saving mine area: '.$e->getMessage(), 'type' => 'error']);
        }
    }

    public function render(): View
    {
        $team = Auth::user()->currentTeam;

        $query = MineArea::forTeam($team->id);

        // Build count relations conditionally based on schema
        $countRelations = [
            'geofences',
            'alerts' => function ($q) {
                $q->where('status', 'active');
            },
            'productionRecords' => function ($q) {
                $q->where('record_date', today());
            },
            'minePlanUploads' => function ($q) {
                $q->where('status', 'active');
            },
        ];

        // Only count machines if the mine_area_id column exists in machines table
        if (Schema::hasColumn('machines', 'mine_area_id')) {
            $countRelations = array_merge(['machines'], $countRelations);
        }

        $query->withCount($countRelations);

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('name', 'like', "%{$this->search}%")
                    ->orWhere('description', 'like', "%{$this->search}%")
                    ->orWhere('location', 'like', "%{$this->search}%");
            });
        }

        if ($this->statusFilter) {
            $query->where('status', $this->statusFilter);
        }

        $mineAreas = $query->orderBy($this->sortBy, $this->sortDirection)
            ->paginate(15);

        $stats = $this->getService()->getTeamStatistics($team->id);

        return view('livewire.mine-area-manager', [
            'mineAreas' => $mineAreas,
            'stats' => $stats,
            'viewMode' => $this->viewMode,
            'isDrawing' => $this->isDrawing,
        ]);
    }
}
