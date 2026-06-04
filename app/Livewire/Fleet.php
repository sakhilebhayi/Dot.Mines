<?php

namespace App\Livewire;

use App\Models\EngineHourSession;
use App\Models\Machine;
use App\Models\MineArea;
use App\Models\Subscription;
use App\Services\AI\FleetOptimizerAgent;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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

    public int $cycleTimeMinutes = 0;

    public int $queueTimeMinutes = 0;

    public int $loadingTimeMinutes = 0;

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
        $allowed = ['name', 'manufacturer', 'status', 'created_at'];
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

    public function updatedSortBy(string $value): void
    {
        $allowed = ['name', 'manufacturer', 'status', 'created_at'];
        if (! in_array($value, $allowed, true)) {
            $this->sortBy = 'name';
        }
    }

    public function updatedSortDirection(string $value): void
    {
        if (! in_array($value, ['asc', 'desc'], true)) {
            $this->sortDirection = 'asc';
        }
    }

    public function openCreateModal(): void
    {
        if ($this->isFleetFull()) {
            $this->dispatch('notify', message: 'Fleet slot limit reached for your subscription plan. Upgrade to add more machines.', type: 'error');

            return;
        }
        $this->resetForm();
        $this->showCreateModal = true;
    }

    /**
     * Returns true when the team has reached its subscribed machine limit.
     * Covers both active and trial subscriptions.
     */
    private function isFleetFull(): bool
    {
        $team = Auth::user()->currentTeam;

        if ($team === null) {
            return false;
        }

        return Subscription::teamHasReachedMachineLimit($team->id);
    }

    /**
     * Returns [current, max] fleet slot counts for the current team.
     */
    public function fleetUsage(): array
    {
        $team = Auth::user()->currentTeam;
        $teamId = $team?->id ?? 0;
        $current = Machine::where('team_id', $teamId)->count();

        $subscription = Subscription::with('plan')
            ->active()
            ->where('team_id', $teamId)
            ->first();

        $max = $subscription?->plan?->max_machines ?? null;

        return ['current' => $current, 'max' => $max];
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
        $this->cycleTimeMinutes = 0;
        $this->queueTimeMinutes = 0;
        $this->loadingTimeMinutes = 0;
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
        $this->cycleTimeMinutes = $machine->cycle_time_minutes ?? 0;
        $this->queueTimeMinutes = $machine->queue_time_minutes ?? 0;
        $this->loadingTimeMinutes = $machine->loading_time_minutes ?? 0;
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
            'cycleTimeMinutes' => 'nullable|integer|min:0|max:9999',
            'queueTimeMinutes' => 'nullable|integer|min:0|max:9999',
            'loadingTimeMinutes' => 'nullable|integer|min:0|max:9999',
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
                'cycle_time_minutes' => $this->cycleTimeMinutes ?: null,
                'queue_time_minutes' => $this->queueTimeMinutes ?: null,
                'loading_time_minutes' => $this->loadingTimeMinutes ?: null,
            ]);
            $this->dispatch('notify', message: 'Machine updated successfully', type: 'success');
        } else {
            // Guard: enforce subscription fleet slot limit
            if ($this->isFleetFull()) {
                $this->addError('name', 'Fleet slot limit reached for your subscription plan.');

                return;
            }

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
                'cycle_time_minutes' => $this->cycleTimeMinutes ?: null,
                'queue_time_minutes' => $this->queueTimeMinutes ?: null,
                'loading_time_minutes' => $this->loadingTimeMinutes ?: null,
            ]);
            $this->dispatch('notify', message: 'Machine created successfully', type: 'success');
        }

        $this->closeModal();
    }

    public function deleteMachine(Machine $machine): void
    {
        if ($machine->team_id !== Auth::user()->currentTeam->id) {
            abort(403);
        }

        $this->authorize('delete', $machine);
        $machineName = $machine->name;
        $machine->delete();
        $this->dispatch('notify', message: "Machine '{$machineName}' deleted successfully", type: 'success');
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
            $this->dispatch('notify', message: 'Machine not found', type: 'error');

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
            $this->dispatch('notify', message: 'Please select an excavator', type: 'error');

            return;
        }

        $team = Auth::user()->currentTeam;
        $machine = Machine::where('team_id', $team->id)->find($this->assigningMachineId);
        $excavator = Machine::where('team_id', $team->id)->find($this->selectedExcavatorId);

        if (! $machine || $machine->team_id !== Auth::user()->currentTeam->id) {
            abort(403);
        }

        if (! $excavator || $excavator->team_id !== Auth::user()->currentTeam->id) {
            abort(403);
        }

        // Prevent assigning a machine to itself
        if ($machine->id === $excavator->id) {
            $this->dispatch('notify', message: 'Cannot assign a machine to itself', type: 'error');

            return;
        }

        // Prevent assigning big machines (excavator/dozer/loader/etc.) to another big machine
        $bigTypes = ['excavator', 'dozer', 'loader', 'grader', 'bulldozer'];
        if (in_array($machine->machine_type, $bigTypes) && in_array($excavator->machine_type, $bigTypes)) {
            $this->dispatch('notify', message: 'Cannot assign an excavator or big machine to another big machine', type: 'error');

            return;
        }

        // Assign
        $machine->assignToExcavator($this->selectedExcavatorId);
        $this->dispatch('notify', message: "Machine '{$machine->name}' assigned to '{$excavator->name}'", type: 'success');
        $this->closeAssignModal();
    }

    public function assignAdtsToExcavator(): void
    {
        if (! $this->assigningMachineId) {
            $this->dispatch('notify', message: 'Excavator not specified', type: 'error');

            return;
        }

        $team = Auth::user()->currentTeam;
        $excavator = Machine::where('team_id', $team->id)->find($this->assigningMachineId);
        if (! $excavator) {
            abort(403);
        }

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

        $this->dispatch('notify', message: 'Assigned ADTs updated successfully', type: 'success');
        $this->closeAssignModal();
    }

    public function unassignFromExcavator(int $machineId): void
    {
        $team = Auth::user()->currentTeam;
        $machine = Machine::where('team_id', $team->id)->find($machineId);

        if (! $machine) {
            abort(403);
        }

        $machineName = $machine->name;
        $machine->unassignFromExcavator();

        $this->dispatch('notify', message: "Machine '{$machineName}' unassigned from excavator", type: 'success');
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
            $this->dispatch('notify', message: 'Please select a mine area', type: 'error');

            return;
        }

        $team = Auth::user()->currentTeam;
        $machine = Machine::where('team_id', $team->id)->find($this->assigningMineAreaMachineId);

        if (! $machine) {
            abort(403);
        }

        $mineArea = MineArea::where('team_id', $team->id)->find($this->selectedMineAreaId);
        if (! $mineArea) {
            abort(403);
        }

        // Update machine's mine_area_id field
        $machine->update(['mine_area_id' => $this->selectedMineAreaId]);

        $this->dispatch('notify', message: "Machine '{$machine->name}' assigned to '{$mineArea->name}'", type: 'success');

        $this->closeMineAreaAssignModal();
    }

    private function calculateMachinePerformance(int $teamId): array
    {
        $machines = Machine::where('team_id', $teamId)->get()->keyBy('id');

        if ($machines->isEmpty()) {
            return [];
        }

        // Single aggregating query instead of N per-machine queries
        $aggregates = DB::table('machine_metrics')
            ->whereIn('machine_id', $machines->keys())
            ->where('created_at', '>=', now()->subDays(30))
            ->groupBy('machine_id')
            ->selectRaw('machine_id,
                AVG(fuel_consumption_rate) as avg_fuel,
                AVG(total_hours)           as avg_hours,
                AVG(idle_hours)            as avg_idle,
                AVG(payload_capacity_used) as avg_payload,
                AVG(speed)                 as avg_speed,
                COUNT(*)                   as row_count')
            ->get()
            ->keyBy('machine_id');

        $performanceData = [];

        foreach ($aggregates as $machineId => $agg) {
            if (! isset($machines[$machineId])) {
                continue;
            }

            $machine = $machines[$machineId];
            $avgFuelConsumption = (float) ($agg->avg_fuel ?? 0);
            $avgTotalHours = (float) ($agg->avg_hours ?? 0);
            $avgIdleHours = (float) ($agg->avg_idle ?? 0);
            $avgPayloadUsage = (float) ($agg->avg_payload ?? 0);

            $utilizationRate = $avgTotalHours > 0
                ? (($avgTotalHours - $avgIdleHours) / $avgTotalHours) * 100
                : 0;

            $fuelEfficiency = $avgTotalHours > 0 && $avgFuelConsumption > 0
                ? (1 / ($avgFuelConsumption / $avgTotalHours)) * 10
                : 50;

            $performanceScore = ($utilizationRate * 0.4) + ($fuelEfficiency * 0.3) + ($avgPayloadUsage * 0.3);

            $performanceData[] = [
                'machine_id' => $machine->id,
                'machine_name' => $machine->name,
                'machine_type' => $machine->machine_type,
                'manufacturer' => $machine->manufacturer,
                'performance_score' => round($performanceScore, 1),
                'utilization_rate' => round($utilizationRate, 1),
                'fuel_efficiency' => round($fuelEfficiency, 1),
                'productivity_score' => round($avgPayloadUsage, 1),
                'avg_hours' => round($avgTotalHours, 1),
                'status' => $machine->status,
            ];
        }

        return $performanceData;
    }

    /**
     * Build a map of today's engine hours keyed by machine ID.
     *
     * Each entry: ['today_hours' => float, 'is_running' => bool]
     *
     * Engine hours are calculated from ignition ON → OFF events stored in
     * engine_hour_sessions. For sessions that are still open (engine running)
     * the elapsed time since ignition_on_at is included in the total.
     *
     * @param  int[]  $machineIds  IDs of machines currently visible on the page
     * @return array<int, array{today_hours: float, is_running: bool}>
     */
    /**
     * Build timing analytics for all fleet machines.
     * Returns fleet-wide averages plus a per-machine array suitable for chart/table rendering.
     *
     * @return array{avg_cycle: float|null, avg_queue: float|null, avg_loading: float|null, machines: array}
     */
    private function buildTimingAnalytics(int $teamId): array
    {
        $machines = Machine::where('team_id', $teamId)
            ->where(function ($q) {
                $q->whereNotNull('cycle_time_minutes')
                    ->orWhereNotNull('queue_time_minutes')
                    ->orWhereNotNull('loading_time_minutes');
            })
            ->orderBy('name')
            ->get(['id', 'name', 'machine_type', 'cycle_time_minutes', 'queue_time_minutes', 'loading_time_minutes']);

        if ($machines->isEmpty()) {
            return ['avg_cycle' => null, 'avg_queue' => null, 'avg_loading' => null, 'machines' => []];
        }

        $rows = $machines->map(fn ($m) => [
            'name' => $m->name,
            'type' => $m->machine_type ?? '',
            'cycle' => $m->cycle_time_minutes ?? 0,
            'queue' => $m->queue_time_minutes ?? 0,
            'loading' => $m->loading_time_minutes ?? 0,
            'total' => ($m->cycle_time_minutes ?? 0) + ($m->queue_time_minutes ?? 0) + ($m->loading_time_minutes ?? 0),
        ])->values()->toArray();

        $avgCycle = $machines->whereNotNull('cycle_time_minutes')->avg('cycle_time_minutes');
        $avgQueue = $machines->whereNotNull('queue_time_minutes')->avg('queue_time_minutes');
        $avgLoading = $machines->whereNotNull('loading_time_minutes')->avg('loading_time_minutes');

        return [
            'avg_cycle' => $avgCycle !== null ? round($avgCycle, 1) : null,
            'avg_queue' => $avgQueue !== null ? round($avgQueue, 1) : null,
            'avg_loading' => $avgLoading !== null ? round($avgLoading, 1) : null,
            'machines' => $rows,
        ];
    }

    private function buildEngineHoursMap(array $machineIds, int $teamId): array
    {
        if (empty($machineIds)) {
            return [];
        }

        $map = array_fill_keys($machineIds, ['today_seconds' => 0, 'is_running' => false]);

        EngineHourSession::where('team_id', $teamId)
            ->whereIn('machine_id', $machineIds)
            ->where('ignition_on_at', '>=', now()->startOfDay())
            ->get()
            ->each(function (EngineHourSession $session) use (&$map): void {
                $id = $session->machine_id;
                if (! isset($map[$id])) {
                    return;
                }

                if ($session->ignition_off_at === null) {
                    // Engine is currently running — include live elapsed time
                    $map[$id]['is_running'] = true;
                    $map[$id]['today_seconds'] += (int) $session->ignition_on_at->diffInSeconds(now());
                } else {
                    $map[$id]['today_seconds'] += $session->duration_seconds
                        ?? (int) $session->ignition_on_at->diffInSeconds($session->ignition_off_at);
                }
            });

        // Convert seconds → hours and remove the intermediate accumulator
        foreach ($map as $id => &$data) {
            $data['today_hours'] = round($data['today_seconds'] / 3600, 1);
            unset($data['today_seconds']);
        }

        return $map;
    }

    public function render(): \Illuminate\View\View
    {
        $this->isLoading = true;
        $team = Auth::user()->currentTeam;
        $teamId = $team?->id ?? 0;

        $machinesQuery = Machine::where('team_id', $teamId)
            ->with('excavator')
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
        $excavators = Machine::where('team_id', $teamId)
            ->whereIn('machine_type', ['excavator', 'digger', 'loader'])
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        // Get all ADTs for potential assignment to excavators
        $adts = Machine::where('team_id', $teamId)
            ->where('machine_type', 'adt')
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        // Get all mine areas for assignment dropdown
        $mineAreas = MineArea::where('team_id', $teamId)
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        $statusStats = [
            'active' => Machine::where('team_id', $teamId)->where('status', 'active')->count(),
            'idle' => Machine::where('team_id', $teamId)->where('status', 'idle')->count(),
            'maintenance' => Machine::where('team_id', $teamId)->where('status', 'maintenance')->count(),
        ];

        // Calculate machine performance based on recent metrics (last 30 days)
        $performanceData = $this->calculateMachinePerformance($teamId);
        $topPerformers = collect($performanceData)->sortByDesc('performance_score')->take(5)->values();
        $worstPerformers = collect($performanceData)->sortBy('performance_score')->take(5)->values();

        // Activity Feed
        $this->activityFeed = \App\Models\ActivityLog::where('team_id', $teamId)
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
        $aiAgent = new FleetOptimizerAgent;
        $aiAnalysis = $aiAgent->analyze($team);
        $aiRecommendations = collect($aiAnalysis['recommendations'])->take(5);
        $aiInsights = collect($aiAnalysis['insights'])->take(3);

        // Keep a serializable copy to reference in action handlers (Livewire methods)
        $this->lastAiRecommendations = $aiRecommendations->values()->map(fn ($r) => (array) $r)->toArray();

        $this->isLoading = false;

        $fleetUsage = $this->fleetUsage();

        // Engine hours per machine for the current page (today's sessions)
        $engineHoursMap = $this->buildEngineHoursMap(
            $machinesQuery->pluck('id')->toArray(),
            $teamId
        );

        // Timing analytics across all fleet machines (not just current page)
        $timingAnalytics = $this->buildTimingAnalytics($teamId);

        return view('livewire.fleet', [
            'machines' => $machinesQuery,
            'excavators' => $excavators,
            'adts' => $adts,
            'mineAreas' => $mineAreas,
            'statusStats' => $statusStats,
            'topPerformers' => $topPerformers,
            'worstPerformers' => $worstPerformers,
            'aiRecommendations' => $aiRecommendations,
            'aiInsights' => $aiInsights,
            'activityFeed' => $this->activityFeed,
            'isLoading' => $this->isLoading,
            'fleetUsage' => $fleetUsage,
            'engineHoursMap' => $engineHoursMap,
            'timingAnalytics' => $timingAnalytics,
        ]);
    }

    public function implementRecommendation(int $index)
    {
        $team = Auth::user()->currentTeam;
        $rec = $this->lastAiRecommendations[$index] ?? null;
        if (! $rec) {
            $this->dispatch('notify', message: 'Recommendation not found', type: 'error');

            return;
        }

        // Compute a stable hash for the recommendation
        $hash = md5((string) json_encode($rec));

        // Create action record
        $action = \App\Models\AiRecommendationAction::create([
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
                \App\Models\ActivityLog::create([
                    'team_id' => $team->id,
                    'user_id' => Auth::id(),
                    'action' => 'ai_recommendation_implemented',
                    'description' => "Implemented AI recommendation: {$rec['title']} for machine {$machine->name}",
                ]);
            }
        } else {
            \App\Models\ActivityLog::create([
                'team_id' => $team->id,
                'user_id' => Auth::id(),
                'action' => 'ai_recommendation_implemented',
                'description' => "Implemented AI recommendation: {$rec['title']}",
            ]);
        }

        // Dispatch a success notification and record that performance tracking should occur (placeholder)
        $this->dispatch('notify', message: 'Recommendation implemented. Performance will be tracked.', type: 'success');
    }

    public function openRejectRecommendation(int $index)
    {
        $this->pendingRecommendationIndex = $index;
        $this->rejectReason = '';
        $this->showRejectRecommendationModal = true;
    }

    public function confirmRejectRecommendation()
    {
        if (empty(trim($this->rejectReason))) {
            $this->dispatch('notify', message: 'Please provide a reason for rejection', type: 'error');

            return;
        }

        $team = Auth::user()->currentTeam;
        $rec = $this->lastAiRecommendations[$this->pendingRecommendationIndex] ?? null;
        if (! $rec) {
            $this->dispatch('notify', message: 'Recommendation not found', type: 'error');
            $this->showRejectRecommendationModal = false;

            return;
        }

        $hash = md5((string) json_encode($rec));

        \App\Models\AiRecommendationAction::create([
            'team_id' => $team->id,
            'recommendation_hash' => $hash,
            'recommendation' => $rec,
            'status' => 'rejected',
            'actioned_by' => Auth::id(),
            'actioned_at' => now(),
            'reject_reason' => $this->rejectReason,
        ]);

        \App\Models\ActivityLog::create([
            'team_id' => $team->id,
            'user_id' => Auth::id(),
            'action' => 'ai_recommendation_rejected',
            'description' => "Rejected AI recommendation: {$rec['title']} — Reason: {$this->rejectReason}",
        ]);

        $this->showRejectRecommendationModal = false;
        $this->pendingRecommendationIndex = null;
        $this->rejectReason = '';

        $this->dispatch('notify', message: 'Recommendation rejected and logged', type: 'success');
    }
}
