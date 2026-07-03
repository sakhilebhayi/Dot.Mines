<?php

namespace App\Services;

use App\Models\BellEquipment;
use App\Models\BellEquipmentDailyKpi;
use App\Models\MachineMetric;
use Carbon\Carbon;

/**
 * MachineKpiService
 *
 * Integration-agnostic aggregator for daily production KPIs.
 *
 * Data is pulled from every available source in priority order:
 *
 *   1. bell_equipment_daily_kpis  — Bell ISO 15143-3 OEM telemetry.
 *      Dense, authoritative daily summaries of loads, payload, fuel, utilization.
 *
 *   2. machine_metrics            — Generic store for all other OEM adapters and
 *      IoT sensors.  Load events are inferred from rows where load_weight > 0.
 *
 * When a new OEM integration is added, it should write data to machine_metrics
 * (via the standard adapter contract) and this service will automatically
 * surface that data — no changes required here.
 *
 * All callers (Dashboard, ProductionDashboard, Reports, etc.) reference only
 * this service; they have no knowledge of the underlying OEM tables.
 */
class MachineKpiService
{
    /**
     * Aggregate daily production KPIs for a set of machines over a date range.
     *
     * @param  array<int>  $machineIds
     * @return array{
     *   total_loads: int,
     *   total_payload_tonnes: float,
     *   avg_utilization: float,
     *   has_data: bool
     * }
     */
    public function getDailyKpiSummary(array $machineIds, string $startDate, string $endDate): array
    {
        if (empty($machineIds)) {
            return $this->empty();
        }

        $totalLoads = 0;
        $totalPayloadKg = 0.0;
        $utilizationValues = [];
        $hasData = false;

        // ── Source 1: Bell Equipment daily KPI table ─────────────────────────
        $bellKeys = BellEquipment::whereIn('machine_id', $machineIds)
            ->pluck('equipment_key')
            ->all();

        if (! empty($bellKeys)) {
            $bell = BellEquipmentDailyKpi::whereIn('equipment_key', $bellKeys)
                ->whereBetween('kpi_date', [$startDate, $endDate])
                ->selectRaw('
                    SUM(loads_moved)         AS total_loads,
                    SUM(payload_moved)       AS total_payload_kg,
                    AVG(utilization_percent) AS avg_utilization
                ')
                ->first();

            if ($bell && ((int) $bell->total_loads > 0 || (float) $bell->total_payload_kg > 0.0)) {
                $totalLoads += (int) $bell->total_loads;
                $totalPayloadKg += (float) $bell->total_payload_kg;

                if ($bell->avg_utilization !== null) {
                    $utilizationValues[] = (float) $bell->avg_utilization;
                }

                $hasData = true;
            }
        }

        // ── Source 2: Generic machine_metrics (all other OEM adapters) ───────
        $metrics = MachineMetric::whereIn('machine_id', $machineIds)
            ->whereBetween('recorded_at', [
                Carbon::parse($startDate)->startOfDay(),
                Carbon::parse($endDate)->endOfDay(),
            ])
            ->selectRaw('
                SUM(CASE WHEN load_weight > 0 THEN 1 ELSE 0 END) AS total_loads,
                SUM(load_weight) AS total_payload_kg,
                AVG(
                    CASE WHEN total_hours > 0
                         THEN ((total_hours - COALESCE(idle_hours, 0)) / total_hours) * 100
                         ELSE NULL END
                ) AS avg_utilization
            ')
            ->first();

        if ($metrics && ((int) $metrics->total_loads > 0 || (float) ($metrics->total_payload_kg ?? 0) > 0.0)) {
            $totalLoads += (int) $metrics->total_loads;
            $totalPayloadKg += (float) ($metrics->total_payload_kg ?? 0.0);

            if ($metrics->avg_utilization !== null) {
                $utilizationValues[] = (float) $metrics->avg_utilization;
            }

            $hasData = true;
        }

        return [
            'total_loads' => $totalLoads,
            'total_payload_tonnes' => round($totalPayloadKg / 1000, 2),  // kg → tonnes
            'avg_utilization' => ! empty($utilizationValues)
                ? round(array_sum($utilizationValues) / count($utilizationValues), 1)
                : 0.0,
            'has_data' => $hasData,
        ];
    }

    /**
     * Convenience wrapper: today's KPI totals for the dashboard widgets.
     *
     * @param  array<int>  $machineIds
     * @return array{total_loads: int, total_payload_tonnes: float}
     */
    public function getTodayKpis(array $machineIds): array
    {
        $today = today()->toDateString();
        $summary = $this->getDailyKpiSummary($machineIds, $today, $today);

        return [
            'total_loads' => $summary['total_loads'],
            'total_payload_tonnes' => $summary['total_payload_tonnes'],
        ];
    }

    /** @return array{total_loads: int, total_payload_tonnes: float, avg_utilization: float, has_data: bool} */
    private function empty(): array
    {
        return [
            'total_loads' => 0,
            'total_payload_tonnes' => 0.0,
            'avg_utilization' => 0.0,
            'has_data' => false,
        ];
    }
}
