<?php

namespace App\Services\Integration;

use App\Models\BellEquipment;
use App\Models\BellEquipmentCautionCode;
use App\Models\BellEquipmentDailyKpi;
use Carbon\Carbon;

class BellTeamInsightsService
{
    /**
     * @return array{
     *   totals: array{machines: int, running: int, issues: int, monthly_loads: int, monthly_payload: float, monthly_fuel: float},
     *   machines: array<int, array<string, mixed>>,
     * }
     */
    public function getTeamOverview(int $teamId, ?Carbon $monthStart = null): array
    {
        $monthStart ??= now()->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();

        $bellEquipments = BellEquipment::query()
            ->whereHas('machine', function ($query) use ($teamId): void {
                $query->where('team_id', $teamId);
            })
            ->with(['machine', 'currentStatus'])
            ->get();

        $monthlyKpis = BellEquipmentDailyKpi::query()
            ->whereHas('equipment.machine', function ($query) use ($teamId): void {
                $query->where('team_id', $teamId);
            })
            ->whereBetween('kpi_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->get()
            ->groupBy('equipment_key');

        $machineSummaries = $bellEquipments->map(function (BellEquipment $equipment) use ($monthlyKpis): array {
            $status = $equipment->currentStatus;
            $openCautionCodes = BellEquipmentCautionCode::query()
                ->where('equipment_key', $equipment->equipment_key)
                ->where('is_active', true)
                ->get();

            $monthlyKpi = $monthlyKpis->get($equipment->equipment_key)?->last();

            return [
                'equipment_key' => $equipment->equipment_key,
                'machine_name' => $equipment->machine?->name ?? $equipment->equipment_id,
                'equipment_id' => $equipment->equipment_id,
                'status' => $status?->engine_running ? 'running' : 'idle',
                'load_count' => (int) ($status?->load_count ?? 0),
                'fuel_remaining_percent' => (float) ($status?->fuel_remaining_percent ?? 0),
                'operating_hours' => (float) ($status?->operating_hours ?? 0),
                'monthly_loads' => (int) ($monthlyKpi?->loads_moved ?? 0),
                'monthly_payload' => (float) ($monthlyKpi?->payload_moved ?? 0),
                'monthly_fuel' => (float) ($monthlyKpi?->fuel_used ?? 0),
                'monthly_utilization' => (float) ($monthlyKpi?->utilization_percent ?? 0),
                'open_caution_codes' => $openCautionCodes->pluck('fault_code')->all(),
            ];
        })->values()->all();

        $totals = [
            'machines' => count($machineSummaries),
            'running' => collect($machineSummaries)->where('status', 'running')->count(),
            'issues' => collect($machineSummaries)->sum(fn (array $machine) => count($machine['open_caution_codes'])),
            'monthly_loads' => collect($machineSummaries)->sum('monthly_loads'),
            'monthly_payload' => round(collect($machineSummaries)->sum('monthly_payload'), 2),
            'monthly_fuel' => round(collect($machineSummaries)->sum('monthly_fuel'), 2),
        ];

        return [
            'totals' => $totals,
            'machines' => $machineSummaries,
        ];
    }
}
