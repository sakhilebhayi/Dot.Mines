<?php

namespace App\Services\AI;

use App\Models\Machine;
use App\Models\Team;

/**
 * Fleet Optimizer AI Agent
 * Analyzes fleet utilization and provides optimization recommendations
 */
class FleetOptimizerAgent
{
    /**
     * @return array<mixed>
     */
    public function analyze(Team $team): array
    {
        $recommendations = [];
        $insights = [];

        // Get all machines for the team
        $machines = Machine::where('team_id', $team->id)
            ->with(['healthStatus'])
            ->get();

        // Analyze machine utilization
        $utilizationAnalysis = $this->analyzeUtilization($machines);
        if ($utilizationAnalysis['recommendations']) {
            $recommendations = array_merge($recommendations, $utilizationAnalysis['recommendations']);
        }
        if ($utilizationAnalysis['insights']) {
            $insights = array_merge($insights, $utilizationAnalysis['insights']);
        }

        // Analyze idle machines
        $idleAnalysis = $this->analyzeIdleMachines($machines);
        if ($idleAnalysis['recommendations']) {
            $recommendations = array_merge($recommendations, $idleAnalysis['recommendations']);
        }

        return [
            'recommendations' => $recommendations,
            'insights' => $insights,
        ];
    }

    /**
     * @return array<mixed>
     */
    protected function analyzeUtilization(mixed $machines): array
    {
        $recommendations = [];
        $insights = [];

        foreach ($machines as $machine) {
            // Calculate utilization metrics
            $hoursPerDay = $machine->metrics()
                ->whereDate('recorded_at', '>=', now()->subDays(7))
                ->avg('operating_hours') ?? 0;

            $utilizationRate = ($hoursPerDay / 24) * 100;

            // Low utilization
            if ($utilizationRate < 30 && $utilizationRate > 0) {
                $recommendations[] = [
                    'category' => 'fleet',
                    'priority' => 'high',
                    'title' => "Low Utilization: {$machine->name}",
                    'description' => "Machine {$machine->name} is only utilized {$utilizationRate}% of the time. Consider reassigning to high-demand areas or scheduling maintenance during idle periods.",
                    'confidence_score' => 0.85,
                    'estimated_efficiency_gain' => 40,
                    'related_machine_id' => $machine->id,
                    'data' => [
                        'current_utilization' => round($utilizationRate, 2),
                        'daily_operating_hours' => round($hoursPerDay, 2),
                        'wasted_hours_per_day' => round(24 - $hoursPerDay, 2),
                    ],
                    'impact_analysis' => [
                        'potential_increase' => '40% utilization increase possible',
                        'estimated_time_saved' => round((24 - $hoursPerDay) * 0.6, 2).' hours/day',
                    ],
                ];
            }

            // Overutilization
            if ($utilizationRate > 95) {
                $recommendations[] = [
                    'category' => 'fleet',
                    'priority' => 'critical',
                    'title' => "Overutilization Risk: {$machine->name}",
                    'description' => "Machine {$machine->name} is operating at {$utilizationRate}% capacity. High risk of breakdown and increased maintenance needs.",
                    'confidence_score' => 0.90,
                    'estimated_savings' => 50000, // Potential breakdown cost
                    'related_machine_id' => $machine->id,
                    'data' => [
                        'current_utilization' => round($utilizationRate, 2),
                        'recommended_max' => 85,
                        'excess_hours' => round(($utilizationRate - 85) / 100 * 24, 2),
                    ],
                    'impact_analysis' => [
                        'breakdown_risk' => 'High - 75% probability in next 30 days',
                        'recommended_action' => 'Reduce load or add support machine',
                    ],
                ];

                $insights[] = [
                    'type' => 'trend',
                    'category' => 'fleet',
                    'severity' => 'warning',
                    'title' => 'High Machine Stress Detected',
                    'description' => "Machine {$machine->name} is operating near maximum capacity",
                    'data' => ['machine_id' => $machine->id, 'utilization' => $utilizationRate],
                ];
            }
        }

        return [
            'recommendations' => $recommendations,
            'insights' => $insights,
        ];
    }

    /**
     * @return array<mixed>
     */
    protected function analyzeIdleMachines(mixed $machines): array
    {
        $recommendations = [];

        $idleMachines = $machines->filter(function ($machine) {
            return $machine->status === 'idle' || $machine->status === 'parked';
        });

        if ($idleMachines->count() > $machines->count() * 0.2) {
            $idlePercentage = ($idleMachines->count() / $machines->count()) * 100;

            $recommendations[] = [
                'category' => 'fleet',
                'priority' => 'high',
                'title' => 'High Idle Fleet Percentage',
                'description' => "{$idleMachines->count()} machines ({$idlePercentage}%) are currently idle. This represents significant underutilization of assets.",
                'confidence_score' => 0.92,
                'estimated_savings' => $idleMachines->count() * 5000, // R5000 per idle machine per day
                'data' => [
                    'idle_machines' => $idleMachines->count(),
                    'total_machines' => $machines->count(),
                    'idle_percentage' => round($idlePercentage, 2),
                    'machine_ids' => $idleMachines->pluck('id')->toArray(),
                ],
                'impact_analysis' => [
                    'daily_cost' => 'R'.number_format($idleMachines->count() * 5000, 2),
                    'monthly_cost' => 'R'.number_format($idleMachines->count() * 5000 * 30, 2),
                    'recommended_action' => 'Reassign or consider selling/renting out',
                ],
            ];
        }

        return ['recommendations' => $recommendations];
    }
}
