<?php

namespace App\Services\AI;

use App\Models\AIAgent;
use App\Models\AIPredictiveAlert;
use App\Models\Machine;
use App\Models\MaintenanceSchedule;
use App\Models\Team;

/**
 * Maintenance Predictor AI Agent
 * Predicts maintenance needs and prevents breakdowns using predictive analytics
 */
class MaintenancePredictorAgent
{
    public function analyze(Team $team): array
    {
        $recommendations = [];
        $insights = [];

        // Predict maintenance needs
        $predictionAnalysis = $this->predictMaintenanceNeeds($team);
        $recommendations = array_merge($recommendations, $predictionAnalysis['recommendations']);
        $insights = array_merge($insights, $predictionAnalysis['insights']);

        // Optimize maintenance schedules
        $scheduleAnalysis = $this->optimizeMaintenanceSchedules($team);
        $recommendations = array_merge($recommendations, $scheduleAnalysis['recommendations']);

        return [
            'recommendations' => $recommendations,
            'insights' => $insights,
        ];
    }

    protected function predictMaintenanceNeeds(Team $team): array
    {
        $recommendations = [];
        $insights = [];

        $machines = Machine::where('team_id', $team->id)
            ->with(['healthStatus', 'maintenanceRecords'])
            ->get();

        foreach ($machines as $machine) {
            // Calculate risk score based on multiple factors
            $riskScore = $this->calculateBreakdownRisk($machine);

            if ($riskScore > 0.7) {
                // High risk of breakdown
                $daysUntilBreakdown = $this->estimateDaysUntilBreakdown($machine, $riskScore);

                $recommendations[] = [
                    'category' => 'maintenance',
                    'priority' => 'critical',
                    'title' => "High Breakdown Risk: {$machine->name}",
                    'description' => 'AI predicts '.round($riskScore * 100)."% probability of breakdown within {$daysUntilBreakdown} days for {$machine->name}. Immediate inspection recommended.",
                    'confidence_score' => $riskScore,
                    'estimated_savings' => 150000, // Average breakdown cost
                    'related_machine_id' => $machine->id,
                    'data' => [
                        'risk_score' => round($riskScore, 2),
                        'estimated_days_until_breakdown' => $daysUntilBreakdown,
                        'contributing_factors' => $this->getContributingFactors($machine),
                        'last_maintenance' => $machine->maintenanceRecords->sortByDesc('completed_at')->first()?->completed_at?->format('Y-m-d'),
                    ],
                    'impact_analysis' => [
                        'breakdown_cost' => 'R150,000 - R250,000',
                        'downtime_impact' => '3-7 days of lost production',
                        'preventive_action_cost' => 'R15,000 - R30,000',
                        'recommended_actions' => [
                            'Schedule immediate inspection',
                            'Order critical spare parts',
                            'Reduce operational load',
                            'Prepare backup machine',
                        ],
                    ],
                ];

                // Create predictive alert
                $this->createPredictiveAlert($team, $machine, $riskScore, $daysUntilBreakdown);

                $insights[] = [
                    'type' => 'prediction',
                    'category' => 'maintenance',
                    'severity' => 'critical',
                    'title' => 'Breakdown Prediction',
                    'description' => "Machine {$machine->name} showing concerning patterns",
                    'data' => [
                        'machine_id' => $machine->id,
                        'risk_score' => round($riskScore * 100, 2),
                    ],
                ];
            } elseif ($riskScore > 0.4) {
                // Medium risk
                $recommendations[] = [
                    'category' => 'maintenance',
                    'priority' => 'high',
                    'title' => "Elevated Maintenance Risk: {$machine->name}",
                    'description' => "Machine {$machine->name} showing elevated wear patterns. Recommend scheduling maintenance within 2 weeks.",
                    'confidence_score' => $riskScore,
                    'estimated_savings' => 75000,
                    'related_machine_id' => $machine->id,
                    'data' => [
                        'risk_score' => round($riskScore, 2),
                        'recommended_inspection_date' => now()->addDays(14)->format('Y-m-d'),
                    ],
                ];
            }

            // Check for optimal maintenance timing
            if ($riskScore < 0.3 && $this->isOptimalMaintenanceTime($machine)) {
                $recommendations[] = [
                    'category' => 'maintenance',
                    'priority' => 'low',
                    'title' => "Optimal Preventive Maintenance Window: {$machine->name}",
                    'description' => "Machine {$machine->name} is in good health. This is an optimal time for preventive maintenance without disrupting operations.",
                    'confidence_score' => 0.82,
                    'related_machine_id' => $machine->id,
                    'data' => [
                        'risk_score' => round($riskScore, 2),
                        'optimal_window' => 'Next 30 days',
                    ],
                ];
            }
        }

        return [
            'recommendations' => $recommendations,
            'insights' => $insights,
        ];
    }

    protected function optimizeMaintenanceSchedules(Team $team): array
    {
        $recommendations = [];

        $schedules = MaintenanceSchedule::where('team_id', $team->id)
            ->where('status', 'active')
            ->with('machine')
            ->get();

        foreach ($schedules as $schedule) {
            // Check if schedule is optimal based on machine usage
            $actualUsage = $this->getMachineUsageRate($schedule->machine);
            $scheduledInterval = $schedule->interval_days;

            $optimalInterval = $this->calculateOptimalInterval($schedule->machine, $actualUsage);

            if (abs($optimalInterval - $scheduledInterval) > 10) {
                $recommendations[] = [
                    'category' => 'maintenance',
                    'priority' => 'medium',
                    'title' => "Suboptimal Maintenance Schedule: {$schedule->machine->name}",
                    'description' => "Current {$scheduledInterval}-day maintenance interval is not aligned with actual usage patterns. Recommend adjusting to {$optimalInterval} days.",
                    'confidence_score' => 0.75,
                    'estimated_savings' => abs($optimalInterval - $scheduledInterval) * 500, // R500 per day difference
                    'related_machine_id' => $schedule->machine_id,
                    'data' => [
                        'current_interval' => $scheduledInterval,
                        'recommended_interval' => $optimalInterval,
                        'actual_usage_rate' => round($actualUsage, 2),
                    ],
                    'impact_analysis' => [
                        'cost_impact' => $optimalInterval > $scheduledInterval
                            ? 'Reduce maintenance frequency to save costs'
                            : 'Increase maintenance frequency to prevent failures',
                    ],
                ];
            }
        }

        return ['recommendations' => $recommendations];
    }

    protected function calculateBreakdownRisk(Machine $machine): float
    {
        $riskFactors = [];

        // Factor 1: Operating hours (30% weight). operating_hours is a
        // cumulative meter -- hours worked in the window is the counter
        // DELTA, not a sum of readings (summing pegged this factor at max
        // for every machine with two or more readings).
        $readings = $machine->metrics()
            ->whereDate('recorded_at', '>=', now()->subDays(30))
            ->get()
            ->pluck('operating_hours')
            ->filter(fn ($value) => $value !== null)
            ->map(fn ($value) => (float) $value);

        $operatingHours = $readings->count() >= 2
            ? max(0.0, $readings->max() - $readings->min())
            : 0.0;
        $avgHoursPerDay = $operatingHours / 30;
        $riskFactors['hours'] = min(($avgHoursPerDay / 20) * 0.3, 0.3); // 20h/day is high

        // Factor 2: Time since last maintenance (25% weight)
        $lastMaintenance = $machine->maintenanceRecords()
            ->where('status', 'completed')
            ->orderByDesc('completed_at')
            ->first();

        if ($lastMaintenance) {
            $daysSinceLastMaintenance = now()->diffInDays($lastMaintenance->completed_at);
            $riskFactors['maintenance'] = min(($daysSinceLastMaintenance / 180) * 0.25, 0.25);
        } else {
            $riskFactors['maintenance'] = 0.25; // No maintenance history = max risk
        }

        // Factor 3: Health score (25% weight)
        $healthScore = $machine->healthStatus?->overall_health_score ?? 50;
        $riskFactors['health'] = ((100 - $healthScore) / 100) * 0.25;

        // Factor 4: Age of machine (20% weight)
        $machineAge = $machine->year_of_manufacture
            ? now()->year - $machine->year_of_manufacture
            : 5;
        $riskFactors['age'] = min(($machineAge / 20) * 0.2, 0.2); // 20 years is max

        return array_sum($riskFactors);
    }

    protected function estimateDaysUntilBreakdown(Machine $machine, float $riskScore): int
    {
        // Higher risk = fewer days until breakdown
        $baseDays = 90;

        return max(7, (int) ($baseDays * (1 - $riskScore)));
    }

    protected function getContributingFactors(Machine $machine): array
    {
        $factors = [];

        if ($machine->healthStatus?->overall_health_score < 60) {
            $factors[] = 'Low health score: '.$machine->healthStatus->overall_health_score;
        }

        $lastMaintenance = $machine->maintenanceRecords()->latest('completed_at')->first();
        if (! $lastMaintenance || now()->diffInDays($lastMaintenance->completed_at) > 90) {
            $factors[] = 'Overdue maintenance';
        }

        $highUsage = $machine->metrics()
            ->whereDate('recorded_at', '>=', now()->subDays(7))
            ->avg('operating_hours');

        if ($highUsage > 18) {
            $factors[] = 'High utilization: '.round($highUsage, 1).' hours/day';
        }

        if ($machine->year_of_manufacture && (now()->year - $machine->year_of_manufacture) > 10) {
            $factors[] = 'Age: '.(now()->year - $machine->year_of_manufacture).' years';
        }

        return $factors;
    }

    protected function isOptimalMaintenanceTime(Machine $machine): bool
    {
        // Check if machine is in low-demand period
        $recentHours = $machine->metrics()
            ->whereDate('recorded_at', '>=', now()->subDays(7))
            ->avg('operating_hours');

        return $recentHours < 12; // Less than 12 hours/day = low demand
    }

    protected function getMachineUsageRate(Machine $machine): float
    {
        return $machine->metrics()
            ->whereDate('recorded_at', '>=', now()->subDays(30))
            ->avg('operating_hours') ?? 0;
    }

    protected function calculateOptimalInterval(Machine $machine, float $usageRate): int
    {
        // Higher usage = shorter intervals
        if ($usageRate > 16) {
            return 30;
        }
        if ($usageRate > 12) {
            return 45;
        }
        if ($usageRate > 8) {
            return 60;
        }

        return 90;
    }

    protected function createPredictiveAlert(Team $team, Machine $machine, float $riskScore, int $daysUntil): void
    {
        $agent = AIAgent::where('type', AIAgent::TYPE_MAINTENANCE_PREDICTOR)->first();

        if ($agent) {
            AIPredictiveAlert::create([
                'team_id' => $team->id,
                'ai_agent_id' => $agent->id,
                'alert_type' => 'breakdown_risk',
                'severity' => $riskScore > 0.8 ? 'critical' : 'high',
                'title' => "Predicted Breakdown: {$machine->name}",
                'description' => "AI model predicts high probability of breakdown within {$daysUntil} days",
                'predictions' => [
                    'risk_score' => $riskScore,
                    'days_until_breakdown' => $daysUntil,
                    'confidence' => $riskScore,
                ],
                'probability' => $riskScore,
                'predicted_occurrence' => now()->addDays($daysUntil),
                'recommended_actions' => [
                    'Schedule immediate inspection',
                    'Order spare parts',
                    'Prepare backup machine',
                ],
                'related_machine_id' => $machine->id,
            ]);
        }
    }
}
