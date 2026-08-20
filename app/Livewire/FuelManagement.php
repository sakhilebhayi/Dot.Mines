<?php

namespace App\Livewire;

use App\Models\FuelAlert;
use App\Models\FuelMonthlyAllocation;
use App\Models\FuelTank;
use App\Models\FuelTransaction;
use App\Models\Machine;
use App\Models\MineArea;
use App\Services\AI\FuelPredictorAgent;
use App\Services\FuelManagementService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

class FuelManagement extends Component
{
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

    public function recordDispensingTransaction()
    {
        $this->transactionError = '';
        $this->validate([
            'transactionTankId' => 'required|exists:fuel_tanks,id',
            'transactionQuantity' => 'required|numeric|min:1',
        ]);
        $user = Auth::user();
        $teamId = $user?->current_team_id;

        $tank = FuelTank::where('team_id', $teamId)->find($this->transactionTankId);
        if (! $tank) {
            $this->transactionError = 'Selected tank not found.';
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Selected tank not found.']);

            return;
        }

        $year = now()->year;
        $month = now()->month;

        // Determine mine area for the transaction. Prefer the machine's assigned
        // mine area (user selects a machine), otherwise fall back to the tank's
        // mine area. This ensures allocations are looked up for the correct area.
        $mineAreaId = null;
        if (! empty($this->transactionMachineId)) {
            $machine = Machine::where('team_id', $tank->team_id)->find($this->transactionMachineId);
            // Ensure the referenced machine belongs to the same team as the tank
            if ($machine) {
                $mineAreaId = $machine->mine_area_id;
            }
        }
        if (is_null($mineAreaId)) {
            $mineAreaId = $tank->mine_area_id;
        }

        // Prefer an allocation scoped to the specific mine area, but fall back
        // to a team-level (general) allocation when none exists for the area.
        $allocation = null;
        if (! is_null($mineAreaId)) {
            $allocation = FuelMonthlyAllocation::where('team_id', $tank->team_id)
                ->where('year', $year)
                ->where('month', $month)
                ->where('mine_area_id', $mineAreaId)
                ->first();
        }

        if (! $allocation) {
            // Try a team-level allocation (mine_area_id IS NULL)
            $allocation = FuelMonthlyAllocation::where('team_id', $tank->team_id)
                ->whereNull('mine_area_id')
                ->where('year', $year)
                ->where('month', $month)
                ->first();

            if ($allocation) {
                Log::info('Falling back to team-level fuel allocation for dispense', [
                    'team_id' => $tank->team_id,
                    'requested_mine_area_id' => $mineAreaId,
                    'allocation_id' => $allocation->id,
                    'year' => $year,
                    'month' => $month,
                ]);
            }
        }

        if (! $allocation) {
            $this->transactionError = 'No monthly allocation set for this mine area.';
            $this->dispatch('notify', ['type' => 'error', 'message' => 'No monthly allocation set for this mine area.']);

            return;
        }

        $remaining = $allocation->remaining_liters;
        if ($this->transactionQuantity > $remaining) {
            $this->transactionError = 'Dispensing this amount would exceed the monthly allocation for this mine area. Remaining: '.number_format($remaining, 2).'L.';
            $this->dispatch('notify', ['type' => 'error', 'message' => $this->transactionError]);

            return;
        }

        // Build transaction payload and record via service
        $unitPrice = $allocation->fuel_price_per_liter ?? 0;
        $totalCost = round($unitPrice * $this->transactionQuantity, 2);

        $service = new FuelManagementService;
        try {
            $transaction = $service->recordTransaction([
                'team_id' => $tank->team_id,
                'fuel_tank_id' => $tank->id,
                'machine_id' => $this->transactionMachineId ?: null,
                'user_id' => auth()->id(),
                'transaction_type' => 'dispensing',
                'quantity_liters' => $this->transactionQuantity,
                'unit_price' => $unitPrice,
                'total_cost' => $totalCost,
                'fuel_type' => $tank->fuel_type,
                'transaction_date' => now(),
                'monthly_allocation_id' => $allocation->id ?? null,
                'notes' => null,
            ]);

            // Refresh allocation consumption
            if ($allocation) {
                $allocation->updateConsumption();
            }

            $this->dispatch('notify', ['type' => 'success', 'message' => 'Dispensing transaction recorded.']);
            $this->reset(['transactionTankId', 'transactionQuantity', 'transactionMachineId']);
            // Show the result of what was just dispensed.
            $this->goToStep(4);

        } catch (\Throwable $e) {
            Log::error('Failed to record dispensing transaction', ['error' => $e->getMessage()]);
            $this->transactionError = "We couldn't record this transaction. Please try again.";
            $this->dispatch('notify', ['type' => 'error', 'message' => $this->transactionError]);
        }
    }

    public function mount()
    {
        $this->allocationYear = now()->year;
        $this->allocationMonth = now()->month;
    }

    /**
     * Open the guided fuel workflow. With no argument it opens at the first
     * step that still needs doing (no tanks -> Tank, no allocation for this
     * month -> Allocation, otherwise Dispense). Legacy tab names are mapped
     * so existing callers keep working.
     */
    public function openManageModal($tab = null)
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

    public function closeManageModal()
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
                $this->dispatch('notify', ['type' => 'info', 'message' => 'Add a fuel tank first.']);
            }

            return;
        }

        if ($step === 3 && ! $this->hasCurrentAllocation()) {
            $this->manageStep = 2;
            $this->dispatch('notify', ['type' => 'info', 'message' => 'Set this month\'s allocation before dispensing.']);

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
        return FuelTank::where('team_id', auth()->user()->current_team_id)
            ->where('status', 'active')
            ->exists();
    }

    private function hasCurrentAllocation(): bool
    {
        return FuelMonthlyAllocation::where('team_id', auth()->user()->current_team_id)
            ->where('year', now()->year)
            ->where('month', now()->month)
            ->exists();
    }

    public function saveTank()
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
        if (! $user || ! $user->current_team_id) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'User session invalid']);

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
            $this->transactionTankId = $tank->id;
            $this->selectedTankId = $tank->id;

            $this->dispatch('notify', ['type' => 'success', 'message' => 'Fuel tank created successfully']);
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

            $this->dispatch('notify', ['type' => 'error', 'message' => 'Failed to create tank']);
        }
    }

    /**
     * Refuel (record a refill/delivery) for a tank and update its current level.
     */
    public function refuelTank()
    {
        $this->validate([
            'refuelTankId' => 'required|exists:fuel_tanks,id',
            'refuelQuantity' => 'required|numeric|min:0.01',
            'refuelUnitPrice' => 'nullable|numeric|min:0',
        ]);

        $user = Auth::user();
        $teamId = $user?->current_team_id;

        $tank = FuelTank::where('team_id', $teamId)->find($this->refuelTankId);
        if (! $tank) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Selected tank not found.']);

            return;
        }

        $quantity = (float) $this->refuelQuantity;
        $unitPrice = $this->refuelUnitPrice ?? 0;

        $service = new FuelManagementService;
        try {
            $transaction = $service->recordTransaction([
                'team_id' => $tank->team_id,
                'fuel_tank_id' => $tank->id,
                'machine_id' => null,
                'user_id' => auth()->id(),
                'transaction_type' => 'refill',
                'quantity_liters' => $quantity,
                'unit_price' => $unitPrice,
                'total_cost' => round($unitPrice * $quantity, 2),
                'fuel_type' => $tank->fuel_type,
                'transaction_date' => now(),
                'monthly_allocation_id' => null,
                'notes' => $this->refuelNotes ? strip_tags($this->refuelNotes) : null,
            ]);

            // Increase tank level but do not exceed capacity
            $newLevel = $tank->current_level_liters + $quantity;
            $overflow = 0;
            if ($newLevel > $tank->capacity_liters) {
                $overflow = $newLevel - $tank->capacity_liters;
                $newLevel = $tank->capacity_liters;
            }

            $tank->current_level_liters = $newLevel;
            $tank->save();

            $message = 'Tank refueled successfully. Current level: '.number_format($tank->current_level_liters, 2).'L.';
            if ($overflow > 0) {
                $message .= ' ('.number_format($overflow, 2).'L overflow was ignored)';
            }

            $this->dispatch('notify', ['type' => 'success', 'message' => $message]);
            $this->reset(['refuelTankId', 'refuelQuantity', 'refuelUnitPrice', 'refuelNotes']);

        } catch (\Exception $e) {
            Log::error('Failed to record refuel transaction', ['error' => $e->getMessage()]);
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Failed to record refuel transaction.']);
        }
    }

    public function openRefuelModal($tankId)
    {
        $this->refuelTankId = $tankId;
        $this->showRefuelModal = true;
    }

    public function closeRefuelModal()
    {
        $this->showRefuelModal = false;
        $this->reset(['refuelTankId', 'refuelQuantity', 'refuelUnitPrice', 'refuelNotes']);
    }

    public function confirmDeleteTank($tankId)
    {
        $this->confirmDeleteTankId = $tankId;
        $this->showDeleteConfirm = true;
    }

    public function closeDeleteConfirm()
    {
        $this->showDeleteConfirm = false;
        $this->confirmDeleteTankId = null;
    }

    public function performDeleteConfirmed()
    {
        if ($this->confirmDeleteTankId) {
            $this->deleteTank($this->confirmDeleteTankId);
        }
        $this->closeDeleteConfirm();
    }

    /**
     * Permanently delete a tank. Caller should ensure confirmation on the frontend.
     */
    public function deleteTank($tankId)
    {
        $user = Auth::user();
        $teamId = $user?->current_team_id;

        $tank = FuelTank::where('team_id', $teamId)->find($tankId);
        if (! $tank) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Tank not found.']);

            return;
        }

        try {
            // If model uses soft deletes this will soft delete; otherwise permanent remove
            $tank->delete();

            // Clear selection if the deleted tank was selected
            if ($this->transactionTankId == $tankId) {
                $this->reset(['transactionTankId', 'selectedTankId']);
            }

            $this->dispatch('notify', ['type' => 'success', 'message' => 'Tank deleted successfully']);
        } catch (\Exception $e) {
            Log::error('Failed to delete tank', ['error' => $e->getMessage()]);
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Failed to delete tank']);
        }
    }

    public function saveAllocation()
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
        if (! $user || ! $user->current_team_id) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'User session invalid']);

            return;
        }

        $teamId = $user->current_team_id;

        try {
            $totalBudget = $this->allocatedLiters * $this->fuelPricePerLiter;

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

            $this->dispatch('notify', ['type' => 'success', 'message' => 'Monthly allocation saved successfully']);
            // Allocation saved: continue to dispensing.
            $this->reset(['allocatedLiters', 'fuelPricePerLiter', 'allocationNotes']);
            $this->goToStep(3);

        } catch (\Exception $e) {
            Log::error('Failed to save fuel allocation', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            $this->dispatch('notify', ['type' => 'error', 'message' => 'Failed to save allocation']);
        }
    }

    public function render()
    {
        $teamId = auth()->user()->current_team_id;

        // Get date range based on period
        $dateRange = $this->getDateRange();

        // Get current month allocation
        $currentAllocation = FuelMonthlyAllocation::where('team_id', $teamId)
            ->where('year', now()->year)
            ->where('month', now()->month)
            ->with('mineArea')
            ->first();

        // Determine whether the current user can see inactive tanks (admins per-team)
        $currentUser = auth()->user();
        $canSeeInactive = $currentUser?->hasRole('admin') ?? false;

        // Tanks overview: include inactive tanks for admins, otherwise only active tanks
        $tanks = FuelTank::where('team_id', $teamId)
            ->when(! $canSeeInactive, fn ($q) => $q->where('status', 'active'))
            ->with('mineArea')
            ->when($this->showLowFuelOnly, fn ($q) => $q->lowFuel())
            ->get();

        // Machines for dispensing form
        $machines = Machine::query()->where('team_id', $teamId)->get()->sortBy('name')->values();

        // AI fuel insights only make sense once there is real fuel data to
        // analyse. With zero tanks and zero transactions the agent used to
        // emit a fabricated "Critical: inventory lasts 0 days (confidence
        // 88%)" recommendation -- alarming noise about a fuel system that
        // does not exist yet.
        $hasFuelData = $tanks->isNotEmpty()
            || FuelTransaction::where('team_id', $teamId)->exists();

        $aiRecommendations = collect();
        $aiInsights = collect();
        if ($hasFuelData) {
            $aiAnalysis = (new FuelPredictorAgent)->analyze(auth()->user()->currentTeam);
            $aiRecommendations = collect($aiAnalysis['recommendations'] ?? [])->take(5);
            $aiInsights = collect($aiAnalysis['insights'] ?? [])->take(3);
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
        $topConsumers = FuelTransaction::where('team_id', $teamId)
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
                $machine = Machine::where('team_id', $teamId)->find($item->machine_id);

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
        $machineFuelActivity = $machines->map(function (Machine $machine) use ($teamId, $dateRange, $monthStart) {
            $base = FuelTransaction::where('team_id', $teamId)
                ->where('machine_id', $machine->id)
                ->where('transaction_type', 'dispensing');

            $periodDispensed = (clone $base)
                ->whereBetween('transaction_date', [$dateRange['start'], $dateRange['end']])
                ->sum('quantity_liters');
            $monthDispensed = (clone $base)
                ->where('transaction_date', '>=', $monthStart)
                ->sum('quantity_liters');
            $lastDispense = (clone $base)->with('fuelTank')->latest('transaction_date')->first();

            $latestFuelMetric = $machine->metrics()
                ->whereNotNull('fuel_level')
                ->latest('recorded_at')
                ->first();

            return [
                'machine' => $machine,
                'period_dispensed' => (float) $periodDispensed,
                'month_dispensed' => (float) $monthDispensed,
                'event_count' => (clone $base)
                    ->whereBetween('transaction_date', [$dateRange['start'], $dateRange['end']])
                    ->count(),
                'last_dispense' => $lastDispense,
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

    protected function getDateRange()
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
