<?php

namespace App\Services\AI;

use App\Models\AIAgent;
use App\Models\AIPredictiveAlert;
use App\Models\Machine;
use App\Models\MachineMetric;
use App\Models\MaintenanceSchedule;
use App\Models\Team;
use Illuminate\Support\Collection;

/**
 * Maintenance Predictor AI Agent
 * Predicts maintenance needs and prevents breakdowns using predictive analytics
 */
class MaintenancePredictorAgent
{
    /**
     * @return array{recommendations: list<array<string, mixed>>, insights: list<array<string, mixed>>}
     */
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

    /**
     * @return array{recommendations: list<array<string, mixed>>, insights: list<array<string, mixed>>}
     */
    protected function predictMaintenanceNeeds(Team $team): array
    {
        $recommendations = [];
        $insights = [];

        $machines = Machine::where('team_id', $team->id)
            ->with(['healthStatus', 'maintenanceRecords'])
            ->get();

        // Batch the per-machine metric aggregates (30-day counter window and
        // 7-day average) into two grouped queries -- the per-machine helpers
        // used to fire 4-5 queries EACH inside this loop.
        $metricWindows30 = $this->metricWindowsForMachines($machines->pluck('id')->all(), 30);
        $metricWindows7 = $this->metricWindowsForMachines($machines->pluck('id')->all(), 7);

        foreach ($machines as $machine) {
            // Calculate risk score based on multiple factors
            $riskScore = $this->calculateBreakdownRisk($machine, $metricWindows30->get($machine->id));

            if ($riskScore > 0.7) {
                // High risk of breakdown
                $daysUntilBreakdown = $this->estimateDaysUntilBreakdown($riskScore);

                $recommendations[] = [
                    'category' => 'maintenance',
                    'priority' => 'critical',
                    'title' => "High Breakdown Risk: {$machine->name}",
                    'description' => 'AI predicts '.((string) round($riskScore * 100.0))."% probability of breakdown within {$daysUntilBreakdown} days for {$machine->name}. Immediate inspection recommended.",
                    'confidence_score' => $riskScore,
                    'estimated_savings' => 150000, // Average breakdown cost
                    'related_machine_id' => $machine->id,
                    'data' => [
                        'risk_score' => round($riskScore, 2),
                        'estimated_days_until_breakdown' => $daysUntilBreakdown,
                        'contributing_factors' => $this->getContributingFactors($machine, $metricWindows7->get($machine->id)),
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
                        'risk_score' => round($riskScore * 100.0, 2),
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
            if ($riskScore < 0.3 && $this->isOptimalMaintenanceTime($metricWindows7->get($machine->id))) {
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

    /**
     * @return array{recommendations: list<array<string, mixed>>}
     */
    protected function optimizeMaintenanceSchedules(Team $team): array
    {
        $recommendations = [];

        $schedules = MaintenanceSchedule::where('team_id', $team->id)
            ->where('status', 'active')
            ->with('machine')
            ->get();

        $usageWindows = $this->metricWindowsForMachines(
            $schedules->pluck('machine_id')->filter()->unique()->values()->all(),
            30
        );

        foreach ($schedules as $schedule) {
            $machine = $schedule->machine;

            if ($machine === null || $schedule->interval_days === null) {
                continue;
            }

            // Check if schedule is optimal based on machine usage
            $actualUsage = $usageWindows->get($machine->id)?->avgHours ?? 0.0;
            $scheduledInterval = $schedule->interval_days;

            $optimalInterval = $this->calculateOptimalInterval($actualUsage);

            if (abs($optimalInterval - $scheduledInterval) > 10) {
                $recommendations[] = [
                    'category' => 'maintenance',
                    'priority' => 'medium',
                    'title' => "Suboptimal Maintenance Schedule: {$machine->name}",
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

    protected function calculateBreakdownRisk(Machine $machine, ?MachineMetricWindow $window30): float
    {
        $riskFactors = [];

        // Factor 1: Operating hours (30% weight). operating_hours is a
        // cumulative meter -- hours worked in the window is the counter
        // DELTA, not a sum of readings (summing pegged this factor at max
        // for every machine with two or more readings). The window comes
        // pre-aggregated (one grouped query for the whole fleet).
        $operatingHours = ($window30 !== null && $window30->readingCount >= 2)
            ? max(0.0, $window30->maxHours - $window30->minHours)
            : 0.0;
        $avgHoursPerDay = $operatingHours / 30.0;
        $riskFactors['hours'] = min(($avgHoursPerDay / 20.0) * 0.3, 0.3); // 20h/day is high

        // Factor 2: Time since last maintenance (25% weight) -- from the
        // eager-loaded relation (maintenanceRecords() as a query here was
        // one of the loop's per-machine round trips).
        $lastMaintenance = $machine->maintenanceRecords
            ->where('status', 'completed')
            ->whereNotNull('completed_at')
            ->sortByDesc('completed_at')
            ->first();

        if ($lastMaintenance) {
            // Carbon 3: now()->diffInDays($past) is negative, which made this
            // factor SUBTRACT from the risk score instead of adding to it.
            $daysSinceLastMaintenance = $lastMaintenance->completed_at?->diffInDays(now()) ?? 180.0;
            $riskFactors['maintenance'] = min(($daysSinceLastMaintenance / 180.0) * 0.25, 0.25);
        } else {
            $riskFactors['maintenance'] = 0.25; // No maintenance history = max risk
        }

        // Factor 3: Health score (25% weight)
        $healthScore = (float) ($machine->healthStatus?->overall_health_score ?? 50);
        $riskFactors['health'] = ((100.0 - $healthScore) / 100.0) * 0.25;

        // Factor 4: Age of machine (20% weight)
        $machineAge = ($machine->year_of_manufacture !== null && $machine->year_of_manufacture !== 0)
            ? now()->year - $machine->year_of_manufacture
            : 5;
        $riskFactors['age'] = min(((float) $machineAge / 20.0) * 0.2, 0.2); // 20 years is max

        return array_sum($riskFactors);
    }

    protected function estimateDaysUntilBreakdown(float $riskScore): int
    {
        // Higher risk = fewer days until breakdown
        $baseDays = 90;

        return max(7, (int) ((float) $baseDays * (1.0 - $riskScore)));
    }

    /**
     * @return array<string, mixed>
     */
    /** @return list<string> */
    protected function getContributingFactors(Machine $machine, ?MachineMetricWindow $window7): array
    {
        $factors = [];

        /** @var mixed $healthScore */
        $healthScore = $machine->healthStatus?->overall_health_score;

        if (is_numeric($healthScore) && (float) $healthScore < 60.0) {
            $factors[] = 'Low health score: '.((string) $healthScore);
        }

        $lastMaintenance = $machine->maintenanceRecords->sortByDesc('completed_at')->first();
        if (! $lastMaintenance || $lastMaintenance->completed_at === null || $lastMaintenance->completed_at->diffInDays(now()) > 90) {
            $factors[] = 'Overdue maintenance';
        }

        $highUsage = $window7?->avgHours;

        if ($highUsage !== null && $highUsage > 18.0) {
            $factors[] = 'High utilization: '.((string) round($highUsage, 1)).' hours/day';
        }

        if (($machine->year_of_manufacture !== null && $machine->year_of_manufacture !== 0) && (now()->year - $machine->year_of_manufacture) > 10) {
            $factors[] = 'Age: '.(now()->year - $machine->year_of_manufacture).' years';
        }

        return $factors;
    }

    protected function isOptimalMaintenanceTime(?MachineMetricWindow $window7): bool
    {
        // Check if machine is in low-demand period. No readings at all keeps
        // the old avg()=null < 12 behaviour: counts as low demand.
        return ($window7?->avgHours ?? 0.0) < 12; // Less than 12 hours/day = low demand
    }

    /**
     * One grouped query per window: max/min/avg/count of operating_hours per
     * machine over the trailing N days. Same whereDate bucketing and
     * non-null filtering the old per-machine queries used.
     *
     * @param  list<int>  $machineIds
     * @return Collection<int, MachineMetricWindow>
     */
    protected function metricWindowsForMachines(array $machineIds, int $days): Collection
    {
        if ($machineIds === []) {
            return collect();
        }

        $rows = MachineMetric::query()
            ->whereIn('machine_id', $machineIds)
            ->whereDate('recorded_at', '>=', now()->subDays($days))
            ->whereNotNull('operating_hours')
            ->selectRaw('machine_id, MAX(operating_hours) as max_hours, MIN(operating_hours) as min_hours, AVG(operating_hours) as avg_hours, COUNT(*) as reading_count')
            ->groupBy('machine_id')
            ->get();

        $windows = collect();

        foreach ($rows as $row) {
            $windows->put((int) data_get($row, 'machine_id'), new MachineMetricWindow(
                maxHours: (float) data_get($row, 'max_hours'),
                minHours: (float) data_get($row, 'min_hours'),
                avgHours: (float) data_get($row, 'avg_hours'),
                readingCount: (int) data_get($row, 'reading_count'),
            ));
        }

        return $windows;
    }

    protected function calculateOptimalInterval(float $usageRate): int
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
