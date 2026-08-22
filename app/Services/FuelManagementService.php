<?php

namespace App\Services;

use App\Models\FuelAlert;
use App\Models\FuelBudget;
use App\Models\FuelConsumptionMetric;
use App\Models\FuelTank;
use App\Models\FuelTransaction;
use App\Models\Machine;
use App\Models\Team;
use App\Support\Currency;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class FuelManagementService
{
    /**
     * Record a fuel transaction and update tank levels
     *
     * @param  array<string, mixed>  $data
     */
    public function recordTransaction(array $data): FuelTransaction
    {
        return DB::transaction(function () use ($data) {
            // currency wasn't in $fillable until it was wired up to
            // Team::currency (see Team::$fillable) -- default new
            // transactions to the team's chosen currency rather than the
            // fuel_transactions column's hardcoded 'ZAR' default.
            if (! isset($data['currency']) && isset($data['team_id'])) {
                $data['currency'] = Team::find($data['team_id'])?->currency ?? 'ZAR';
            }

            $transaction = FuelTransaction::create($data);

            // Update tank level based on transaction type
            if ($transaction->fuel_tank_id) {
                $this->updateTankLevel($transaction);
            }

            // Check for alerts after transaction
            $this->checkAndCreateAlerts($transaction);

            // Update budget if applicable
            if ($transaction->total_cost) {
                $this->updateBudget($transaction);
            }

            return $transaction->load(['fuelTank', 'machine', 'user']);
        });
    }

    /**
     * Update fuel tank level based on transaction
     */
    protected function updateTankLevel(FuelTransaction $transaction): void
    {
        $tank = $transaction->fuelTank;

        if ($tank === null && $transaction->transaction_type !== 'transfer') {
            // Transfers carry their own from/to tank ids; every other type
            // needs the owning tank to exist to have any level to adjust.
            return;
        }

        switch ($transaction->transaction_type) {
            case 'refill':
            case 'delivery':
                $tank?->increment('current_level_liters', (float) $transaction->quantity_liters);
                break;

            case 'dispensing':
            case 'spillage':
            case 'theft':
                $tank?->decrement('current_level_liters', (float) $transaction->quantity_liters);
                break;

            case 'adjustment':
                if ($tank !== null) {
                    $tank->current_level_liters = (float) $transaction->quantity_liters;
                    $tank->save();
                }
                break;

            case 'transfer':
                if ($transaction->from_tank_id) {
                    FuelTank::query()->find($transaction->from_tank_id)
                        ?->decrement('current_level_liters', (float) $transaction->quantity_liters);
                }
                if ($transaction->to_tank_id) {
                    FuelTank::query()->find($transaction->to_tank_id)
                        ?->increment('current_level_liters', (float) $transaction->quantity_liters);
                }
                break;
        }
    }

    /**
     * Check and create fuel alerts
     */
    protected function checkAndCreateAlerts(FuelTransaction $transaction): void
    {
        // Check tank level alerts
        $tank = $transaction->fuel_tank_id ? $transaction->fuelTank : null;

        if ($tank !== null) {
            if ($tank->isCritical()) {
                $this->createFuelAlert([
                    'team_id' => $tank->team_id,
                    'fuel_tank_id' => $tank->id,
                    'alert_type' => 'tank_critical',
                    'title' => "Critical Fuel Level: {$tank->name}",
                    'message' => "Fuel tank {$tank->name} is critically low at {$tank->fill_percentage}%",
                    'severity' => 'critical',
                ]);
            } elseif ($tank->isBelowMinimum()) {
                $this->createFuelAlert([
                    'team_id' => $tank->team_id,
                    'fuel_tank_id' => $tank->id,
                    'alert_type' => 'tank_low',
                    'title' => "Low Fuel Level: {$tank->name}",
                    'message' => "Fuel tank {$tank->name} is below minimum level at {$tank->current_level_liters}L",
                    'severity' => 'warning',
                ]);
            }
        }

        // Check machine fuel consumption patterns
        if ($transaction->machine_id && $transaction->transaction_type === 'dispensing') {
            $this->checkMachineConsumptionPatterns($transaction);
        }

        // Theft/spillage used to just silently decrement the tank level,
        // exactly like a normal dispensing entry -- nobody was ever
        // notified a real loss had actually been logged.
        if (in_array($transaction->transaction_type, ['theft', 'spillage'], true)) {
            $this->createFuelLossAlert($transaction);
        }
    }

    /**
     * Fires for every theft/spillage transaction. Deliberately does not go
     * through createFuelAlert()'s dedup (one alert per type per team per
     * 24h) -- that rule makes sense for a recurring condition like a low
     * tank, but a second real loss report the same day, possibly at a
     * different tank or machine, is exactly the kind of thing that must
     * never be silently suppressed.
     */
    protected function createFuelLossAlert(FuelTransaction $transaction): FuelAlert
    {
        $label = $transaction->transaction_type === 'theft' ? 'Theft' : 'Spillage';
        $location = $transaction->fuelTank?->name ?? $transaction->machine?->name ?? 'an unrecorded location';
        $quantity = number_format((float) $transaction->quantity_liters, 2);
        $costSuffix = $transaction->total_cost
            ? ' ('.Currency::format($transaction->total_cost, $transaction->currency ?? 'ZAR').')'
            : '';

        return FuelAlert::create([
            'team_id' => $transaction->team_id,
            'fuel_tank_id' => $transaction->fuel_tank_id,
            'machine_id' => $transaction->machine_id,
            'alert_type' => 'fuel_loss_reported',
            'title' => "Fuel {$label} Reported: {$location}",
            'message' => "{$quantity}L reported as {$transaction->transaction_type} at {$location}{$costSuffix}.",
            'severity' => $transaction->transaction_type === 'theft' ? 'critical' : 'warning',
            'status' => 'active',
            'triggered_at' => now(),
            'metadata' => [
                'fuel_transaction_id' => $transaction->id,
                'transaction_type' => $transaction->transaction_type,
                'quantity_liters' => (float) $transaction->quantity_liters,
                'total_cost' => $transaction->total_cost !== null ? (float) $transaction->total_cost : null,
            ],
        ]);
    }

    /**
     * Check for unusual machine fuel consumption patterns
     */
    protected function checkMachineConsumptionPatterns(FuelTransaction $transaction): void
    {
        $machine = $transaction->machine;

        if ($machine === null) {
            return;
        }

        // Get average daily consumption for this machine
        $avgConsumption = FuelConsumptionMetric::where('machine_id', $machine->id)
            ->where('date', '>=', now()->subDays(30))
            ->avg('fuel_consumed_liters');

        if ($avgConsumption && $transaction->quantity_liters > ($avgConsumption * 1.5)) {
            $this->createFuelAlert([
                'team_id' => $machine->team_id,
                'machine_id' => $machine->id,
                'alert_type' => 'high_consumption',
                'title' => "High Fuel Consumption: {$machine->name}",
                'message' => "Machine consumed {$transaction->quantity_liters}L, significantly higher than 30-day average of {$avgConsumption}L",
                'severity' => 'warning',
            ]);
        }
    }

    /**
     * Create fuel alert (avoid duplicates)
     *
     * @param  array<string, mixed>  $data
     */
    protected function createFuelAlert(array $data): ?FuelAlert
    {
        // Check if similar alert exists in last 24 hours
        $existing = FuelAlert::where('team_id', $data['team_id'])
            ->where('alert_type', $data['alert_type'])
            ->where('status', 'active')
            ->where('triggered_at', '>=', now()->subDay())
            ->first();

        if ($existing) {
            return null;
        }

        $data['triggered_at'] = now();
        $data['status'] = 'active';

        return FuelAlert::create($data);
    }

    /**
     * Update fuel budget with transaction
     */
    protected function updateBudget(FuelTransaction $transaction): void
    {
        $budget = FuelBudget::where('team_id', $transaction->team_id)
            ->active()
            ->current()
            ->first();

        if ($budget) {
            $budget->increment('actual_spent', $transaction->total_cost);
            $budget->increment('actual_liters', $transaction->quantity_liters);

            // Update status if exceeded
            if ($budget->isExceeded() && $budget->status !== 'exceeded') {
                $budget->update(['status' => 'exceeded']);

                // Create budget alert
                $this->createFuelAlert([
                    'team_id' => $budget->team_id,
                    'alert_type' => 'unusual_pattern',
                    'title' => 'Fuel Budget Exceeded',
                    'message' => "Fuel budget for {$budget->period_type} period has been exceeded",
                    'severity' => 'critical',
                ]);
            }
        }
    }

    /**
     * Calculate daily fuel consumption metrics for a machine
     *
     * @psalm-suppress PossiblyUnusedMethod -- exercised only by its test today; kept as covered public API
     */
    public function calculateDailyMetrics(Machine $machine, Carbon $date): FuelConsumptionMetric
    {
        $startOfDay = $date->copy()->startOfDay();
        $endOfDay = $date->copy()->endOfDay();

        // Get all fuel transactions for this machine on this date
        $transactions = FuelTransaction::where('machine_id', $machine->id)
            ->where('transaction_type', 'dispensing')
            ->whereBetween('transaction_date', [$startOfDay, $endOfDay])
            ->get();

        $totalFuel = $transactions->sum('quantity_liters');

        // operating_hours / idle_hours on machine_metrics are cumulative
        // meters (MachinePerformanceService::dayDelta() semantics): the
        // day's hours are the counter DELTA across the day's readings.
        // This used to store the day's first RAW meter reading -- a
        // machine's lifetime hours -- as its daily operating hours, which
        // poisoned fuel_efficiency_lph at the source. It also read
        // $metrics->idle_time, a column that does not exist (idle_hours is
        // the real one), so idle figures were always null. Readings are
        // selected by recorded_at (telemetry time), not created_at (row
        // insert time, which lags the sync).
        $dayMetrics = $machine->metrics()
            ->whereBetween('recorded_at', [$startOfDay, $endOfDay])
            ->get();

        $operatingHours = $this->counterDelta($dayMetrics, 'operating_hours');
        $idleTime = $this->counterDelta($dayMetrics, 'idle_hours');

        $data = [
            'team_id' => $machine->team_id,
            'machine_id' => $machine->id,
            'date' => $date->toDateString(),
            'fuel_consumed_liters' => $totalFuel,
            'operating_hours' => $operatingHours,
            'idle_time_hours' => $idleTime,
        ];

        // Calculate efficiency if we have operating hours
        if ($operatingHours && $operatingHours > 0) {
            $data['fuel_efficiency_lph'] = round($totalFuel / $operatingHours, 4);
        }

        // Calculate idle fuel consumption estimate (25% of total if idling)
        if ($idleTime && $idleTime > 0) {
            $data['idle_fuel_consumed'] = round($totalFuel * 0.25, 2);
        }

        return FuelConsumptionMetric::updateOrCreate(
            [
                'machine_id' => $machine->id,
                'date' => $date->toDateString(),
            ],
            $data
        );
    }

    /**
     * Delta of a cumulative meter column across a set of readings; null when
     * fewer than two readings exist (one meter snapshot is not a duration).
     *
     * @param  Collection<int, mixed>  $metrics
     */
    private function counterDelta($metrics, string $column): ?float
    {
        $readings = $metrics->pluck($column)
            ->filter(fn ($value) => $value !== null)
            ->map(fn ($value) => (float) $value);

        return $readings->count() >= 2
            ? max(0.0, $readings->max() - $readings->min())
            : null;
    }

    /**
     * Get fuel analytics for team
     *
     * @return array<string, mixed>
     */
    public function getTeamAnalytics(int $teamId, Carbon $startDate, Carbon $endDate): array
    {
        // Total fuel removed from tanks -- dispensing (legitimate use) plus
        // theft/spillage (loss). Kept as-is for anything relying on this
        // matching real tank drawdown; the loss portion used to be silently
        // lumped in here with no separate visibility at all.
        $totalConsumed = FuelTransaction::where('team_id', $teamId)
            ->whereIn('transaction_type', ['dispensing', 'spillage', 'theft'])
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->sum('quantity_liters');

        // Legitimate consumption vs. loss, broken out separately -- a team
        // could not previously see "how much fuel did we actually lose to
        // theft/spillage this period" anywhere; it was invisible inside the
        // total above.
        $dispensedTotal = FuelTransaction::where('team_id', $teamId)
            ->where('transaction_type', 'dispensing')
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->sum('quantity_liters');

        $lossByType = FuelTransaction::where('team_id', $teamId)
            ->whereIn('transaction_type', ['theft', 'spillage'])
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->select('transaction_type', DB::raw('SUM(quantity_liters) as liters'), DB::raw('SUM(total_cost) as cost'))
            ->groupBy('transaction_type')
            ->get()
            ->keyBy('transaction_type');

        $theftLiters = round((float) ($lossByType->get('theft')->liters ?? 0), 2);
        $theftCost = round((float) ($lossByType->get('theft')->cost ?? 0), 2);
        $spillageLiters = round((float) ($lossByType->get('spillage')->liters ?? 0), 2);
        $spillageCost = round((float) ($lossByType->get('spillage')->cost ?? 0), 2);

        $losses = [
            'theft' => ['liters' => $theftLiters, 'cost' => $theftCost],
            'spillage' => ['liters' => $spillageLiters, 'cost' => $spillageCost],
            'total_liters' => round($theftLiters + $spillageLiters, 2),
            'total_cost' => round($theftCost + $spillageCost, 2),
            // What share of everything removed from a tank was loss rather
            // than legitimate use -- 0 when nothing at all was recorded.
            'percent_of_total' => $totalConsumed > 0
                ? round((($theftLiters + $spillageLiters) / $totalConsumed) * 100, 1)
                : 0,
        ];

        // Total cost
        $totalCost = FuelTransaction::where('team_id', $teamId)
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->sum('total_cost');

        // Average price per liter
        $avgPrice = $totalConsumed > 0 ? round($totalCost / $totalConsumed, 2) : 0;

        // Consumption by machine
        $consumptionByMachine = FuelTransaction::where('team_id', $teamId)
            ->whereNotNull('machine_id')
            ->where('transaction_type', 'dispensing')
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->select('machine_id', DB::raw('SUM(quantity_liters) as total_fuel'))
            ->groupBy('machine_id')
            ->with('machine:id,name')
            ->get()
            ->map(function ($item) {
                return [
                    'machine_id' => $item->machine_id,
                    'machine_name' => $item->machine->name ?? 'Unknown',
                    'total_fuel' => round($item->total_fuel, 2),
                ];
            });

        // Daily consumption trend
        $dailyTrend = FuelTransaction::where('team_id', $teamId)
            ->whereIn('transaction_type', ['dispensing'])
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->select(DB::raw('DATE(transaction_date) as date'), DB::raw('SUM(quantity_liters) as total'))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Active alerts
        $activeAlerts = FuelAlert::where('team_id', $teamId)
            ->active()
            ->count();

        // Tank status
        $tankStatus = FuelTank::where('team_id', $teamId)
            ->active()
            ->get()
            ->map(function ($tank) {
                return [
                    'id' => $tank->id,
                    'name' => $tank->name,
                    'fill_percentage' => $tank->fill_percentage,
                    'current_level' => $tank->current_level_liters,
                    'capacity' => $tank->capacity_liters,
                    'status' => $tank->isCritical() ? 'critical' : ($tank->isBelowMinimum() ? 'low' : 'normal'),
                ];
            });

        // Budget status
        $currentBudget = FuelBudget::where('team_id', $teamId)
            ->active()
            ->current()
            ->first();

        $budgetStatus = null;
        if ($currentBudget) {
            $budgetStatus = [
                'budgeted_amount' => $currentBudget->budgeted_amount,
                'actual_spent' => $currentBudget->actual_spent,
                'remaining' => $currentBudget->remaining_budget,
                'utilization' => $currentBudget->budget_utilization,
                'status' => $currentBudget->status,
            ];
        }

        return [
            'period' => [
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
            ],
            'totals' => [
                'fuel_consumed' => round($totalConsumed, 2),
                // Legitimate dispensing only -- fuel_consumed above still
                // includes loss, kept for backward compatibility with
                // anything relying on it matching real tank drawdown.
                'fuel_dispensed' => round($dispensedTotal, 2),
                'total_cost' => round($totalCost, 2),
                'average_price_per_liter' => $avgPrice,
            ],
            // Broken out from 'totals' above -- previously invisible,
            // silently folded into fuel_consumed with no way to see how
            // much was actually lost vs. legitimately used.
            'losses' => $losses,
            'consumption_by_machine' => $consumptionByMachine,
            'daily_trend' => $dailyTrend,
            'tank_status' => $tankStatus,
            'active_alerts' => $activeAlerts,
            'budget_status' => $budgetStatus,
        ];
    }
}
