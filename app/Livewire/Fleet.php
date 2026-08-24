<?php

namespace App\Livewire;

use App\Exceptions\IneligibleAssignmentException;
use App\Exceptions\InsufficientAllocationException;
use App\Livewire\Concerns\NotifiesUser;
use App\Models\ActivityLog;
use App\Models\AIAgent;
use App\Models\AiRecommendationAction;
use App\Models\Machine;
use App\Models\MineArea;
use App\Models\Operator;
use App\Models\User;
use App\Services\AI\FleetOptimizerAgent;
use App\Services\Billing\MachineEntitlementService;
use App\Services\Billing\MachineProvisioningService;
use App\Services\MachinePerformanceService;
use App\Services\OperationalSnapshotService;
use App\Services\Operators\AssignmentEligibility;
use App\Services\Operators\OperatorAssignmentService;
use App\Support\ApiPayload;
use App\Support\CurrentUser;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Lazy;
use Livewire\Component;
use Livewire\WithPagination;

#[Lazy]
class Fleet extends Component
{
    use NotifiesUser;

    /**
     * Skeleton shown while this page lazy-loads -- the page shell paints
     * immediately instead of blocking on mount()'s data queries.
     *
     * @psalm-suppress PossiblyUnusedMethod -- invoked by Livewire's lazy-loading lifecycle
     */
    public function placeholder(): View
    {
        return view('livewire.placeholders.dashboard');
    }

    /** @var array<int|string, mixed> */
    public array $activityFeed = [];

    public bool $isLoading = true;

    use WithPagination;

    // AI recommendation interaction state
    /** @var array<int|string, mixed> */
    /** @var list<array<string, mixed>> */
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

    /** @var list<int|string> */
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

    /**
     * @var array<string>
     *
     * @psalm-suppress NonInvariantDocblockPropertyType -- Livewire's HandlesEvents leaves $listeners untyped (mixed)
     */
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
        $this->model = ApiPayload::str($machine->model, '');
        $this->manufacturer = $machine->manufacturer ?? '';
        $this->machineType = $machine->machine_type;
        $this->status = $machine->status;
        $this->serialNumber = $machine->serial_number ?? '';
        $this->capacity = $machine->capacity ?? 0;
        $this->latitude = $machine->last_location_latitude ?? 0;
        $this->longitude = $machine->last_location_longitude ?? 0;
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

        $team = CurrentUser::team();

        if (($this->editingMachineId !== null && $this->editingMachineId !== 0)) {
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
                'last_location_latitude' => $this->latitude ?: null,
                'last_location_longitude' => $this->longitude ?: null,
            ]);
            $this->notify('Machine updated successfully', 'success');
        } else {
            $this->authorize('create', Machine::class);

            try {
                app(MachineProvisioningService::class)->provision(
                    $team,
                    $this->machineType,
                    fn (): Machine => Machine::create([
                        'team_id' => $team->id,
                        'name' => $this->name,
                        'model' => $this->model,
                        'manufacturer' => $this->manufacturer ?: null,
                        'machine_type' => $this->machineType,
                        'status' => $this->status,
                        'serial_number' => $this->serialNumber,
                        'capacity' => $this->capacity ?: null,
                        'last_location_latitude' => $this->latitude ?: null,
                        'last_location_longitude' => $this->longitude ?: null,
                    ]),
                );
            } catch (InsufficientAllocationException $e) {
                // Server-side gate, not a UI nicety: the same exception fires
                // no matter which door a creation attempt comes through.
                $this->addError('allocation', $e->getMessage());

                return;
            }

            $this->notify('Machine created successfully', 'success');
        }

        $this->closeModal();
    }

    /**
     * Activate a machine an integration discovered while no allocation was
     * available (brief §23). Consumes one allocation; fails with the same
     * honest message as any other capacity miss.
     */
    public function activateMachine(Machine $machine): void
    {
        $this->authorize('update', $machine);

        try {
            app(MachineProvisioningService::class)->activate($machine);
            $this->notify("Machine '{$machine->name}' activated", 'success');
        } catch (InsufficientAllocationException $e) {
            $this->addError('allocation', $e->getMessage());
        }
    }

    /**
     * Decommission: release the machine's allocation WITHOUT deleting its
     * history (brief §13 replacement flow -- free the slot, keep the
     * record, add the replacement without buying another allocation).
     */
    public function decommissionMachine(Machine $machine): void
    {
        $this->authorize('update', $machine);

        $machine->forceFill([
            'allocation_state' => MachineEntitlementService::STATE_RELEASED,
        ])->save();

        $this->notify("Machine '{$machine->name}' decommissioned — its allocation is available again", 'success');
    }

    public function deleteMachine(Machine $machine): void
    {
        $this->authorize('delete', $machine);

        $machineName = $machine->name;
        $machine->delete();
        $this->notify("Machine '{$machineName}' deleted successfully", 'success');
    }

    public function openAssignModal(int $machineId): void
    {
        $this->assigningMachineId = $machineId;
        $this->selectedExcavatorId = null;
        $this->selectedAdtIds = [];
        $this->assignMode = 'assign_to_excavator';
        $team = CurrentUser::team();
        $machine = Machine::where('team_id', $team->id)->find($machineId);
        if (! $machine) {
            $this->notify('Machine not found', 'error');

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
                ->values()
                ->all();
        } else {
            // For ADTs and other machines, allow selecting a single excavator
            if (($machine->excavator_id !== null && $machine->excavator_id !== 0)) {
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

        if (($this->assigningMachineId === null || $this->assigningMachineId === 0) || ($this->selectedExcavatorId === null || $this->selectedExcavatorId === 0)) {
            $this->notify('Please select an excavator', 'error');

            return;
        }

        $team = CurrentUser::team();
        $machine = Machine::where('team_id', $team->id)->find($this->assigningMachineId);
        $excavator = Machine::where('team_id', $team->id)->find($this->selectedExcavatorId);

        if (! $machine || ! $excavator) {
            abort(403);
        }

        $this->authorize('update', $machine);
        $this->authorize('update', $excavator);

        // Prevent assigning a machine to itself
        if ($machine->id === $excavator->id) {
            $this->notify('Cannot assign a machine to itself', 'error');

            return;
        }

        // Prevent assigning big machines (excavator/dozer/loader/etc.) to another big machine
        $bigTypes = ['excavator', 'dozer', 'loader', 'grader', 'bulldozer'];
        if (in_array($machine->machine_type, $bigTypes) && in_array($excavator->machine_type, $bigTypes)) {
            $this->notify('Cannot assign an excavator or big machine to another big machine', 'error');

            return;
        }

        // Assign
        $machine->assignToExcavator($this->selectedExcavatorId);
        $this->notify("Machine '{$machine->name}' assigned to '{$excavator->name}'", 'success');
        $this->closeAssignModal();
    }

    public function assignAdtsToExcavator(): void
    {
        if (($this->assigningMachineId === null || $this->assigningMachineId === 0)) {
            $this->notify('Excavator not specified', 'error');

            return;
        }

        $team = CurrentUser::team();
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

        $this->notify('Assigned ADTs updated successfully', 'success');
        $this->closeAssignModal();
    }

    public function unassignFromExcavator(int $machineId): void
    {
        $team = CurrentUser::team();
        $machine = Machine::where('team_id', $team->id)->find($machineId);

        if (! $machine) {
            abort(403);
        }
        $this->authorize('update', $machine);

        $machineName = $machine->name;
        $machine->unassignFromExcavator();

        $this->notify("Machine '{$machineName}' unassigned from excavator", 'success');
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
        if (($this->assigningMineAreaMachineId === null || $this->assigningMineAreaMachineId === 0) || ($this->selectedMineAreaId === null || $this->selectedMineAreaId === 0)) {
            $this->notify('Please select a mine area', 'error');

            return;
        }

        $team = CurrentUser::team();
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

        $this->notify("Machine '{$machine->name}' assigned to '{$mineArea->name}'", 'success');

        $this->closeMineAreaAssignModal();
    }

    // calculateMachinePerformance() used to live here: it averaged columns
    // no integration ever writes (total_hours, fuel_consumption_rate,
    // payload_capacity_used) and fell back to a hardcoded neutral 50 for
    // fuel efficiency, so every machine scored an identical, meaningless
    // "15%". MachinePerformanceService now derives real daily metrics from
    // the telemetry and production data the sync actually stores.

    /**
     * Entitlement numbers for the page banner -- computed so the banner
     * stays current after every create/delete without extra wiring.
     *
     * @return array{purchased: array{adt: int, heavy: int}, occupied: array{adt: int, heavy: int}, available: array{adt: int, heavy: int}, trial: bool, trial_allowance: int, over_allocated: bool, suspended: bool}
     */
    public function getAllocationSummaryProperty(): array
    {
        $team = CurrentUser::team();

        return app(MachineEntitlementService::class)->summary($team);
    }

    /**
     * Whether the current user may manage operator assignments, computed
     * ONCE -- a per-card @can('update', $machine) is a role+permission query
     * pair multiplied by the machine count, and the fleet page's query
     * budget (rightly) refuses that. The machines listed are already
     * team-scoped, so the tenancy half of the policy is satisfied by the
     * query itself.
     */
    public function getCanManageOperatorsProperty(): bool
    {
        return CurrentUser::get()?->hasPermission('update_machines') ?? false;
    }

    /** Operator assignment picker state */
    public ?int $assignOperatorMachineId = null;

    public string $operatorSearch = '';

    public string $overrideReason = '';

    /** @var list<string> */
    public array $assignmentBlockers = [];

    /** @var list<string> */
    public array $assignmentWarnings = [];

    /**
     * The picker's open-state is entangled with the modal's Alpine `show`;
     * closing via backdrop or Escape writes `false`, which Livewire coerces
     * to 0 on this ?int -- half-closed server state. Normalise to null.
     */
    public function updatedAssignOperatorMachineId(mixed $value): void
    {
        if (! $value) {
            $this->assignOperatorMachineId = null;
        }
    }

    public function openAssignOperator(int $machineId): void
    {
        $this->authorize('update', Machine::query()->findOrFail($machineId));

        $this->assignOperatorMachineId = $machineId;
        $this->operatorSearch = '';
        $this->overrideReason = '';
        $this->assignmentBlockers = [];
        $this->assignmentWarnings = [];
    }

    public function closeAssignOperator(): void
    {
        $this->assignOperatorMachineId = null;
    }

    /**
     * Operators to offer for the machine being assigned, each with its
     * eligibility verdict -- the picker shows WHY someone is not eligible
     * instead of silently hiding them.
     *
     * @return list<array{operator: Operator, eligible: bool, blockers: list<string>, warnings: list<string>}>
     */
    public function getAssignableOperatorsProperty(): array
    {
        if ($this->assignOperatorMachineId === null) {
            return [];
        }

        $machine = Machine::query()->find($this->assignOperatorMachineId);

        if ($machine === null) {
            return [];
        }

        $eligibility = app(AssignmentEligibility::class);

        $query = Operator::query()->with(['qualifications', 'medicals', 'trainings']);

        if ($this->operatorSearch !== '') {
            $term = '%'.strtolower($this->operatorSearch).'%';
            $query->where(function (\Illuminate\Contracts\Database\Query\Builder $q) use ($term): void {
                $q->whereRaw('LOWER(first_name) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(last_name) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(employee_number) LIKE ?', [$term]);
            });
        }

        $rows = [];

        /**
         * @var Collection<int, Operator> $operators
         *
         * @psalm-suppress UnnecessaryVarAnnotation -- phpstan loses the model
         * generic through the where() closures; psalm keeps it.
         */
        $operators = $query->orderBy('last_name')->limit(25)->get();

        foreach ($operators as $operator) {
            $check = $eligibility->check($operator, $machine);
            $rows[] = ['operator' => $operator, ...$check];
        }

        // Eligible first, so the default pick is a legal one.
        usort($rows, static fn (array $a, array $b): int => (int) $b['eligible'] <=> (int) $a['eligible']);

        return $rows;
    }

    public function assignOperator(int $operatorId): void
    {
        if ($this->assignOperatorMachineId === null) {
            return;
        }

        $machine = Machine::query()->findOrFail($this->assignOperatorMachineId);
        $this->authorize('update', $machine);

        $operator = Operator::query()->findOrFail($operatorId);
        $user = CurrentUser::get();

        if ($user === null) {
            return;
        }

        try {
            app(OperatorAssignmentService::class)->assign(
                $operator,
                $machine,
                $user,
                $operator->default_shift,
                $this->overrideReason !== '' ? $this->overrideReason : null,
            );

            $this->assignOperatorMachineId = null;
            $this->assignmentBlockers = [];
            $this->assignmentWarnings = [];
        } catch (IneligibleAssignmentException $e) {
            // Shown in the modal; assignment did not happen.
            $this->assignmentBlockers = $e->blockers;
        }
    }

    public function unassignOperator(int $machineId): void
    {
        $machine = Machine::query()->findOrFail($machineId);
        $this->authorize('update', $machine);

        $assignment = $machine->currentOperatorAssignment();
        $user = CurrentUser::get();

        if ($assignment !== null && $user !== null) {
            app(OperatorAssignmentService::class)->unassign($assignment, $user);
        }
    }

    public function render(): View
    {
        $this->isLoading = true;
        $team = CurrentUser::team();

        $machinesQuery = Machine::where('team_id', $team->id)
            ->with(['excavator', 'latestEngineHoursMetric', 'latestMetric', 'operatorAssignments' => fn (Relation $q) => $q->whereNull('unassigned_at')->with('operator')])
            ->when($this->search, function (Builder $query): mixed {
                return $query->where('name', 'like', "%{$this->search}%")
                    ->orWhere('model', 'like', "%{$this->search}%")
                    ->orWhere('manufacturer', 'like', "%{$this->search}%");
            })
            ->when($this->statusFilter, function (Builder $query): mixed {
                return $query->where('status', $this->statusFilter);
            })
            ->orderBy($this->sortBy, $this->sortDirection === 'desc' ? 'desc' : 'asc')
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
            ->map(fn (ActivityLog $log): array => [
                'user' => $log->user?->name ?? 'System',
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
        $this->lastAiRecommendations = array_values($aiRecommendations->all());

        $this->isLoading = false;

        return view('livewire.fleet', [
            'machines' => $machinesQuery,
            'snapshots' => app(OperationalSnapshotService::class)->forTeam($team),
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

    public function implementRecommendation(int $index): void
    {
        $this->authorizeRecommendationAction();

        $team = CurrentUser::team();
        $rec = $this->lastAiRecommendations[$index] ?? null;
        if ($rec === null || $rec === []) {
            $this->notify('Recommendation not found', 'error');

            return;
        }

        // Compute a stable hash for the recommendation
        $encoded = json_encode($rec);
        $hash = md5($encoded === false ? '' : $encoded);

        // Create action record
        $action = AiRecommendationAction::create([
            'team_id' => $team->id,
            'recommendation_hash' => $hash,
            'recommendation' => $rec,
            'status' => 'implemented',
            'actioned_by' => Auth::id(),
            'actioned_at' => now(),
        ]);

        $title = ApiPayload::str($rec['title'] ?? null, 'Untitled');
        $relatedMachineId = (int) (is_numeric($rec['related_machine_id'] ?? null) ? $rec['related_machine_id'] : 0);

        // Apply operational adjustment (best-effort): if recommendation references a machine, create an activity log and tag machine
        if ($relatedMachineId !== 0) {
            $machine = Machine::where('team_id', $team->id)->find($relatedMachineId);
            if ($machine) {
                ActivityLog::create([
                    'team_id' => $team->id,
                    'user_id' => Auth::id(),
                    'action' => 'ai_recommendation_implemented',
                    'description' => "Implemented AI recommendation: {$title} for machine {$machine->name}",
                ]);
            }
        } else {
            ActivityLog::create([
                'team_id' => $team->id,
                'user_id' => Auth::id(),
                'action' => 'ai_recommendation_implemented',
                'description' => "Implemented AI recommendation: {$title}",
            ]);
        }

        $this->recordRecommendationOutcome(true);

        $this->notify('Recommendation implemented — Fleet Optimizer accuracy updated.', 'success');
    }

    /**
     * Implementing or rejecting a recommendation is the human verdict on
     * the Fleet Optimizer's prediction -- the only outcome signal the
     * platform has. It feeds the accuracy_score / predictions_made that
     * the AI Analytics page displays; AIAgent::updateAccuracy() had no
     * caller at all before this, so those metrics could never move.
     */
    private function recordRecommendationOutcome(bool $wasSuccessful): void
    {
        $agent = AIAgent::query()
            ->where('type', AIAgent::TYPE_FLEET_OPTIMIZER)
            ->first();

        if ($agent instanceof AIAgent) {
            $agent->updateAccuracy($wasSuccessful);
        }
    }

    public function openRejectRecommendation(int $index): void
    {
        $this->pendingRecommendationIndex = $index;
        $this->rejectReason = '';
        $this->showRejectRecommendationModal = true;
    }

    public function confirmRejectRecommendation(): void
    {
        $this->authorizeRecommendationAction();

        if (empty(trim($this->rejectReason))) {
            $this->notify('Please provide a reason for rejection', 'error');

            return;
        }

        $team = CurrentUser::team();
        $rec = $this->pendingRecommendationIndex !== null
            ? ($this->lastAiRecommendations[$this->pendingRecommendationIndex] ?? null)
            : null;
        if ($rec === null || $rec === []) {
            $this->notify('Recommendation not found', 'error');
            $this->showRejectRecommendationModal = false;

            return;
        }

        $encoded = json_encode($rec);
        $hash = md5($encoded === false ? '' : $encoded);

        AiRecommendationAction::create([
            'team_id' => $team->id,
            'recommendation_hash' => $hash,
            'recommendation' => $rec,
            'status' => 'rejected',
            'actioned_by' => Auth::id(),
            'actioned_at' => now(),
            'reject_reason' => $this->rejectReason,
        ]);

        $this->recordRecommendationOutcome(false);

        ActivityLog::create([
            'team_id' => $team->id,
            'user_id' => Auth::id(),
            'action' => 'ai_recommendation_rejected',
            'description' => 'Rejected AI recommendation: '.ApiPayload::str($rec['title'] ?? null, 'Untitled')." — Reason: {$this->rejectReason}",
        ]);

        $this->showRejectRecommendationModal = false;
        $this->pendingRecommendationIndex = null;
        $this->rejectReason = '';

        $this->notify('Recommendation rejected and logged', 'success');
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
        $user = CurrentUser::get();

        if (! $user instanceof User || (! $user->hasPermission('update_recommendations') && ! $user->ownsTeam($user->currentTeam))) {
            abort(403, 'You are not authorized to act on AI recommendations.');
        }
    }
}
