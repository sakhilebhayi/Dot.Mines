<?php

namespace App\Livewire;

use App\Actions\Fuel\RecordFuelDispense;
use App\Actions\Fuel\RefuelTank;
use App\Exceptions\FuelDispenseException;
use App\Livewire\Concerns\NotifiesUser;
use App\Models\FuelAlert;
use App\Models\FuelMonthlyAllocation;
use App\Models\FuelTank;
use App\Models\FuelTransaction;
use App\Models\Machine;
use App\Models\MineArea;
use App\Models\User;
use App\Services\AI\FuelPredictorAgent;
use App\Support\CurrentUser;
use Carbon\CarbonInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Lazy;
use Livewire\Component;

#[Lazy]
class FuelManagement extends Component
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

    // Guided workflow modal: 1 Tank -> 2 Allocation -> 3 Dispense -> 4 Review
    public bool $showManageModal = false;

    public int $manageStep = 1;

    // Dispense Fuel form
    public string $transactionTankId = '';

    public string $transactionQuantity = '';

    public string $transactionType = 'dispensing';

    public string $transactionMachineId = '';

    public string $transactionError = '';

    public string $selectedPeriod = 'week';

    public bool $showLowFuelOnly = false;

    // Monthly allocation form
    public ?int $allocationYear = null;

    public ?int $allocationMonth = null;

    public ?float $allocatedLiters = null;

    public ?float $fuelPricePerLiter = null;

    public string $allocationNotes = '';

    public string $mineAreaId = '';

    // Tank creation form
    public string $tankName = '';

    public string $tankNumber = '';

    public string $tankCapacity = '';

    public string $tankMinimumLevel = '';

    public string $tankFuelType = 'diesel';

    public string $tankLocationDescription = '';

    public string $tankNotes = '';

    public string $tankMineAreaId = '';

    public string $selectedTankId = '';

    // Refuel form
    public string $refuelTankId = '';

    public string $refuelQuantity = '';

    public ?float $refuelUnitPrice = null;

    public string $refuelNotes = '';

    public bool $showRefuelModal = false;

    public bool $showDeleteConfirm = false;

    public ?int $confirmDeleteTankId = null;

    public function recordDispensingTransaction(): void
    {
        $this->transactionError = '';
        $this->validate([
            'transactionTankId' => 'required|exists:fuel_tanks,id',
            'transactionQuantity' => 'required|numeric|min:1',
        ]);

        $user = CurrentUser::get();

        try {
            app(RecordFuelDispense::class)->execute(
                (int) $user?->current_team_id,
                (int) $this->transactionTankId,
                (float) $this->transactionQuantity,
                $this->transactionMachineId ? (int) $this->transactionMachineId : null,
                $user?->id,
            );

            $this->notify('Dispensing transaction recorded.', 'success');
            $this->reset(['transactionTankId', 'transactionQuantity', 'transactionMachineId']);
            // Show the result of what was just dispensed.
            $this->goToStep(4);
        } catch (FuelDispenseException $e) {
            $this->transactionError = $e->getMessage();
            $this->notify($e->getMessage(), 'error');
        } catch (\Throwable $e) {
            Log::error('Failed to record dispensing transaction', ['error' => $e->getMessage()]);
            $this->transactionError = "We couldn't record this transaction. Please try again.";
            $this->notify($this->transactionError, 'error');
        }
    }

    public function mount(): void
    {
        $this->allocationYear = now()->year;
        $this->allocationMonth = now()->month;
    }

    /**
     * Open the guided fuel workflow. With no argument it opens at the first
     * step that still needs doing (no tanks -> Tank, no allocation for this
     * month -> Allocation, otherwise Dispense). Legacy tab names are mapped
     * so existing callers keep working.
     *
     * @param  string|null  $tab
     */
    public function openManageModal($tab = null): void
    {
        $this->showManageModal = true;

        $step = match ($tab) {
            'tank' => 1,
            'allocation' => 2,
            'dispense' => 3,
            'review' => 4,
            default => $this->firstIncompleteStep(),
        };

        $this->goToStep($step);
    }

    public function closeManageModal(): void
    {
        $this->showManageModal = false;
    }

    /**
     * Step navigation. Moving forward is gated on the prerequisites the
     * server enforces anyway: dispensing needs a tank AND a monthly
     * allocation, allocations are meaningless without a tank. Moving
     * backward is always allowed.
     */
    public function goToStep(int $step): void
    {
        $step = max(1, min(4, $step));

        if ($step >= 2 && ! $this->hasActiveTanks()) {
            $this->manageStep = 1;
            if ($step > 1) {
                $this->notify('Add a fuel tank first.', 'info');
            }

            return;
        }

        if ($step === 3 && ! $this->hasCurrentAllocation()) {
            $this->manageStep = 2;
            $this->notify('Set this month\'s allocation before dispensing.', 'info');

            return;
        }

        $this->manageStep = $step;

        // Fresh forms per step.
        if ($step === 1) {
            $this->reset(['tankName', 'tankNumber', 'tankCapacity', 'tankMinimumLevel', 'tankFuelType', 'tankLocationDescription', 'tankNotes']);
        } elseif ($step === 2) {
            $this->reset(['allocatedLiters', 'fuelPricePerLiter', 'allocationNotes']);
        } elseif ($step === 3) {
            $this->reset(['transactionTankId', 'transactionQuantity', 'transactionError']);
        }
    }

    private function firstIncompleteStep(): int
    {
        if (! $this->hasActiveTanks()) {
            return 1;
        }

        if (! $this->hasCurrentAllocation()) {
            return 2;
        }

        return 3;
    }

    private function hasActiveTanks(): bool
    {
        return FuelTank::where('team_id', auth()->user()?->current_team_id)
            ->where('status', 'active')
            ->exists();
    }

    private function hasCurrentAllocation(): bool
    {
        $authUser = CurrentUser::get();

        return FuelMonthlyAllocation::query()->where('team_id', $authUser?->current_team_id)
            ->where('year', now()->year)
            ->where('month', now()->month)
            ->exists();
    }

    public function saveTank(): void
    {
        $this->validate([
            'tankName' => 'required|string|max:255',
            'tankNumber' => 'nullable|string|max:50',
            'tankCapacity' => 'required|numeric|min:1|max:999999999',
            'tankMinimumLevel' => 'required|numeric|min:0|max:999999999',
            'tankFuelType' => 'required|in:diesel,petrol,aviation_fuel,biodiesel',
            'tankLocationDescription' => 'nullable|string|max:500',
            'tankNotes' => 'nullable|string|max:1000',
        ]);

        $user = Auth::user();
        if (! $user instanceof User || ($user->current_team_id === null || $user->current_team_id === 0)) {
            $this->notify('User session invalid', 'error');

            return;
        }

        $teamId = $user->current_team_id;

        try {
            $tank = FuelTank::create([
                'team_id' => $teamId,
                'name' => $this->tankName,
                'tank_number' => $this->tankNumber,
                'location_description' => $this->tankLocationDescription,
                'capacity_liters' => $this->tankCapacity,
                'current_level_liters' => $this->tankCapacity, // Start full
                'minimum_level_liters' => $this->tankMinimumLevel,
                'fuel_type' => $this->tankFuelType,
                'status' => 'active',
                'notes' => strip_tags($this->tankNotes),
            ]);

            // Ensure the newly created tank is immediately selected in the dispense dropdown
            $this->transactionTankId = (string) $tank->id;
            $this->selectedTankId = (string) $tank->id;

            $this->notify('Fuel tank created successfully', 'success');
            // Notify frontend and keep selection so new tank appears in dispense dropdown
            $this->dispatch('tank-created', ['id' => $tank->id, 'name' => $tank->name]);
            // Tank created: the natural next step is the monthly allocation.
            $this->reset(['tankName', 'tankNumber', 'tankCapacity', 'tankMinimumLevel', 'tankFuelType', 'tankLocationDescription', 'tankNotes', 'tankMineAreaId']);
            $this->goToStep(2);

        } catch (\Exception $e) {
            Log::error('Failed to create fuel tank', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            $this->notify('Failed to create tank', 'error');
        }
    }

    /**
     * Refuel (record a refill/delivery) for a tank and update its current level.
     */
    public function refuelTank(): void
    {
        $this->validate([
            'refuelTankId' => 'required|exists:fuel_tanks,id',
            'refuelQuantity' => 'required|numeric|min:0.01',
            'refuelUnitPrice' => 'nullable|numeric|min:0',
        ]);

        $user = CurrentUser::get();

        try {
            $result = app(RefuelTank::class)->execute(
                (int) $user?->current_team_id,
                (int) $this->refuelTankId,
                (float) $this->refuelQuantity,
                (float) ($this->refuelUnitPrice ?? 0),
                $this->refuelNotes ? strip_tags($this->refuelNotes) : null,
                $user?->id,
            );

            $message = 'Tank refueled successfully. Current level: '
                .number_format((float) $result['tank']->current_level_liters, 2).'L.';

            if ($result['overflow'] > 0) {
                $message .= ' ('.number_format($result['overflow'], 2).'L overflow was ignored)';
            }

            $this->notify($message, 'success');
            $this->reset(['refuelTankId', 'refuelQuantity', 'refuelUnitPrice', 'refuelNotes']);
        } catch (FuelDispenseException $e) {
            $this->notify($e->getMessage(), 'error');
        } catch (\Throwable $e) {
            Log::error('Failed to record refuel transaction', ['error' => $e->getMessage()]);
            $this->notify('Failed to record refuel transaction.', 'error');
        }
    }

    /**
     * @param  int|string  $tankId
     */
    public function openRefuelModal($tankId): void
    {
        $this->refuelTankId = (string) $tankId;
        $this->showRefuelModal = true;
    }

    public function closeRefuelModal(): void
    {
        $this->showRefuelModal = false;
        $this->reset(['refuelTankId', 'refuelQuantity', 'refuelUnitPrice', 'refuelNotes']);
    }

    /**
     * @param  int|string  $tankId
     */
    public function confirmDeleteTank($tankId): void
    {
        $this->confirmDeleteTankId = (int) $tankId;
        $this->showDeleteConfirm = true;
    }

    public function closeDeleteConfirm(): void
    {
        $this->showDeleteConfirm = false;
        $this->confirmDeleteTankId = null;
    }

    public function performDeleteConfirmed(): void
    {
        if (($this->confirmDeleteTankId !== null && $this->confirmDeleteTankId !== 0)) {
            $this->deleteTank($this->confirmDeleteTankId);
        }
        $this->closeDeleteConfirm();
    }

    /**
     * Permanently delete a tank. Caller should ensure confirmation on the frontend.
     *
     * @param  int|string  $tankId
     */
    public function deleteTank($tankId): void
    {
        $user = Auth::user();

        if (! $user instanceof User || ($user->current_team_id === null || $user->current_team_id === 0)) {
            $this->notify('Tank not found.', 'error');

            return;
        }

        $teamId = $user->current_team_id;

        $tank = FuelTank::where('team_id', $teamId)->find($tankId);
        if (! $tank) {
            $this->notify('Tank not found.', 'error');

            return;
        }

        try {
            // If model uses soft deletes this will soft delete; otherwise permanent remove
            $tank->delete();

            // Clear selection if the deleted tank was selected
            if ($this->transactionTankId == $tankId) {
                $this->reset(['transactionTankId', 'selectedTankId']);
            }

            $this->notify('Tank deleted successfully', 'success');
        } catch (\Exception $e) {
            Log::error('Failed to delete tank', ['error' => $e->getMessage()]);
            $this->notify('Failed to delete tank', 'error');
        }
    }

    public function saveAllocation(): void
    {
        $this->validate([
            'allocationYear' => 'required|integer|min:2020|max:2100',
            'allocationMonth' => 'required|integer|min:1|max:12',
            'mineAreaId' => 'required|exists:mine_areas,id',
            'allocatedLiters' => 'required|numeric|min:1|max:999999999',
            'fuelPricePerLiter' => 'required|numeric|min:0.01|max:999999',
            'allocationNotes' => 'nullable|string|max:1000',
        ]);

        $user = Auth::user();
        if (! $user instanceof User || ($user->current_team_id === null || $user->current_team_id === 0)) {
            $this->notify('User session invalid', 'error');

            return;
        }

        $teamId = $user->current_team_id;

        try {
            $totalBudget = ($this->allocatedLiters ?? 0.0) * ($this->fuelPricePerLiter ?? 0.0);

            $allocation = FuelMonthlyAllocation::updateOrCreate(
                [
                    'team_id' => $teamId,
                    'mine_area_id' => $this->mineAreaId,
                    'year' => $this->allocationYear,
                    'month' => $this->allocationMonth,
                ],
                [
                    'allocated_liters' => $this->allocatedLiters,
                    'fuel_price_per_liter' => $this->fuelPricePerLiter,
                    'total_budget_zar' => $totalBudget,
                    'remaining_liters' => $this->allocatedLiters,
                    'remaining_budget_zar' => $totalBudget,
                    'status' => 'active',
                    'notes' => strip_tags($this->allocationNotes), // Sanitize HTML
                ]
            );

            $allocation->updateConsumption();

            $this->notify('Monthly allocation saved successfully', 'success');
            // Allocation saved: continue to dispensing.
            $this->reset(['allocatedLiters', 'fuelPricePerLiter', 'allocationNotes']);
            $this->goToStep(3);

        } catch (\Exception $e) {
            Log::error('Failed to save fuel allocation', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            $this->notify('Failed to save allocation', 'error');
        }
    }

    public function render(): View
    {
        $teamId = auth()->user()?->current_team_id;

        // Get date range based on period
        $dateRange = $this->getDateRange();

        // Get current month allocation
        $currentAllocation = FuelMonthlyAllocation::query()->where('team_id', $teamId)
            ->where('year', now()->year)
            ->where('month', now()->month)
            ->with('mineArea')
            ->first();

        // Determine whether the current user can see inactive tanks (admins per-team)
        $currentUser = auth()->user();
        $canSeeInactive = $currentUser?->hasRole('admin') ?? false;

        // Tanks overview: include inactive tanks for admins, otherwise only active tanks
        $tanks = FuelTank::where('team_id', $teamId)
            ->when(! $canSeeInactive, fn (Builder $q): mixed => $q->where('status', 'active'))
            ->with('mineArea')
            ->when($this->showLowFuelOnly, fn (Builder $q): mixed => $q->lowFuel())
            ->get();

        // Machines for dispensing form
        $machines = Machine::query()->where('team_id', $teamId)->with('latestFuelMetric')->get()->sortBy('name')->values();

        // AI fuel insights only make sense once there is real fuel data to
        // analyse. With zero tanks and zero transactions the agent used to
        // emit a fabricated "Critical: inventory lasts 0 days (confidence
        // 88%)" recommendation -- alarming noise about a fuel system that
        // does not exist yet.
        $hasFuelData = $tanks->isNotEmpty()
            || FuelTransaction::query()->where('team_id', $teamId)->exists();

        $aiRecommendations = collect();
        $aiInsights = collect();
        $currentTeam = auth()->user()?->currentTeam;
        if ($hasFuelData && $currentTeam !== null) {
            $aiAnalysis = (new FuelPredictorAgent)->analyze($currentTeam);
            $aiRecommendations = collect($aiAnalysis['recommendations'])->take(5);
            $aiInsights = collect($aiAnalysis['insights'])->take(3);
        }

        $tankStats = [
            'total' => $tanks->count(),
            'active' => $tanks->where('status', 'active')->count(),
            'low_fuel' => $tanks->filter(fn ($t) => $t->isBelowMinimum())->count(),
            'critical' => $tanks->filter(fn ($t) => $t->isCritical())->count(),
            'total_capacity' => $tanks->sum('capacity_liters'),
            'current_level' => $tanks->sum('current_level_liters'),
        ];

        // Recent transactions
        $recentTransactions = FuelTransaction::where('team_id', $teamId)
            ->with(['fuelTank', 'machine', 'user'])
            ->whereBetween('transaction_date', [$dateRange['start'], $dateRange['end']])
            ->latest('transaction_date')
            ->limit(10)
            ->get();

        // Transaction statistics
        $transactionStats = [
            'total_refueled' => FuelTransaction::where('team_id', $teamId)
                ->whereBetween('transaction_date', [$dateRange['start'], $dateRange['end']])
                ->whereIn('transaction_type', ['refill', 'delivery'])
                ->sum('quantity_liters'),
            'total_consumed' => FuelTransaction::where('team_id', $teamId)
                ->whereBetween('transaction_date', [$dateRange['start'], $dateRange['end']])
                ->where('transaction_type', 'dispensing')
                ->sum('quantity_liters'),
            'total_cost' => FuelTransaction::where('team_id', $teamId)
                ->whereBetween('transaction_date', [$dateRange['start'], $dateRange['end']])
                ->sum('total_cost'),
            'transaction_count' => FuelTransaction::where('team_id', $teamId)
                ->whereBetween('transaction_date', [$dateRange['start'], $dateRange['end']])
                ->count(),
            // Previously invisible: theft/spillage were silently folded
            // into ordinary transaction totals with no way to see how much
            // fuel was actually lost, as opposed to legitimately used.
            'total_theft' => FuelTransaction::where('team_id', $teamId)
                ->whereBetween('transaction_date', [$dateRange['start'], $dateRange['end']])
                ->where('transaction_type', 'theft')
                ->sum('quantity_liters'),
            'total_spillage' => FuelTransaction::where('team_id', $teamId)
                ->whereBetween('transaction_date', [$dateRange['start'], $dateRange['end']])
                ->where('transaction_type', 'spillage')
                ->sum('quantity_liters'),
            'total_loss_cost' => FuelTransaction::where('team_id', $teamId)
                ->whereBetween('transaction_date', [$dateRange['start'], $dateRange['end']])
                ->whereIn('transaction_type', ['theft', 'spillage'])
                ->sum('total_cost'),
        ];

        // Active alerts
        $activeAlerts = FuelAlert::where('team_id', $teamId)
            ->with(['fuelTank', 'machine'])
            ->active()
            ->latest('triggered_at')
            ->limit(5)
            ->get();

        // Top consumers
        /**
         * @psalm-suppress InvalidTemplateParam, UndefinedMagicPropertyFetch
         * selectRaw() aggregate pseudo-columns (total_consumed/total_cost)
         * exist only on this query's row objects, not on the model.
         */
        $topConsumers = FuelTransaction::query()->where('team_id', $teamId)
            ->whereBetween('transaction_date', [$dateRange['start'], $dateRange['end']])
            ->where('transaction_type', 'dispensing')
            ->whereNotNull('machine_id')
            ->selectRaw('machine_id, SUM(quantity_liters) as total_consumed, SUM(total_cost) as total_cost')
            ->groupBy('machine_id')
            ->orderByDesc('total_consumed')
            ->limit(5)
            ->get()
            // The missing use($teamId) here was latent: this map only runs
            // once a dispensing transaction exists, and none could be
            // created until the guided workflow shipped -- the first real
            // dispense crashed the page.
            ->map(function ($item) use ($teamId) {
                $machine = Machine::query()->where('team_id', $teamId)->find($item->machine_id);

                return [
                    'machine' => $machine,
                    'total_consumed' => $item->total_consumed,
                    'total_cost' => $item->total_cost ?? 0,
                ];
            });

        $mineAreas = MineArea::where('team_id', $teamId)->orderBy('name')->get();

        // Per-machine fuel activity: which truck received fuel, how much,
        // when, from which tank -- manual dispensing records (entered by
        // people) shown separately from the machine's own telemetry fuel
        // level, never blended.
        $monthStart = now()->startOfMonth();

        // Per-machine dispensing aggregates in ONE grouped query (this map
        // used to run five queries per machine -- period sum, month sum,
        // event count, last dispense, latest fuel metric -- an N+1 that put
        // ~60 extra queries on every render of this page at fleet size).
        /** @psalm-suppress InvalidTemplateParam, UndefinedMagicPropertyFetch -- selectRaw aggregate pseudo-columns */
        $dispenseAggregates = FuelTransaction::query()->where('team_id', $teamId)
            ->where('transaction_type', 'dispensing')
            ->whereNotNull('machine_id')
            ->selectRaw(
                'machine_id, '
                .'SUM(CASE WHEN transaction_date BETWEEN ? AND ? THEN quantity_liters ELSE 0 END) as period_dispensed, '
                .'SUM(CASE WHEN transaction_date >= ? THEN quantity_liters ELSE 0 END) as month_dispensed, '
                .'SUM(CASE WHEN transaction_date BETWEEN ? AND ? THEN 1 ELSE 0 END) as event_count, '
                .'MAX(transaction_date) as latest_dispense_date',
                [$dateRange['start'], $dateRange['end'], $monthStart, $dateRange['start'], $dateRange['end']]
            )
            ->groupBy('machine_id')
            ->get()
            ->keyBy('machine_id');

        // The newest dispense row (with its tank) per machine, matched on the
        // MAX(transaction_date) pairs above -- ties resolved by unique(),
        // same arbitrary-pick behaviour latest()->first() had.
        /** @var Collection<int, FuelTransaction> $lastDispenses */
        $lastDispenses = collect();
        if ($dispenseAggregates->isNotEmpty()) {
            $lastDispenses = FuelTransaction::query()->where('team_id', $teamId)
                ->where('transaction_type', 'dispensing')
                ->with('fuelTank')
                ->where(function (Builder $query) use ($dispenseAggregates) {
                    foreach ($dispenseAggregates as $machineId => $aggregate) {
                        $query->orWhere(function (Builder $pair) use ($machineId, $aggregate) {
                            $pair->where('machine_id', $machineId)
                                ->where('transaction_date', data_get($aggregate, 'latest_dispense_date'));
                        });
                    }
                })
                ->get()
                ->unique('machine_id')
                ->keyBy('machine_id');
        }

        /**
         * @psalm-suppress InvalidTemplateParam -- map() to plain arrays off an Eloquent collection
         */
        $machineFuelActivity = $machines->map(function (Machine $machine) use ($dispenseAggregates, $lastDispenses) {
            $aggregate = $dispenseAggregates->get($machine->id);
            $latestFuelMetric = $machine->latestFuelMetric;

            return [
                'machine' => $machine,
                'period_dispensed' => (float) data_get($aggregate, 'period_dispensed', 0),
                'month_dispensed' => (float) data_get($aggregate, 'month_dispensed', 0),
                'event_count' => (int) data_get($aggregate, 'event_count', 0),
                'last_dispense' => $lastDispenses->get($machine->id),
                'telemetry_fuel_level' => $latestFuelMetric?->fuel_level,
                'telemetry_recorded_at' => $latestFuelMetric?->recorded_at,
            ];
        })->filter(function (array $row) {
            // Only rows with something to say: a dispensing history or a
            // live telemetry fuel reading.
            return $row['month_dispensed'] > 0
                || $row['period_dispensed'] > 0
                || $row['telemetry_fuel_level'] !== null;
        })->sortByDesc('period_dispensed')->values();

        return view('livewire.fuel-management', [
            'machineFuelActivity' => $machineFuelActivity,
            'tanks' => $tanks,
            'machines' => $machines,
            'tankStats' => $tankStats,
            'recentTransactions' => $recentTransactions,
            'transactionStats' => $transactionStats,
            'activeAlerts' => $activeAlerts,
            'topConsumers' => $topConsumers,
            'currentAllocation' => $currentAllocation,
            'aiRecommendations' => $aiRecommendations,
            'aiInsights' => $aiInsights,
            'mineAreas' => $mineAreas,
            'canSeeInactiveTanks' => $canSeeInactive,
            // Aggregates (sums across a team's own transactions) are labelled
            // with the team's currency preference, since fuel_transactions
            // are now stamped with it at creation time. Individual
            // transactions still carry their own real currency and should
            // use that instead -- see $transaction->currency in the view.
            'teamCurrency' => auth()->user()->currentTeam->currency ?? 'ZAR',
        ]);
    }

    /**
     * @return array{start: CarbonInterface, end: CarbonInterface}
     */
    protected function getDateRange(): array
    {
        return match ($this->selectedPeriod) {
            'today' => ['start' => now()->startOfDay(), 'end' => now()->endOfDay()],
            'week' => ['start' => now()->startOfWeek(), 'end' => now()->endOfWeek()],
            'month' => ['start' => now()->startOfMonth(), 'end' => now()->endOfMonth()],
            'quarter' => ['start' => now()->startOfQuarter(), 'end' => now()->endOfQuarter()],
            'year' => ['start' => now()->startOfYear(), 'end' => now()->endOfYear()],
            default => ['start' => now()->startOfWeek(), 'end' => now()->endOfWeek()],
        };
    }
}
