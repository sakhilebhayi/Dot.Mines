<?php

namespace App\Services\AI;

use App\Models\Machine;
use App\Models\Team;
use App\Services\MachinePerformanceService;

/**
 * Fleet Optimizer AI Agent
 *
 * Analyzes fleet utilisation from the real daily telemetry that
 * MachinePerformanceService derives from machine_metrics and
 * production_records. Machines whose telemetry cannot support a daily
 * utilisation figure are skipped rather than scored on invented numbers
 * (the old version averaged the cumulative lifetime engine-hours counter
 * and divided by 24, reporting "34976% capacity" for every machine), and
 * recommendations carry no fabricated savings or confidence figures.
 */
/**
 * @psalm-import-type MachineDayPerformance from \App\Services\MachinePerformanceService
 *
 * @phpstan-import-type MachineDayPerformance from \App\Services\MachinePerformanceService
 */
class FleetOptimizerAgent
{
    /**
     * Utilisation here is MachinePerformanceService's definition: the share
     * of today's engine-on time spent working rather than idling. Below
     * LOW_UTILISATION_PERCENT a machine is flagged; below the critical
     * bound the flag is high priority.
     */
    private const LOW_UTILISATION_PERCENT = 50.0;

    private const CRITICAL_UTILISATION_PERCENT = 30.0;

    /**
     * Minimum engine hours today before utilisation is judged at all -- a
     * machine that barely ran says nothing about its allocation.
     */
    private const MIN_ENGINE_HOURS_TO_JUDGE = 2.0;

    /**
     * Engine hours in a single day that leave no realistic window for
     * inspection or maintenance.
     */
    private const SUSTAINED_OPERATION_HOURS = 20.0;

    /**
     * Share of the fleet idle (by machine status) before it is flagged.
     */
    private const IDLE_FLEET_RATIO = 0.2;

    /** @psalm-suppress PossiblyUnusedMethod -- instantiated by the container (app()/DI), which psalm cannot see */
    public function __construct(private MachinePerformanceService $performanceService) {}

    /**
     * @return array{recommendations: list<array<string, mixed>>, insights: list<array<string, mixed>>}
     */
    public function analyze(Team $team): array
    {
        $recommendations = [];
        $insights = [];

        foreach ($this->performanceService->dailyPerformanceForTeam($team->id) as $performance) {
            $machineAnalysis = $this->analyzeMachineDay($performance);
            $recommendations = array_merge($recommendations, $machineAnalysis['recommendations']);
            $insights = array_merge($insights, $machineAnalysis['insights']);
        }

        $idleAnalysis = $this->analyzeIdleMachines($team);
        $recommendations = array_merge($recommendations, $idleAnalysis['recommendations']);

        return [
            'recommendations' => $recommendations,
            'insights' => $insights,
        ];
    }

    /**
     * @param  MachineDayPerformance  $performance
     * @return array{recommendations: list<array<string, mixed>>, insights: list<array<string, mixed>>}
     */
    protected function analyzeMachineDay(array $performance): array
    {
        $recommendations = [];
        $insights = [];

        $utilisation = $performance['utilisation_today'];
        $operatingHours = $performance['operating_hours_today'];
        $idleHours = $performance['idle_hours_today'];

        if ($utilisation === null || $operatingHours === null || $idleHours === null) {
            return ['recommendations' => [], 'insights' => []];
        }

        $name = $performance['machine_name'];

        if ($operatingHours >= self::MIN_ENGINE_HOURS_TO_JUDGE && $utilisation < self::LOW_UTILISATION_PERCENT) {
            $recommendations[] = [
                'category' => 'fleet',
                'priority' => $utilisation < self::CRITICAL_UTILISATION_PERCENT ? 'high' : 'medium',
                'title' => "Low Utilization: {$name}",
                'description' => "Machine {$name} spent only ".((string) round($utilisation)).'% of its '.((string) round($operatingHours, 1)).' engine hours working today ('.((string) round($idleHours, 1)).' hours idling). Consider reassigning it to a busier area or investigating queueing and dispatch delays.',
                'related_machine_id' => $performance['machine_id'],
                'data' => [
                    'current_utilisation' => round($utilisation, 2),
                    'operating_hours_today' => round($operatingHours, 2),
                    'idle_hours_today' => round($idleHours, 2),
                    'loads_today' => $performance['loads_today'],
                    'tonnes_today' => $performance['tonnes_today'],
                ],
                'impact_analysis' => [
                    'recommended_action' => 'Reassign to a high-demand area, or use the idle time for scheduled maintenance',
                ],
            ];
        }

        if ($operatingHours >= self::SUSTAINED_OPERATION_HOURS) {
            $recommendations[] = [
                'category' => 'fleet',
                'priority' => 'high',
                'title' => "Sustained Operation: {$name}",
                'description' => "Machine {$name} has run ".((string) round($operatingHours, 1)).' engine hours today, leaving no realistic window for inspection or maintenance. Extended running without breaks increases wear and breakdown risk.',
                'related_machine_id' => $performance['machine_id'],
                'data' => [
                    'operating_hours_today' => round($operatingHours, 2),
                    'idle_hours_today' => round($idleHours, 2),
                    'current_utilisation' => round($utilisation, 2),
                ],
                'impact_analysis' => [
                    'recommended_action' => 'Rotate in a support machine or schedule a maintenance window',
                ],
            ];

            $insights[] = [
                'type' => 'trend',
                'category' => 'fleet',
                'severity' => 'warning',
                'title' => 'High Machine Stress Detected',
                'description' => "Machine {$name} has run ".((string) round($operatingHours, 1)).' engine hours today without a maintenance window',
                'data' => [
                    'machine_id' => $performance['machine_id'],
                    'operating_hours_today' => round($operatingHours, 2),
                    'utilisation' => round($utilisation, 2),
                ],
            ];
        }

        if ($performance['utilisation_trend'] === 'declining') {
            $insights[] = [
                'type' => 'trend',
                'category' => 'fleet',
                'severity' => 'warning',
                'title' => "Utilisation Declining: {$name}",
                'description' => "Machine {$name}'s working share of engine time (".((string) round($utilisation)).'% today) is well below its recent daily average',
                'data' => [
                    'machine_id' => $performance['machine_id'],
                    'utilisation_today' => round($utilisation, 2),
                ],
            ];
        }

        return [
            'recommendations' => $recommendations,
            'insights' => $insights,
        ];
    }

    /**
     * Fleet-level idle share from machine status -- a real, operator-set
     * field. The old version also priced each idle machine at an invented
     * R5,000/day "savings"; the counts stand on their own.
     *
     * @return array{recommendations: list<array<string, mixed>>}
     */
    protected function analyzeIdleMachines(Team $team): array
    {
        $recommendations = [];

        $statusCounts = Machine::where('team_id', $team->id)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $totalMachines = (int) $statusCounts->sum();
        $idleCount = (int) ($statusCounts['idle'] ?? 0);

        if ($totalMachines > 0 && (float) $idleCount > (float) $totalMachines * self::IDLE_FLEET_RATIO) {
            $idlePercentage = ((float) $idleCount / (float) $totalMachines) * 100.0;

            $recommendations[] = [
                'category' => 'fleet',
                'priority' => 'high',
                'title' => 'High Idle Fleet Percentage',
                'description' => "{$idleCount} of {$totalMachines} machines (".((string) round($idlePercentage)).'%) are currently marked idle. This represents significant underutilization of assets.',
                'data' => [
                    'idle_machines' => $idleCount,
                    'total_machines' => $totalMachines,
                    'idle_percentage' => round($idlePercentage, 2),
                    'machine_ids' => Machine::where('team_id', $team->id)
                        ->where('status', 'idle')
                        ->pluck('id')
                        ->all(),
                ],
                'impact_analysis' => [
                    'recommended_action' => 'Reassign idle machines to active areas, or stand them down to cut standing costs',
                ],
            ];
        }

        return ['recommendations' => $recommendations];
    }
}
