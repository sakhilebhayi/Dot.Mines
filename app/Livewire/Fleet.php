<?php

namespace App\Livewire;

use App\Models\ActivityLog;
use App\Models\AiRecommendationAction;
use App\Models\Machine;
use App\Models\MineArea;
use App\Services\AI\FleetOptimizerAgent;
use App\Services\MachinePerformanceService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class Fleet extends Component
{
    public array $activityFeed = [];

    public bool $isLoading = true;

    use WithPagination;

    // AI recommendation interaction state
    public array $lastAiRecommendations = [];

    public ?int $pendingRecommendationIndex = null;

    public bool $showRejectRecommendationModal = false;

    public string $rejectReason = '';

    public string $search = '';

    public string $statusFilter = '';

    public string $sortBy = 'name';

    public string $sortDirection = 'asc';

    public bool $showCreateModal = false;

    public bool $showAssignModal = false;

    public ?int $assigningMachineId = null;

    public ?int $selectedExcavatorId = null;

    public array $selectedAdtIds = [];

    public string $assignMode = 'assign_to_excavator';

    public bool $showMineAreaAssignModal = false;

    public ?int $assigningMineAreaMachineId = null;

    public ?int $selectedMineAreaId = null;

    // Create/Edit form properties
    public ?int $editingMachineId = null;

    public string $name = '';

    public string $model = '';

    public string $manufacturer = '';

    public string $machineType = '';

    public string $status = 'active';

    public string $serialNumber = '';

    public float $capacity = 0;

    public float $latitude = 0;

    public float $longitude = 0;

    /** @var array<string, string> */
    protected $listeners = ['machineCreated' => 'machineCreated', 'machineDeleted' => 'machineDeleted'];

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
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
        $this->editingMachineId = null;
        $this->name = '';
        $this->model = '';
        $this->manufacturer = '';
        $this->machineType = '';
        $this->status = 'active';
        $this->serialNumber = '';
        $this->capacity = 0;
        $this->latitude = 0;
        $this->longitude = 0;
    }

    public function editMachine(Machine $machine): void
    {
        $this->editingMachineId = $machine->id;
        $this->name = $machine->name;
        $this->model = $machine->model;
        $this->manufacturer = $machine->manufacturer ?? '';
        $this->machineType = $machine->machine_type;
        $this->status = $machine->status;
        $this->serialNumber = $machine->serial_number;
        $this->capacity = $machine->capacity ?? 0;
        $this->latitude = $machine->latitude ?? 0;
        $this->longitude = $machine->longitude ?? 0;
        $this->showCreateModal = true;
    }

    public function saveMachine(): void
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'model' => 'required|string|max:255',
            'manufacturer' => 'nullable|string|max:255',
            'machineType' => 'required|string|max:255',
            'status' => 'required|in:active,idle,maintenance',
            'serialNumber' => 'nullable|string|max:255',
            'capacity' => 'nullable|numeric|min:0',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
        ]);

        $team = Auth::user()->currentTeam;

        if ($this->editingMachineId) {
            $machine = Machine::where('team_id', $team->id)->findOrFail($this->editingMachineId);
            $this->authorize('update', $machine);

            $machine->update([
                'name' => $this->name,
                'model' => $this->model,
                'manufacturer' => $this->manufacturer ?: null,
                'machine_type' => $this->machineType,
                'status' => $this->status,
                'serial_number' => $this->serialNumber,
                'capacity' => $this->capacity ?: null,
                'latitude' => $this->latitude ?: null,
                'longitude' => $this->longitude ?: null,
            ]);
            $this->dispatch('notify', ['message' => 'Machine updated successfully', 'type' => 'success']);
        } else {
            $this->authorize('create', Machine::class);

            Machine::create([
                'team_id' => $team->id,
                'name' => $this->name,
                'model' => $this->model,
                'manufacturer' => $this->manufacturer ?: null,
                'machine_type' => $this->machineType,
                'status' => $this->status,
                'serial_number' => $this->serialNumber,
                'capacity' => $this->capacity ?: null,
                'latitude' => $this->latitude ?: null,
                'longitude' => $this->longitude ?: null,
            ]);
            $this->dispatch('notify', ['message' => 'Machine created successfully', 'type' => 'success']);
        }

        $this->closeModal();
    }

    public function deleteMachine(Machine $machine): void
    {
        $this->authorize('delete', $machine);

        $machineName = $machine->name;
        $machine->delete();
        $this->dispatch('notify', ['message' => "Machine '{$machineName}' deleted successfully", 'type' => 'success']);
    }

    public function openAssignModal(int $machineId): void
    {
        $this->assigningMachineId = $machineId;
        $this->selectedExcavatorId = null;
        $this->selectedAdtIds = [];
        $this->assignMode = 'assign_to_excavator';
        $team = Auth::user()->currentTeam;
        $machine = Machine::where('team_id', $team->id)->find($machineId);
        if (! $machine) {
            $this->dispatch('notify', ['message' => 'Machine not found', 'type' => 'error']);

            return;
        }

        // If the selected machine is an excavator-like machine, open modal to assign ADTs to it
        if (in_array($machine->machine_type, ['excavator', 'digger', 'loader'])) {
            $this->assignMode = 'assign_adts_to_excavator';
            // Pre-select ADTs currently assigned to this excavator
            $this->selectedAdtIds = Machine::where('team_id', $machine->team_id)
                ->where('excavator_id', $machine->id)
                ->where('machine_type', 'adt')
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->toArray();
        } else {
            // For ADTs and other machines, allow selecting a single excavator
            if ($machine && $machine->excavator_id) {
                $this->selectedExcavatorId = $machine->excavator_id;
            }
            $this->assignMode = 'assign_to_excavator';
        }

        $this->showAssignModal = true;
    }

    public function closeAssignModal(): void
    {
        $this->showAssignModal = false;
        $this->assigningMachineId = null;
        $this->selectedExcavatorId = null;
        $this->selectedAdtIds = [];
        $this->assignMode = 'assign_to_excavator';
    }

    public function assignToExcavator(): void
    {
        // If in ADT assignment mode, route to assignAdtsToExcavator
        if ($this->assignMode === 'assign_adts_to_excavator') {
            $this->assignAdtsToExcavator();

            return;
        }

        if (! $this->assigningMachineId || ! $this->selectedExcavatorId) {
            $this->dispatch('notify', ['message' => 'Please select an excavator', 'type' => 'error']);

            return;
        }

        $team = Auth::user()->currentTeam;
        $machine = Machine::where('team_id', $team->id)->find($this->assigningMachineId);
        $excavator = Machine::where('team_id', $team->id)->find($this->selectedExcavatorId);

        if (! $machine || ! $excavator) {
            abort(403);
        }

        $this->authorize('update', $machine);
        $this->authorize('update', $excavator);

        // Prevent assigning a machine to itself
        if ($machine->id === $excavator->id) {
            $this->dispatch('notify', ['message' => 'Cannot assign a machine to itself', 'type' => 'error']);

            return;
        }

        // Prevent assigning big machines (excavator/dozer/loader/etc.) to another big machine
        $bigTypes = ['excavator', 'dozer', 'loader', 'grader', 'bulldozer'];
        if (in_array($machine->machine_type, $bigTypes) && in_array($excavator->machine_type, $bigTypes)) {
            $this->dispatch('notify', ['message' => 'Cannot assign an excavator or big machine to another big machine', 'type' => 'error']);

            return;
        }

        // Assign
        $machine->assignToExcavator($this->selectedExcavatorId);
        $this->dispatch('notify', ['message' => "Machine '{$machine->name}' assigned to '{$excavator->name}'", 'type' => 'success']);
        $this->closeAssignModal();
    }

    public function assignAdtsToExcavator(): void
    {
        if (! $this->assigningMachineId) {
            $this->dispatch('notify', ['message' => 'Excavator not specified', 'type' => 'error']);

            return;
        }

        $team = Auth::user()->currentTeam;
        $excavator = Machine::where('team_id', $team->id)->find($this->assigningMachineId);
        if (! $excavator) {
            abort(403);
        }
        $this->authorize('update', $excavator);

        // Ensure selected ADTs belong to team and are ADTs
        $validAdts = Machine::where('team_id', $excavator->team_id)
            ->whereIn('id', $this->selectedAdtIds)
            ->where('machine_type', 'adt')
            ->pluck('id')
            ->toArray();

        // First unassign ADTs previously assigned to this excavator but not selected
        Machine::where('team_id', $excavator->team_id)
            ->where('machine_type', 'adt')
            ->where('excavator_id', $excavator->id)
            ->whereNotIn('id', $validAdts)
            ->update(['excavator_id' => null, 'assigned_to_excavator_at' => null]);

        // Assign selected ADTs
        Machine::whereIn('id', $validAdts)->update(['excavator_id' => $excavator->id, 'assigned_to_excavator_at' => now()]);

        $this->dispatch('notify', ['message' => 'Assigned ADTs updated successfully', 'type' => 'success']);
        $this->closeAssignModal();
    }

    public function unassignFromExcavator(int $machineId): void
    {
        $team = Auth::user()->currentTeam;
        $machine = Machine::where('team_id', $team->id)->find($machineId);

        if (! $machine) {
            abort(403);
        }
        $this->authorize('update', $machine);

        $machineName = $machine->name;
        $machine->unassignFromExcavator();

        $this->dispatch('notify', ['message' => "Machine '{$machineName}' unassigned from excavator", 'type' => 'success']);
    }

    public function openMineAreaAssignModal(int $machineId): void
    {
        $this->assigningMineAreaMachineId = $machineId;
        $this->selectedMineAreaId = null;
        $this->showMineAreaAssignModal = true;
    }

    public function closeMineAreaAssignModal(): void
    {
        $this->showMineAreaAssignModal = false;
        $this->assigningMineAreaMachineId = null;
        $this->selectedMineAreaId = null;
    }

    public function assignToMineArea(): void
    {
        if (! $this->assigningMineAreaMachineId || ! $this->selectedMineAreaId) {
            $this->dispatch('notify', ['message' => 'Please select a mine area', 'type' => 'error']);

            return;
        }

        $team = Auth::user()->currentTeam;
        $machine = Machine::where('team_id', $team->id)->find($this->assigningMineAreaMachineId);

        if (! $machine) {
            abort(403);
        }
        $this->authorize('update', $machine);

        $mineArea = MineArea::where('team_id', $team->id)->find($this->selectedMineAreaId);
        if (! $mineArea) {
            abort(403);
        }

        // Update machine's mine_area_id field
        $machine->update(['mine_area_id' => $this->selectedMineAreaId]);

        $this->dispatch('notify', ['message' => "Machine '{$machine->name}' assigned to '{$mineArea->name}'", 'type' => 'success']);

        $this->closeMineAreaAssignModal();
    }

    // calculateMachinePerformance() used to live here: it averaged columns
    // no integration ever writes (total_hours, fuel_consumption_rate,
    // payload_capacity_used) and fell back to a hardcoded neutral 50 for
    // fuel efficiency, so every machine scored an identical, meaningless
    // "15%". MachinePerformanceService now derives real daily metrics from
    // the telemetry and production data the sync actually stores.

    public function render()
    {
        $this->isLoading = true;
        $team = Auth::user()->currentTeam;

        $machinesQuery = Machine::where('team_id', $team->id)
            ->with(['excavator', 'latestEngineHoursMetric'])
            ->when($this->search, function ($query) {
                return $query->where('name', 'like', "%{$this->search}%")
                    ->orWhere('model', 'like', "%{$this->search}%")
                    ->orWhere('manufacturer', 'like', "%{$this->search}%");
            })
            ->when($this->statusFilter, function ($query) {
                return $query->where('status', $this->statusFilter);
            })
            ->orderBy($this->sortBy, $this->sortDirection)
            ->paginate(10);

        // Get all excavators for assignment dropdown
        $excavators = Machine::where('team_id', $team->id)
            ->whereIn('machine_type', ['excavator', 'digger', 'loader'])
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        // Get all ADTs for potential assignment to excavators
        $adts = Machine::where('team_id', $team->id)
            ->where('machine_type', 'adt')
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        // Get all mine areas for assignment dropdown
        $mineAreas = MineArea::where('team_id', $team->id)
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        $statusStats = [
            'active' => Machine::where('team_id', $team->id)->where('status', 'active')->count(),
            'idle' => Machine::where('team_id', $team->id)->where('status', 'idle')->count(),
            'maintenance' => Machine::where('team_id', $team->id)->where('status', 'maintenance')->count(),
        ];

        // Real daily performance from stored telemetry + production data,
        // ranked by today's utilisation. Machines whose telemetry can't
        // support a utilisation figure yet are counted separately rather
        // than ranked on invented numbers.
        $performanceData = collect(app(MachinePerformanceService::class)->dailyPerformanceForTeam($team->id));
        $rankable = $performanceData->filter(fn (array $machine) => $machine['utilisation_today'] !== null);
        $topPerformers = $rankable->sortByDesc('utilisation_today')->take(5)->values();
        $worstPerformers = $rankable->sortBy('utilisation_today')->take(5)->values();
        $unrankedMachines = $performanceData->count() - $rankable->count();

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

        // AI Fleet Optimization Analysis
        $aiAgent = app(FleetOptimizerAgent::class);
        $aiAnalysis = $aiAgent->analyze($team);
        $aiRecommendations = collect($aiAnalysis['recommendations'])->take(5);
        $aiInsights = collect($aiAnalysis['insights'])->take(3);

        // Keep a serializable copy to reference in action handlers (Livewire methods)
        $this->lastAiRecommendations = $aiRecommendations->values()->map(fn ($r) => (array) $r)->toArray();

        $this->isLoading = false;

        return view('livewire.fleet', [
            'machines' => $machinesQuery,
            'excavators' => $excavators,
            'adts' => $adts,
            'mineAreas' => $mineAreas,
            'statusStats' => $statusStats,
            'topPerformers' => $topPerformers,
            'worstPerformers' => $worstPerformers,
            'unrankedMachines' => $unrankedMachines,
            'aiRecommendations' => $aiRecommendations,
            'aiInsights' => $aiInsights,
            'activityFeed' => $this->activityFeed,
            'isLoading' => $this->isLoading,
        ]);
    }

    public function implementRecommendation(int $index)
    {
        $this->authorizeRecommendationAction();

        $team = Auth::user()->currentTeam;
        $rec = $this->lastAiRecommendations[$index] ?? null;
        if (! $rec) {
            $this->dispatch('notify', ['message' => 'Recommendation not found', 'type' => 'error']);

            return;
        }

        // Compute a stable hash for the recommendation
        $hash = md5(json_encode($rec));

        // Create action record
        $action = AiRecommendationAction::create([
            'team_id' => $team->id,
            'recommendation_hash' => $hash,
            'recommendation' => $rec,
            'status' => 'implemented',
            'actioned_by' => Auth::id(),
            'actioned_at' => now(),
        ]);

        // Apply operational adjustment (best-effort): if recommendation references a machine, create an activity log and tag machine
        if (! empty($rec['related_machine_id'])) {
            $machine = Machine::where('team_id', $team->id)->find($rec['related_machine_id']);
            if ($machine) {
                ActivityLog::create([
                    'team_id' => $team->id,
                    'user_id' => Auth::id(),
                    'action' => 'ai_recommendation_implemented',
                    'description' => "Implemented AI recommendation: {$rec['title']} for machine {$machine->name}",
                ]);
            }
        } else {
            ActivityLog::create([
                'team_id' => $team->id,
                'user_id' => Auth::id(),
                'action' => 'ai_recommendation_implemented',
                'description' => "Implemented AI recommendation: {$rec['title']}",
            ]);
        }

        // Dispatch a success notification and record that performance tracking should occur (placeholder)
        $this->dispatch('notify', ['message' => 'Recommendation implemented. Performance will be tracked.', 'type' => 'success']);
    }

    public function openRejectRecommendation(int $index)
    {
        $this->pendingRecommendationIndex = $index;
        $this->rejectReason = '';
        $this->showRejectRecommendationModal = true;
    }

    public function confirmRejectRecommendation()
    {
        $this->authorizeRecommendationAction();

        if (empty(trim($this->rejectReason))) {
            $this->dispatch('notify', ['message' => 'Please provide a reason for rejection', 'type' => 'error']);

            return;
        }

        $team = Auth::user()->currentTeam;
        $rec = $this->lastAiRecommendations[$this->pendingRecommendationIndex] ?? null;
        if (! $rec) {
            $this->dispatch('notify', ['message' => 'Recommendation not found', 'type' => 'error']);
            $this->showRejectRecommendationModal = false;

            return;
        }

        $hash = md5(json_encode($rec));

        AiRecommendationAction::create([
            'team_id' => $team->id,
            'recommendation_hash' => $hash,
            'recommendation' => $rec,
            'status' => 'rejected',
            'actioned_by' => Auth::id(),
            'actioned_at' => now(),
            'reject_reason' => $this->rejectReason,
        ]);

        ActivityLog::create([
            'team_id' => $team->id,
            'user_id' => Auth::id(),
            'action' => 'ai_recommendation_rejected',
            'description' => "Rejected AI recommendation: {$rec['title']} — Reason: {$this->rejectReason}",
        ]);

        $this->showRejectRecommendationModal = false;
        $this->pendingRecommendationIndex = null;
        $this->rejectReason = '';

        $this->dispatch('notify', ['message' => 'Recommendation rejected and logged', 'type' => 'success']);
    }

    /**
     * These AI recommendations are computed fresh on every page load by
     * FleetOptimizerAgent (plain arrays, never persisted with an id), unlike
     * AIOptimizationDashboard's recommendations which are real AIRecommendation
     * rows -- there's no model instance to authorize against here via
     * AIRecommendationPolicy, so this checks the same underlying permission
     * directly instead.
     */
    private function authorizeRecommendationAction(): void
    {
        $user = Auth::user();

        if (! $user->hasPermission('update_recommendations') && ! $user->ownsTeam($user->currentTeam)) {
            abort(403, 'You are not authorized to act on AI recommendations.');
        }
    }
}
