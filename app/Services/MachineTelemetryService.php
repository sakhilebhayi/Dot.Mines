<?php

namespace App\Services;

use App\Models\BellEquipment;
use App\Models\BellEquipmentCurrentStatus;
use App\Models\BellEquipmentLocationHistory;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * MachineTelemetryService
 *
 * Resolves live Bell telemetry for a set of machines and derives operational
 * status labels from the available signals (engine_running bool, idle ratio,
 * recent speed, last-seen timestamp).
 *
 * Bell ISO 15143-3 provides:
 *   engine_running, operating_hours, idle_hours, load_count, payload,
 *   def_percent, odometer, fuel_remaining_percent, latitude, longitude.
 *
 * Status derivation (in priority order):
 *   Offline    → last telemetry > OFFLINE_MINUTES ago
 *   Maintenance→ machine.status === 'maintenance'
 *   Parked     → engine_running = false
 *   Travelling → engine_running + latest speed_kmh > SPEED_THRESHOLD
 *   Idling     → engine_running + idle_ratio > IDLE_RATIO_THRESHOLD
 *   Working    → engine_running (default when running)
 */
class MachineTelemetryService
{
    /** Minutes without telemetry before a machine is considered Offline */
    private const OFFLINE_MINUTES = 15;

    /** km/h threshold above which the machine is Travelling */
    private const SPEED_THRESHOLD = 3;

    /** Idle hours / operating hours ratio above which the machine is Idling */
    private const IDLE_RATIO_THRESHOLD = 0.75;

    /**
     * Load Bell telemetry for all given machine IDs in two bulk queries.
     *
     * @param  array<int>  $machineIds
     * @return array<int, array<string, mixed>>
     */
    public function forMachines(array $machineIds): array
    {
        if (empty($machineIds)) {
            return [];
        }

        // 1. Load current status for all linked Bell equipment in one query
        /** @var Collection<int, BellEquipment> $bellEquipment */
        $bellEquipment = BellEquipment::whereIn('machine_id', $machineIds)
            ->with('currentStatus')
            ->get()
            ->keyBy('machine_id');

        // 2. Collect equipment keys to fetch latest speed per machine
        $equipmentKeys = $bellEquipment->pluck('equipment_key')->filter()->values()->all();

        $latestSpeeds = $this->latestSpeedByEquipmentKey($equipmentKeys);

        $result = [];
        foreach ($machineIds as $machineId) {
            /** @var BellEquipment|null $eq */
            $eq = $bellEquipment->get($machineId);
            $status = $eq?->currentStatus;

            if ($eq === null || $status === null) {
                $result[$machineId] = $this->noTelemetry();

                continue;
            }

            $speedKmh = $latestSpeeds[$eq->equipment_key] ?? null;
            $result[$machineId] = $this->buildEntry($status, $speedKmh);
        }

        return $result;
    }

    /**
     * Load telemetry for a single machine.
     *
     * @return array<string, mixed>
     */
    public function forMachine(int $machineId): array
    {
        return $this->forMachines([$machineId])[$machineId] ?? $this->noTelemetry();
    }

    // ── Private helpers ─────────────────────────────────────────────────────

    /**
     * @param  array<int>  $equipmentKeys
     * @return array<int, float> equipment_key → speed_kmh
     */
    private function latestSpeedByEquipmentKey(array $equipmentKeys): array
    {
        if (empty($equipmentKeys)) {
            return [];
        }

        // Get the single most-recent location record per equipment key
        $rows = BellEquipmentLocationHistory::whereIn('equipment_key', $equipmentKeys)
            ->whereNotNull('speed_kmh')
            ->select('equipment_key', 'speed_kmh', 'recorded_at')
            ->orderByDesc('recorded_at')
            ->get()
            ->unique('equipment_key')
            ->keyBy('equipment_key');

        $speeds = [];
        foreach ($rows as $key => $row) {
            $speeds[(int) $key] = (float) $row->speed_kmh;
        }

        return $speeds;
    }

    /** @return array<string, mixed> */
    private function buildEntry(BellEquipmentCurrentStatus $status, ?float $speedKmh): array
    {
        $lastSeen = $status->updated_date ? Carbon::parse($status->updated_date) : null;
        $isOffline = ! $lastSeen || $lastSeen->lt(now()->subMinutes(self::OFFLINE_MINUTES));

        $operationalStatus = $this->deriveStatus(
            engineRunning: (bool) $status->engine_running,
            isOffline: $isOffline,
            speedKmh: $speedKmh,
            operatingHours: $status->operating_hours ? (float) $status->operating_hours : null,
            idleHours: $status->idle_hours ? (float) $status->idle_hours : null,
        );

        [$label, $color] = $this->labelAndColor($operationalStatus);

        return [
            'status' => $operationalStatus,
            'status_label' => $label,
            'status_color' => $color,
            'engine_running' => (bool) $status->engine_running,
            'fuel_remaining_percent' => $status->fuel_remaining_percent !== null ? (float) $status->fuel_remaining_percent : null,
            'operating_hours' => $status->operating_hours !== null ? (float) $status->operating_hours : null,
            'idle_hours' => $status->idle_hours !== null ? (float) $status->idle_hours : null,
            'load_count' => $status->load_count !== null ? (int) $status->load_count : null,
            'odometer' => $status->odometer !== null ? (float) $status->odometer : null,
            'def_percent' => $status->def_percent !== null ? (float) $status->def_percent : null,
            'payload' => $status->payload !== null ? (float) $status->payload : null,
            'latitude' => $status->latitude !== null ? (float) $status->latitude : null,
            'longitude' => $status->longitude !== null ? (float) $status->longitude : null,
            'speed_kmh' => $speedKmh,
            'last_seen_at' => $lastSeen?->toIso8601String(),
            'last_seen_human' => $lastSeen?->diffForHumans(),
            'equipment_key' => $status->equipment_key,
        ];
    }

    private function deriveStatus(
        bool $engineRunning,
        bool $isOffline,
        ?float $speedKmh,
        ?float $operatingHours,
        ?float $idleHours,
    ): string {
        if ($isOffline) {
            return 'offline';
        }

        if (! $engineRunning) {
            return 'parked';
        }

        // Engine is running — determine sub-state
        if ($speedKmh !== null && $speedKmh > self::SPEED_THRESHOLD) {
            return 'travelling';
        }

        if ($operatingHours > 0 && $idleHours !== null) {
            $idleRatio = $idleHours / $operatingHours;
            if ($idleRatio > self::IDLE_RATIO_THRESHOLD) {
                return 'idling';
            }
        }

        return 'working';
    }

    /**
     * @return array{0: string, 1: string} [label, tailwind_color_class]
     */
    private function labelAndColor(string $status): array
    {
        return match ($status) {
            'working' => ['Working',     'emerald'],
            'travelling' => ['Travelling',  'cyan'],
            'idling' => ['Idling',       'amber'],
            'parked' => ['Parked',       'slate'],
            'offline' => ['Offline',      'red'],
            'maintenance' => ['Maintenance',  'orange'],
            default => ['Unknown',      'gray'],
        };
    }

    /** @return array<string, mixed> */
    private function noTelemetry(): array
    {
        return [
            'status' => 'offline',
            'status_label' => 'No Data',
            'status_color' => 'gray',
            'engine_running' => false,
            'fuel_remaining_percent' => null,
            'operating_hours' => null,
            'idle_hours' => null,
            'load_count' => null,
            'odometer' => null,
            'def_percent' => null,
            'payload' => null,
            'latitude' => null,
            'longitude' => null,
            'speed_kmh' => null,
            'last_seen_at' => null,
            'last_seen_human' => null,
            'equipment_key' => null,
        ];
    }
}
