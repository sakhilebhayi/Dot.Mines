<?php

namespace App\Services\Integration;

use App\Events\BellMachineHealthChanged;
use App\Events\BellMachineOfflineDetected;
use App\Models\BellEquipment;
use App\Models\BellEquipmentCautionCode;
use App\Models\BellEquipmentCurrentStatus;
use App\Models\BellEquipmentHealthHistory;
use App\Models\BellEquipmentIdleHoursHistory;
use App\Models\BellEquipmentOperatingHoursHistory;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Bell Machine Intelligence Service
 *
 * Aggregates Bell Equipment telemetry data to compute machine-level KPIs,
 * health scores, utilisation metrics, and actionable recommendations.
 *
 * This service is the analytics brain of the Bell integration — it does NOT
 * pull data from the API (that is BellHistoricalTelemetryService's job).
 * Instead it reads from the existing telemetry tables and produces intelligence
 * that feeds:
 *
 *   - fleet-intelligence-agent
 *   - production-intelligence-agent
 *   - maintenance-guardian
 *   - dispatch-optimization-agent
 *   - esg-sustainability-agent
 *   - enterprise-decision-intelligence
 *
 * Health Score Dimensions (each 0–100, weighted average):
 *   Engine Condition     30%   — based on EngineCondition signal
 *   Caution Codes        25%   — count and severity of open codes
 *   Idle Hours Ratio     15%   — idle / total operating hours
 *   Fuel Efficiency      15%   — L/h or trend vs fleet average
 *   Regen Hours Ratio    15%   — active regen / total hours
 */
class BellMachineIntelligenceService
{
    /** Thresholds */
    private const FUEL_LOW_PERCENT = 20.0;

    private const IDLE_HIGH_THRESHOLD = 0.35; // 35% idle = poor

    private const REGEN_HIGH_THRESHOLD = 0.10; // >10% regen hours = warning

    private const HEALTH_CHANGE_THRESHOLD = 10.0; // points

    // ------------------------------------------------------------------ //
    // Public API                                                           //
    // ------------------------------------------------------------------ //

    /**
     * Compute a comprehensive intelligence snapshot for a single Bell machine.
     *
     * @return array{
     *   equipment_id: string,
     *   health_score: float,
     *   utilisation_percent: float,
     *   idle_ratio_percent: float,
     *   regen_ratio_percent: float,
     *   fuel_efficiency_score: float,
     *   engine_condition_score: float,
     *   caution_score: float,
     *   open_caution_codes: int,
     *   total_operating_hours: float|null,
     *   recommendations: list<string>,
     *   confidence: float,
     * }
     */
    public function computeMachineSnapshot(BellEquipment $equipment): array
    {
        $currentStatus = BellEquipmentCurrentStatus::where('equipment_key', $equipment->equipment_key)->first();
        $operatingHours = $this->latestOperatingHours($equipment->equipment_key);
        $idleHours = $this->latestIdleHours($equipment->equipment_key);
        $openCautionCodes = $this->openCautionCodeCount($equipment->equipment_key);
        $engineConditionScore = $this->engineConditionScore($equipment->equipment_key);
        $fuelEfficiencyScore = $this->fuelEfficiencyScore($equipment->equipment_key);
        $regenRatio = $this->regenRatioPercent($equipment->equipment_key);

        $idleRatio = ($operatingHours > 0 && $idleHours !== null)
            ? round($idleHours / $operatingHours * 100, 2)
            : 0.0;

        $utilizationPercent = max(0, 100 - $idleRatio);

        $cautionScore = $this->cautionScore($openCautionCodes);
        $idleScore = max(0, 100 - ($idleRatio * 2)); // penalise heavily
        $regenScore = max(0, 100 - ($regenRatio * 5)); // >10% regen triggers penalty

        $healthScore = round(
            ($engineConditionScore * 0.30)
            + ($cautionScore * 0.25)
            + ($idleScore * 0.15)
            + ($fuelEfficiencyScore * 0.15)
            + ($regenScore * 0.15),
            1
        );

        $recommendations = $this->buildRecommendations(
            equipment: $equipment,
            currentStatus: $currentStatus,
            idleRatio: $idleRatio,
            regenRatio: $regenRatio,
            openCautionCodes: $openCautionCodes,
            engineConditionScore: $engineConditionScore,
            healthScore: $healthScore,
        );

        // Evidence items present determine confidence
        $evidenceCount = collect([
            $currentStatus !== null,
            $operatingHours > 0,
            $idleHours !== null,
            $openCautionCodes >= 0,
            $engineConditionScore > 0,
        ])->filter()->count();
        $confidence = round($evidenceCount / 5 * 100, 1);

        return [
            'equipment_id' => $equipment->equipment_id,
            'health_score' => $healthScore,
            'utilisation_percent' => round($utilizationPercent, 2),
            'idle_ratio_percent' => $idleRatio,
            'regen_ratio_percent' => $regenRatio,
            'fuel_efficiency_score' => $fuelEfficiencyScore,
            'engine_condition_score' => $engineConditionScore,
            'caution_score' => $cautionScore,
            'open_caution_codes' => $openCautionCodes,
            'total_operating_hours' => $operatingHours > 0 ? $operatingHours : null,
            'recommendations' => $recommendations,
            'confidence' => $confidence,
        ];
    }

    /**
     * Compute snapshots for an entire fleet and return sorted by health score asc
     * (most critical machines first).
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function computeFleetSnapshots(): Collection
    {
        return BellEquipment::orderBy('equipment_key')
            ->get()
            ->map(fn (BellEquipment $eq) => $this->computeMachineSnapshot($eq))
            ->sortBy('health_score')
            ->values();
    }

    /**
     * Calculate ESG carbon output estimate for a single machine.
     * Uses 2.68 kg CO₂ per litre of diesel (IPCC standard factor).
     *
     * @return array{
     *   equipment_id: string,
     *   estimated_fuel_litres: float|null,
     *   estimated_co2_kg: float|null,
     *   fuel_per_operating_hour: float|null,
     * }
     */
    public function computeEsgMetrics(BellEquipment $equipment): array
    {
        $currentStatus = BellEquipmentCurrentStatus::where('equipment_key', $equipment->equipment_key)->first();

        $fuelConsumed = $currentStatus?->fuel_consumed ?? null;
        $operatingHours = $this->latestOperatingHours($equipment->equipment_key);

        $co2Kg = $fuelConsumed !== null ? round((float) $fuelConsumed * 2.68, 2) : null;
        $fuelPerHour = ($fuelConsumed !== null && $operatingHours > 0)
            ? round($fuelConsumed / $operatingHours, 2)
            : null;

        return [
            'equipment_id' => $equipment->equipment_id,
            'estimated_fuel_litres' => $fuelConsumed !== null ? (float) $fuelConsumed : null,
            'estimated_co2_kg' => $co2Kg,
            'fuel_per_operating_hour' => $fuelPerHour,
        ];
    }

    /**
     * Detect Bell machines that have not reported telemetry within the given window
     * and fire BellMachineOfflineDetected events.
     *
     * @return list<array{equipment_id: string, offline_minutes: int}>
     */
    public function detectOfflineMachines(int $thresholdMinutes = 120): array
    {
        $offline = [];
        $cutoff = now()->subMinutes($thresholdMinutes);

        BellEquipment::orderBy('equipment_key')->each(function (BellEquipment $eq) use ($cutoff, &$offline) {
            $status = BellEquipmentCurrentStatus::where('equipment_key', $eq->equipment_key)->first();

            if ($status === null || $status->updated_date === null) {
                return;
            }

            $lastSeen = Carbon::parse($status->updated_date);

            if ($lastSeen->lessThan($cutoff)) {
                $offlineMinutes = (int) $lastSeen->diffInMinutes(now());
                BellMachineOfflineDetected::dispatch($eq, $lastSeen, $offlineMinutes);
                $offline[] = ['equipment_id' => $eq->equipment_id, 'offline_minutes' => $offlineMinutes];
            }
        });

        return $offline;
    }

    /**
     * Check current health score against last stored score and fire
     * BellMachineHealthChanged if the delta exceeds the threshold.
     */
    public function checkAndFireHealthChange(BellEquipment $equipment, float $newScore): void
    {
        $lastRecord = BellEquipmentHealthHistory::where('equipment_key', $equipment->equipment_key)
            ->orderByDesc('recorded_at')
            ->skip(1)  // skip current, get previous
            ->first();

        if ($lastRecord === null) {
            return;
        }

        $previous = (float) ($lastRecord->health_score ?? 0);
        $delta = abs($newScore - $previous);

        if ($delta >= self::HEALTH_CHANGE_THRESHOLD) {
            $reason = $newScore < $previous ? 'Health score degraded' : 'Health score improved';
            BellMachineHealthChanged::dispatch($equipment, $previous, $newScore, $reason, now());
        }
    }

    // ------------------------------------------------------------------ //
    // Private scoring helpers                                              //
    // ------------------------------------------------------------------ //

    private function latestOperatingHours(int $equipmentKey): float
    {
        $record = BellEquipmentOperatingHoursHistory::where('equipment_key', $equipmentKey)
            ->orderByDesc('recorded_at')
            ->first();

        return $record !== null ? (float) $record->operating_hours : 0.0;
    }

    private function latestIdleHours(int $equipmentKey): ?float
    {
        $record = BellEquipmentIdleHoursHistory::where('equipment_key', $equipmentKey)
            ->orderByDesc('recorded_at')
            ->first();

        return $record !== null ? (float) $record->idle_hours : null;
    }

    private function openCautionCodeCount(int $equipmentKey): int
    {
        return BellEquipmentCautionCode::where('equipment_key', $equipmentKey)
            ->whereNull('resolved_at')
            ->count();
    }

    private function engineConditionScore(int $equipmentKey): float
    {
        $record = BellEquipmentHealthHistory::where('equipment_key', $equipmentKey)
            ->whereNotNull('engine_condition')
            ->orderByDesc('recorded_at')
            ->first();

        if ($record === null) {
            return 50.0; // neutral when no data
        }

        return match (strtolower((string) $record->engine_condition)) {
            'normal', 'ok' => 100.0,
            'warning' => 55.0,
            'error', 'fault', 'critical' => 10.0,
            default => 50.0,
        };
    }

    private function fuelEfficiencyScore(int $equipmentKey): float
    {
        // Compare latest fuel_remaining vs fleet average — returns 0–100
        $status = BellEquipmentCurrentStatus::where('equipment_key', $equipmentKey)->first();
        if ($status === null || $status->fuel_remaining_percent === null) {
            return 50.0;
        }

        $pct = (float) $status->fuel_remaining_percent;
        if ($pct <= self::FUEL_LOW_PERCENT) {
            return 20.0;
        }
        if ($pct <= 40.0) {
            return 55.0;
        }

        return 100.0;
    }

    private function regenRatioPercent(int $equipmentKey): float
    {
        $operatingHours = $this->latestOperatingHours($equipmentKey);
        if ($operatingHours <= 0) {
            return 0.0;
        }

        $regenRecord = BellEquipmentHealthHistory::where('equipment_key', $equipmentKey)
            ->whereNotNull('active_regen_hours')
            ->orderByDesc('recorded_at')
            ->first();

        if ($regenRecord === null) {
            return 0.0;
        }

        return round((float) $regenRecord->active_regen_hours / $operatingHours * 100, 2);
    }

    private function cautionScore(int $openCodes): float
    {
        return match (true) {
            $openCodes === 0 => 100.0,
            $openCodes <= 2 => 75.0,
            $openCodes <= 5 => 45.0,
            default => 10.0,
        };
    }

    /**
     * @return list<string>
     */
    private function buildRecommendations(
        BellEquipment $equipment,
        ?BellEquipmentCurrentStatus $currentStatus,
        float $idleRatio,
        float $regenRatio,
        int $openCautionCodes,
        float $engineConditionScore,
        float $healthScore,
    ): array {
        $recommendations = [];

        if ($engineConditionScore <= 10.0) {
            $recommendations[] = 'Engine fault detected — remove from production immediately and inspect.';
        } elseif ($engineConditionScore <= 55.0) {
            $recommendations[] = 'Engine warning active — schedule inspection within next shift.';
        }

        if ($openCautionCodes >= 3) {
            $recommendations[] = "Resolve {$openCautionCodes} open caution codes before next dispatch.";
        }

        if ($idleRatio > (self::IDLE_HIGH_THRESHOLD * 100)) {
            $recommendations[] = sprintf(
                'Idle ratio is %.1f%% — review dispatch scheduling to reduce queue time.',
                $idleRatio
            );
        }

        if ($regenRatio > (self::REGEN_HIGH_THRESHOLD * 100)) {
            $recommendations[] = 'Elevated DPF regeneration hours — check operating duty cycle and consider maintenance.';
        }

        if ($currentStatus !== null && $currentStatus->fuel_remaining_percent !== null) {
            if ((float) $currentStatus->fuel_remaining_percent <= self::FUEL_LOW_PERCENT) {
                $recommendations[] = sprintf(
                    'Fuel at %.0f%% — schedule refuelling before next shift.',
                    $currentStatus->fuel_remaining_percent
                );
            }
        }

        if ($healthScore < 40.0) {
            $recommendations[] = 'Overall machine health is critical — restrict to light-duty or pull from service.';
        } elseif ($healthScore < 65.0) {
            $recommendations[] = 'Machine health is below threshold — prioritise for maintenance within 24 hours.';
        }

        return $recommendations;
    }
}
