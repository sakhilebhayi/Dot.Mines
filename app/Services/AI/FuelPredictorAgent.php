<?php

namespace App\Services\AI;

use App\Models\FuelTank;
use App\Models\FuelTransaction;
use App\Models\Machine;
use App\Models\Team;

/**
 * Fuel Predictor AI Agent
 * Predicts fuel consumption and identifies cost-saving opportunities
 */
class FuelPredictorAgent
{
    public function analyze(Team $team): array
    {
        $recommendations = [];
        $insights = [];

        // Analyze fuel consumption patterns
        $consumptionAnalysis = $this->analyzeFuelConsumption($team);
        $recommendations = array_merge($recommendations, $consumptionAnalysis['recommendations']);
        $insights = array_merge($insights, $consumptionAnalysis['insights']);

        // Predict future needs
        $predictionAnalysis = $this->predictFuelNeeds($team);
        $recommendations = array_merge($recommendations, $predictionAnalysis['recommendations']);

        // Analyze tank levels
        $tankAnalysis = $this->analyzeTankLevels($team);
        $recommendations = array_merge($recommendations, $tankAnalysis['recommendations']);

        return [
            'recommendations' => $recommendations,
            'insights' => $insights,
        ];
    }

    protected function analyzeFuelConsumption(Team $team): array
    {
        $recommendations = [];
        $insights = [];

        // Get fuel transactions for last 30 days
        $transactions = FuelTransaction::where('team_id', $team->id)
            ->whereDate('transaction_date', '>=', now()->subDays(30))
            ->where('transaction_type', 'dispensing')
            ->get();

        if ($transactions->isEmpty()) {
            return ['recommendations' => [], 'insights' => []];
        }

        // Group by machine
        $byMachine = $transactions->groupBy('machine_id');

        foreach ($byMachine as $machineId => $machineTransactions) {
            $machine = Machine::find($machineId);
            if (! $machine) {
                continue;
            }

            $totalLiters = $machineTransactions->sum('quantity_liters');
            $totalCost = $machineTransactions->sum('total_cost');
            $avgDailyConsumption = $totalLiters / 30;
            // This machine's own actual price paid over the period, rather
            // than a hardcoded guess -- falls back to the team's broader
            // recent average only if this machine has no cost data at all.
            $pricePerLiter = $totalLiters > 0 ? $totalCost / $totalLiters : $this->getAverageUnitPrice($team);

            // Check for high consumption
            $expectedConsumption = $this->getExpectedConsumption($machine->machine_type);
            $deviationPercent = (($avgDailyConsumption - $expectedConsumption) / $expectedConsumption) * 100;

            if ($deviationPercent > 20) {
                $monthlyCostImpact = $pricePerLiter !== null
                    ? ($avgDailyConsumption - $expectedConsumption) * 30 * $pricePerLiter
                    : null;

                $recommendations[] = [
                    'category' => 'fuel',
                    'priority' => 'high',
                    'title' => "High Fuel Consumption: {$machine->name}",
                    'description' => "Machine {$machine->name} is consuming ".round($deviationPercent).'% more fuel than expected. This could indicate maintenance issues or operational inefficiencies.',
                    'confidence_score' => 0.82,
                    'estimated_savings' => $monthlyCostImpact,
                    'related_machine_id' => $machine->id,
                    'data' => [
                        'actual_daily_consumption' => round($avgDailyConsumption, 2),
                        'expected_daily_consumption' => $expectedConsumption,
                        'excess_liters_per_day' => round($avgDailyConsumption - $expectedConsumption, 2),
                        'monthly_cost_impact' => $monthlyCostImpact !== null ? round($monthlyCostImpact, 2) : null,
                    ],
                    'impact_analysis' => [
                        'potential_savings' => $monthlyCostImpact !== null
                            ? 'R'.number_format($monthlyCostImpact, 2).'/month'
                            : 'No recent fuel pricing data to estimate cost impact',
                        'recommended_actions' => [
                            'Schedule maintenance inspection',
                            'Check for fuel leaks',
                            'Review operator training',
                            'Verify load optimization',
                        ],
                    ],
                ];

                $insights[] = [
                    'type' => 'anomaly',
                    'category' => 'fuel',
                    'severity' => 'warning',
                    'title' => 'Abnormal Fuel Consumption Pattern',
                    'description' => "{$machine->name} consuming significantly more fuel than baseline",
                    'data' => [
                        'machine_id' => $machine->id,
                        'deviation' => round($deviationPercent, 2),
                    ],
                    'visualization_data' => [
                        'type' => 'line_chart',
                        'actual' => array_values($machineTransactions->pluck('quantity_liters')->toArray()),
                        'expected' => array_fill(0, $machineTransactions->count(), $expectedConsumption),
                    ],
                ];
            }

            // Check for efficient consumption
            if ($deviationPercent < -10) {
                $insights[] = [
                    'type' => 'trend',
                    'category' => 'fuel',
                    'severity' => 'success',
                    'title' => 'Excellent Fuel Efficiency',
                    'description' => "{$machine->name} is operating ".abs(round($deviationPercent)).'% below expected fuel consumption',
                    'data' => [
                        'machine_id' => $machine->id,
                        'efficiency_gain' => abs(round($deviationPercent, 2)),
                    ],
                ];
            }
        }

        return [
            'recommendations' => $recommendations,
            'insights' => $insights,
        ];
    }

    protected function predictFuelNeeds(Team $team): array
    {
        $recommendations = [];

        // Get historical consumption
        $last30Days = FuelTransaction::where('team_id', $team->id)
            ->whereDate('transaction_date', '>=', now()->subDays(30))
            ->where('transaction_type', 'dispensing')
            ->sum('quantity_liters');

        $avgDailyConsumption = $last30Days / 30;
        $predicted7Days = $avgDailyConsumption * 7;
        $predicted30Days = $avgDailyConsumption * 30;

        // Check current inventory
        $currentInventory = FuelTank::where('team_id', $team->id)
            ->sum('current_level_liters');

        $daysOfSupply = $avgDailyConsumption > 0 ? $currentInventory / $avgDailyConsumption : 0;

        // Low inventory warning
        if ($daysOfSupply < 7) {
            $recommendations[] = [
                'category' => 'fuel',
                'priority' => $daysOfSupply < 3 ? 'critical' : 'high',
                'title' => 'Low Fuel Inventory',
                'description' => 'Current fuel inventory will last approximately '.round($daysOfSupply, 1).' days at current consumption rates. Immediate refueling recommended.',
                'confidence_score' => 0.88,
                'estimated_savings' => null,
                'data' => [
                    'current_inventory_liters' => round($currentInventory, 2),
                    'daily_consumption' => round($avgDailyConsumption, 2),
                    'days_of_supply' => round($daysOfSupply, 2),
                    'recommended_order_liters' => round($predicted30Days, 2),
                ],
                'impact_analysis' => [
                    'risk' => 'Operations may be affected in '.round($daysOfSupply).' days',
                    'recommended_action' => 'Order '.number_format($predicted30Days, 0).' liters immediately',
                ],
            ];
        }

        // Optimal ordering recommendation
        if ($daysOfSupply > 7 && $daysOfSupply < 20) {
            $optimalOrderQuantity = $predicted30Days * 1.2; // 20% buffer
            $pricePerLiter = $this->getAverageUnitPrice($team);
            $estimatedOrderCost = $pricePerLiter !== null ? $optimalOrderQuantity * $pricePerLiter : null;

            $recommendations[] = [
                'category' => 'fuel',
                'priority' => 'medium',
                'title' => 'Optimal Fuel Order Timing',
                'description' => 'Good time to place a fuel order. Current inventory is adequate but planning ahead will prevent urgent orders.',
                'confidence_score' => 0.85,
                'data' => [
                    'predicted_30_day_needs' => round($predicted30Days, 2),
                    'recommended_order_quantity' => round($optimalOrderQuantity, 2),
                    'current_price_per_liter' => $pricePerLiter !== null ? round($pricePerLiter, 2) : null,
                    'estimated_order_cost' => $estimatedOrderCost !== null ? round($estimatedOrderCost, 2) : null,
                ],
                'impact_analysis' => [
                    'benefit' => 'Avoid rushed orders and potential price premiums',
                    'cost_estimate' => $estimatedOrderCost !== null
                        ? 'R'.number_format($estimatedOrderCost, 2)
                        : 'No recent fuel pricing data to estimate cost',
                ],
            ];
        }

        return ['recommendations' => $recommendations];
    }

    protected function analyzeTankLevels(Team $team): array
    {
        $recommendations = [];

        $tanks = FuelTank::where('team_id', $team->id)->get();

        foreach ($tanks as $tank) {
            $fillPercentage = $tank->fill_percentage;

            // Critical level
            if ($fillPercentage < 15) {
                $recommendations[] = [
                    'category' => 'fuel',
                    'priority' => 'critical',
                    'title' => "Critical Tank Level: {$tank->name}",
                    'description' => "Tank {$tank->name} is at {$fillPercentage}% capacity. Immediate refill required to prevent operational disruptions.",
                    'confidence_score' => 0.95,
                    'data' => [
                        'tank_id' => $tank->id,
                        'current_level' => $tank->current_level_liters,
                        'capacity' => $tank->capacity_liters,
                        'fill_percentage' => round($fillPercentage, 2),
                        'required_liters' => $tank->capacity_liters - $tank->current_level_liters,
                    ],
                ];
            }

            // Overfill warning
            if ($fillPercentage > 95) {
                $recommendations[] = [
                    'category' => 'fuel',
                    'priority' => 'low',
                    'title' => "Near Capacity: {$tank->name}",
                    'description' => "Tank {$tank->name} is at {$fillPercentage}% capacity. Consider distribution to other tanks or machines before next delivery.",
                    'confidence_score' => 0.75,
                    'data' => [
                        'tank_id' => $tank->id,
                        'current_level' => $tank->current_level_liters,
                        'available_space' => $tank->capacity_liters - $tank->current_level_liters,
                    ],
                ];
            }
        }

        return ['recommendations' => $recommendations];
    }

    /**
     * The team's own recent average price paid per liter, computed from
     * real transactions -- returns null (rather than a guessed constant)
     * when there's no real pricing data to derive it from yet.
     */
    protected function getAverageUnitPrice(Team $team): ?float
    {
        $avg = FuelTransaction::where('team_id', $team->id)
            ->where('transaction_type', 'dispensing')
            ->whereNotNull('unit_price')
            ->where('unit_price', '>', 0)
            ->whereDate('transaction_date', '>=', now()->subDays(90))
            ->avg('unit_price');

        return $avg !== null ? (float) $avg : null;
    }

    protected function getExpectedConsumption(string $machineType): float
    {
        // Expected daily fuel consumption in liters by machine type.
        // Keys must match the real machine_type values (see Fleet's "Add
        // Machine" type select) -- 'haul_truck'/'bulldozer' never matched
        // any real machine (the actual values are 'truck'/'adt' and
        // 'dozer'), so every truck and dozer was silently falling through
        // to the generic default instead of its own baseline.
        return match (strtolower($machineType)) {
            'excavator' => 200,
            'adt', 'truck' => 150,
            'dozer' => 180,
            'loader' => 120,
            'drill' => 100,
            'grader' => 90,
            default => 150,
        };
    }
}
