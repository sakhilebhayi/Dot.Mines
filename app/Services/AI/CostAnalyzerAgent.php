<?php

namespace App\Services\AI;

use App\Models\FuelTransaction;
use App\Models\MaintenanceRecord;
use App\Models\Team;

/**
 * Cost Analyzer AI Agent
 */
class CostAnalyzerAgent
{
    /**
     * @return array{recommendations: list<array<string, mixed>>, insights: list<array<string, mixed>>}
     */
    public function analyze(Team $team): array
    {
        $recommendations = [];
        $insights = [];

        // Analyze fuel costs
        $fuelCosts = (float) FuelTransaction::where('team_id', $team->id)
            ->whereDate('transaction_date', '>=', now()->subDays(30))
            ->sum('total_cost');

        // Analyze maintenance costs
        $maintenanceCosts = (float) MaintenanceRecord::where('team_id', $team->id)
            ->whereDate('completed_at', '>=', now()->subDays(30))
            ->sum('cost');

        $totalCosts = $fuelCosts + $maintenanceCosts;
        $avgDailyCost = $totalCosts / 30.0;

        if ($avgDailyCost > 50000) {
            $recommendations[] = [
                'category' => 'cost',
                'priority' => 'high',
                'title' => 'High Operational Costs',
                'description' => 'Daily operational costs averaging R'.number_format($avgDailyCost, 2).'. Review efficiency measures.',
                'confidence_score' => 0.85,
                'estimated_savings' => $avgDailyCost * 0.15 * 30.0,
                'data' => [
                    'total_monthly_cost' => round($totalCosts, 2),
                    'fuel_costs' => round($fuelCosts, 2),
                    'maintenance_costs' => round($maintenanceCosts, 2),
                    'daily_average' => round($avgDailyCost, 2),
                ],
            ];
        }

        return [
            'recommendations' => $recommendations,
            'insights' => $insights,
        ];
    }
}
