<?php

namespace App\Services\AI;

use App\Models\ProductionRecord;
use App\Models\Team;
use Carbon\Carbon;

/**
 * Production Optimizer AI Agent
 *
 * Analyses real internal production records only.
 * No external data sources. No fabricated assumptions.
 * Every recommendation is derived exclusively from the team's own
 * ProductionRecord rows stored in the database.
 */
class ProductionOptimizerAgent
{
    /**
     * @return array<mixed>
     */
    public function analyze(Team $team): array
    {
        $recommendations = [];
        $insights = [];

        $machineAnalysis = $this->analyzeMachineEfficiency($team);
        $shiftAnalysis = $this->analyzeShiftPerformance($team);
        $trendAnalysis = $this->analyzeWeekOverWeekTrend($team);
        $areaAnalysis = $this->analyzeAreaPerformance($team);

        foreach ([$machineAnalysis, $shiftAnalysis, $trendAnalysis, $areaAnalysis] as $result) {
            $recommendations = array_merge($recommendations, $result['recommendations']);
            $insights = array_merge($insights, $result['insights']);
        }

        return ['recommendations' => $recommendations, 'insights' => $insights];
    }

    // -------------------------------------------------------------------------
    // Machine efficiency
    // -------------------------------------------------------------------------

    /**
     * @return array<mixed>
     */
    protected function analyzeMachineEfficiency(Team $team): array
    {
        $recommendations = [];
        $insights = [];

        $records = ProductionRecord::where('team_id', $team->id)
            ->where('record_date', '>=', Carbon::now()->subDays(30))
            ->whereNotNull('machine_id')
            ->whereNotNull('target_quantity')
            ->where('target_quantity', '>', 0)
            ->with('machine:id,name,machine_type')
            ->get();

        if ($records->isEmpty()) {
            return ['recommendations' => [], 'insights' => []];
        }

        $byMachine = $records->groupBy('machine_id');

        foreach ($byMachine as $machineId => $machineRecords) {
            $machine = $machineRecords->first()->machine;
            if (! $machine) {
                continue;
            }

            $produced = (float) $machineRecords->sum('quantity_produced');
            $target = (float) $machineRecords->sum('target_quantity');
            $achievement = ($produced / $target) * 100;
            $count = $machineRecords->count();

            // Need at least 3 records to be meaningful
            if ($count < 3) {
                continue;
            }

            if ($achievement < 70) {
                $shortfall = $target - $produced;
                $recommendations[] = [
                    'category' => 'production',
                    'priority' => $achievement < 50 ? 'critical' : 'high',
                    'title' => "Low Target Achievement: {$machine->name}",
                    'description' => "{$machine->name} reached only ".round($achievement, 1).'% of its 30-day production target. '
                        .'Total shortfall: '.round($shortfall, 1)." T across {$count} logged shifts.",
                    'confidence_score' => min(0.95, 0.60 + ($count * 0.01)),
                    'estimated_efficiency_gain' => round(100 - $achievement, 1),
                    'related_machine_id' => $machineId,
                    'data' => [
                        'achievement_rate_pct' => round($achievement, 2),
                        'total_produced_t' => round($produced, 2),
                        'total_target_t' => round($target, 2),
                        'shortfall_t' => round($shortfall, 2),
                        'records_analyzed' => $count,
                        'analysis_window_days' => 30,
                    ],
                    'impact_analysis' => [
                        'production_gap' => round($shortfall, 1).' T / 30 days',
                        'recommended_actions' => [
                            'Review operator assignment and proficiency for this machine',
                            'Inspect for mechanical inefficiencies (tyre wear, hydraulics, payload limits)',
                            'Cross-check shift planning — are enough hours allocated?',
                            'Compare against the highest-performing machine of the same type',
                        ],
                    ],
                ];
            }

            if ($achievement >= 105) {
                $insights[] = [
                    'type' => 'trend',
                    'category' => 'production',
                    'severity' => 'success',
                    'title' => "Top Performer: {$machine->name}",
                    'description' => "{$machine->name} exceeded its 30-day target by "
                        .round($achievement - 100, 1).'% ('
                        .round($produced, 1).' T produced vs '
                        .round($target, 1).' T target).',
                    'data' => [
                        'machine_id' => $machineId,
                        'achievement_rate_pct' => round($achievement, 2),
                        'total_produced_t' => round($produced, 2),
                        'total_target_t' => round($target, 2),
                        'records_analyzed' => $count,
                    ],
                    'visualization_data' => [
                        'type' => 'comparison_bar',
                        'produced' => round($produced, 2),
                        'target' => round($target, 2),
                    ],
                    'valid_until' => now()->addDays(7),
                ];
            }
        }

        return ['recommendations' => $recommendations, 'insights' => $insights];
    }

    // -------------------------------------------------------------------------
    // Shift performance comparison
    // -------------------------------------------------------------------------

    /**
     * @return array<mixed>
     */
    protected function analyzeShiftPerformance(Team $team): array
    {
        $recommendations = [];
        $insights = [];

        $records = ProductionRecord::where('team_id', $team->id)
            ->where('record_date', '>=', Carbon::now()->subDays(30))
            ->whereNotNull('shift')
            ->whereNotNull('target_quantity')
            ->where('target_quantity', '>', 0)
            ->get();

        // Need at least 6 records (3 per shift) to make a comparison meaningful
        if ($records->count() < 6) {
            return ['recommendations' => [], 'insights' => []];
        }

        $byShift = $records->groupBy('shift');

        $shiftStats = [];
        foreach ($byShift as $shift => $shiftRecords) {
            $produced = (float) $shiftRecords->sum('quantity_produced');
            $target = (float) $shiftRecords->sum('target_quantity');
            $shiftStats[$shift] = [
                'achievement_rate_pct' => round(($produced / $target) * 100, 2),
                'avg_tonnage_per_shift' => round($produced / $shiftRecords->count(), 2),
                'record_count' => $shiftRecords->count(),
            ];
        }

        // Only compare day vs night if both exist
        if (! isset($shiftStats['day'], $shiftStats['night'])) {
            return ['recommendations' => [], 'insights' => []];
        }

        $dayRate = $shiftStats['day']['achievement_rate_pct'];
        $nightRate = $shiftStats['night']['achievement_rate_pct'];
        $gap = $dayRate - $nightRate;

        if (abs($gap) > 15) {
            $weak = $gap > 0 ? 'night' : 'day';
            $strong = $gap > 0 ? 'day' : 'night';
            $weakRate = $gap > 0 ? $nightRate : $dayRate;
            $strongRate = $gap > 0 ? $dayRate : $nightRate;

            $recommendations[] = [
                'category' => 'production',
                'priority' => abs($gap) > 30 ? 'high' : 'medium',
                'title' => ucfirst($weak).' Shift Underperforming vs '.ucfirst($strong),
                'description' => "The {$weak} shift achieves ".round($weakRate, 1).'% of target compared to '
                    .round($strongRate, 1)."% for the {$strong} shift — a ".round(abs($gap), 1)
                    .'% gap. Based on '.$records->count().' production records over 30 days.',
                'confidence_score' => 0.82,
                'estimated_efficiency_gain' => round(abs($gap) * 0.5, 1),
                'data' => [
                    'shift_stats' => $shiftStats,
                    'performance_gap_pct' => round(abs($gap), 2),
                    'underperforming_shift' => $weak,
                    'outperforming_shift' => $strong,
                    'records_analyzed' => $records->count(),
                ],
                'impact_analysis' => [
                    'potential_gain' => round(abs($gap) * 0.5, 1).'% production increase if gap is halved',
                    'recommended_actions' => [
                        'Review operator experience and certification levels per shift',
                        'Confirm equal machine availability and condition across shifts',
                        'Assess lighting and visibility conditions for night operations',
                        'Standardise shift handover procedures to reduce idle time',
                    ],
                ],
            ];

            $insights[] = [
                'type' => 'trend',
                'category' => 'production',
                'severity' => 'warning',
                'title' => 'Shift Performance Imbalance Detected',
                'description' => ucfirst($weak).' shift is '.round(abs($gap), 1)
                    .'% behind the '.$strong.' shift in target achievement.',
                'data' => [
                    'shift_stats' => $shiftStats,
                    'gap_pct' => round(abs($gap), 2),
                ],
                'visualization_data' => [
                    'type' => 'bar',
                    'labels' => array_keys($shiftStats),
                    'values' => array_column(array_values($shiftStats), 'achievement_rate_pct'),
                ],
            ];
        }

        return ['recommendations' => $recommendations, 'insights' => $insights];
    }

    // -------------------------------------------------------------------------
    // Week-over-week achievement trend
    // -------------------------------------------------------------------------

    /**
     * @return array<mixed>
     */
    protected function analyzeWeekOverWeekTrend(Team $team): array
    {
        $recommendations = [];
        $insights = [];

        $recent = ProductionRecord::where('team_id', $team->id)
            ->where('record_date', '>=', Carbon::now()->subDays(7))
            ->whereNotNull('target_quantity')
            ->where('target_quantity', '>', 0)
            ->get();

        $prior = ProductionRecord::where('team_id', $team->id)
            ->whereBetween('record_date', [
                Carbon::now()->subDays(14)->format('Y-m-d'),
                Carbon::now()->subDays(8)->format('Y-m-d'),
            ])
            ->whereNotNull('target_quantity')
            ->where('target_quantity', '>', 0)
            ->get();

        if ($recent->isEmpty() || $prior->isEmpty()) {
            return ['recommendations' => [], 'insights' => []];
        }

        $recentRate = ((float) $recent->sum('quantity_produced') / (float) $recent->sum('target_quantity')) * 100;
        $priorRate = ((float) $prior->sum('quantity_produced') / (float) $prior->sum('target_quantity')) * 100;
        $change = $recentRate - $priorRate;

        if ($change < -10) {
            $recommendations[] = [
                'category' => 'production',
                'priority' => $change < -20 ? 'high' : 'medium',
                'title' => 'Production Achievement Declining Week-on-Week',
                'description' => 'Overall target achievement dropped from '
                    .round($priorRate, 1).'% (previous 7 days) to '
                    .round($recentRate, 1).'% (last 7 days) — a '
                    .round(abs($change), 1).'% decline. Based on '
                    .($recent->count() + $prior->count()).' production records.',
                'confidence_score' => 0.88,
                'estimated_efficiency_gain' => round(abs($change), 1),
                'data' => [
                    'recent_achievement_rate_pct' => round($recentRate, 2),
                    'prior_achievement_rate_pct' => round($priorRate, 2),
                    'change_pct' => round($change, 2),
                    'recent_records' => $recent->count(),
                    'prior_records' => $prior->count(),
                ],
                'impact_analysis' => [
                    'trend_direction' => 'Downward',
                    'recommended_actions' => [
                        'Identify which machines or areas drove the drop this week',
                        'Check for unplanned downtime events in the current period',
                        'Review target accuracy — were targets raised without resource increase?',
                        'Hold a rapid operational review to surface blockers',
                    ],
                ],
            ];
        }

        if ($change > 10) {
            $insights[] = [
                'type' => 'trend',
                'category' => 'production',
                'severity' => 'success',
                'title' => 'Production Achievement Improving',
                'description' => 'Target achievement rose from '
                    .round($priorRate, 1).'% to '.round($recentRate, 1)
                    .'% week-over-week — a '.round($change, 1).'% improvement.',
                'data' => [
                    'recent_rate_pct' => round($recentRate, 2),
                    'prior_rate_pct' => round($priorRate, 2),
                    'improvement_pct' => round($change, 2),
                ],
            ];
        }

        return ['recommendations' => $recommendations, 'insights' => $insights];
    }

    // -------------------------------------------------------------------------
    // Area performance
    // -------------------------------------------------------------------------

    /**
     * @return array<mixed>
     */
    protected function analyzeAreaPerformance(Team $team): array
    {
        $recommendations = [];
        $insights = [];

        $records = ProductionRecord::where('team_id', $team->id)
            ->where('record_date', '>=', Carbon::now()->subDays(30))
            ->whereNotNull('mine_area_id')
            ->whereNotNull('target_quantity')
            ->where('target_quantity', '>', 0)
            ->with('mineArea:id,name')
            ->get();

        // Need at least 2 distinct areas with 3+ records each to compare
        $byArea = $records->groupBy('mine_area_id')->filter(fn ($recs) => $recs->count() >= 3);

        if ($byArea->count() < 2) {
            return ['recommendations' => [], 'insights' => []];
        }

        $areaStats = $byArea->map(function ($areaRecords) {
            $produced = (float) $areaRecords->sum('quantity_produced');
            $target = (float) $areaRecords->sum('target_quantity');

            return [
                'area_name' => $areaRecords->first()->mineArea?->name ?? 'Unknown',
                'achievement_rate_pct' => round(($produced / $target) * 100, 2),
                'total_produced_t' => round($produced, 2),
                'total_target_t' => round($target, 2),
                'record_count' => $areaRecords->count(),
            ];
        })->values();

        $worst = $areaStats->sortBy('achievement_rate_pct')->first();
        $best = $areaStats->sortByDesc('achievement_rate_pct')->first();

        if ($worst['achievement_rate_pct'] < 70) {
            $gapToBest = $best['achievement_rate_pct'] - $worst['achievement_rate_pct'];
            $recommendations[] = [
                'category' => 'production',
                'priority' => 'medium',
                'title' => "Underperforming Area: {$worst['area_name']}",
                'description' => "{$worst['area_name']} achieved only "
                    .round($worst['achievement_rate_pct'], 1).'% of its 30-day target. '
                    ."Best area {$best['area_name']} achieved ".round($best['achievement_rate_pct'], 1)
                    .'% — a '.round($gapToBest, 1).'% gap.',
                'confidence_score' => 0.80,
                'estimated_efficiency_gain' => round($gapToBest * 0.4, 1),
                'data' => [
                    'worst_area' => $worst,
                    'best_area' => $best,
                    'all_areas' => $areaStats->toArray(),
                    'records_analyzed' => $records->count(),
                ],
                'impact_analysis' => [
                    'gap_to_best' => round($gapToBest, 1).'% achievement gap',
                    'recommended_actions' => [
                        'Audit equipment allocation in this area vs the best-performing area',
                        'Review geological conditions and bench quality affecting output',
                        'Compare staffing levels and operator experience between areas',
                        'Investigate blast timing, loading efficiency, and haul road conditions',
                    ],
                ],
            ];
        }

        return ['recommendations' => $recommendations, 'insights' => $insights];
    }
}
