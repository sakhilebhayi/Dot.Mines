<?php

namespace App\Services;

use App\Models\FuelAlert;
use App\Models\FuelBudget;
use App\Models\FuelConsumptionMetric;
use App\Models\FuelTank;
use App\Models\FuelTransaction;
use App\Models\Machine;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class FuelManagementService
{
    /**
     * Record a fuel transaction and update tank levels
     */
    /** @param array<string, mixed> $data */
    public function recordTransaction(array $data): FuelTransaction
    {
        return DB::transaction(function () use ($data) {
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

        if ($tank === null) {
            return;
        }

        switch ($transaction->transaction_type) {
            case 'refill':
            case 'delivery':
                $tank->increment('current_level_liters', $transaction->quantity_liters);
                break;

            case 'dispensing':
            case 'spillage':
            case 'theft':
                $tank->decrement('current_level_liters', $transaction->quantity_liters);
                break;

            case 'adjustment':
                $tank->current_level_liters = $transaction->quantity_liters;
                $tank->save();
                break;

            case 'transfer':
                if ($transaction->from_tank_id) {
                    $fromTank = FuelTank::find($transaction->from_tank_id);
                    if ($fromTank !== null) {
                        $fromTank->decrement('current_level_liters', $transaction->quantity_liters);
                    }
                }
                if ($transaction->to_tank_id) {
                    $toTank = FuelTank::find($transaction->to_tank_id);
                    if ($toTank !== null) {
                        $toTank->increment('current_level_liters', $transaction->quantity_liters);
                    }
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
        if ($transaction->fuel_tank_id) {
            $tank = $transaction->fuelTank;

            if ($tank === null) {
                return;
            }

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
     */
    /** @param array<string, mixed> $data */
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

        // Get machine metrics for operating hours (if available)
        $metrics = $machine->metrics()
            ->whereBetween('created_at', [$startOfDay, $endOfDay])
            ->first();

        $operatingHours = $metrics->operating_hours ?? null;
        $idleTime = $metrics->idle_time ?? null;

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
     * Get fuel analytics for team
     *
     * @return array<mixed>
     */
    public function getTeamAnalytics(int $teamId, Carbon $startDate, Carbon $endDate): array
    {
        // Total fuel consumed
        $totalConsumed = FuelTransaction::where('team_id', $teamId)
            ->whereIn('transaction_type', ['dispensing', 'spillage', 'theft'])
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->sum('quantity_liters');

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
                'total_cost' => round($totalCost, 2),
                'average_price_per_liter' => $avgPrice,
            ],
            'consumption_by_machine' => $consumptionByMachine,
            'daily_trend' => $dailyTrend,
            'tank_status' => $tankStatus,
            'active_alerts' => $activeAlerts,
            'budget_status' => $budgetStatus,
        ];
    }

    /**
     * Get machine fuel efficiency report
     *
     * @return array<mixed>
     */
    public function getMachineFuelEfficiency(Machine $machine, Carbon $startDate, Carbon $endDate): array
    {
        $metrics = FuelConsumptionMetric::where('machine_id', $machine->id)
            ->whereBetween('date', [$startDate, $endDate])
            ->get();

        $totalFuel = $metrics->sum('fuel_consumed_liters');
        $totalHours = $metrics->sum('operating_hours');
        $avgLph = $totalHours > 0 ? round($totalFuel / $totalHours, 4) : null;

        // Get trend data
        $trend = $metrics->map(function ($metric) {
            return [
                'date' => $metric->date->toDateString(),
                'fuel_consumed' => $metric->fuel_consumed_liters,
                'operating_hours' => $metric->operating_hours,
                'efficiency_lph' => $metric->fuel_efficiency_lph,
            ];
        });

        return [
            'machine_id' => $machine->id,
            'machine_name' => $machine->name,
            'period' => [
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
            ],
            'summary' => [
                'total_fuel_consumed' => round($totalFuel, 2),
                'total_operating_hours' => round($totalHours, 2),
                'average_lph' => $avgLph,
                'total_idle_fuel' => round($metrics->sum('idle_fuel_consumed'), 2),
            ],
            'daily_metrics' => $trend,
        ];
    }
}
