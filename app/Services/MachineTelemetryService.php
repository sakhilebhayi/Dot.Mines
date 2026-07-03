<?php

namespace App\Services;

use App\Models\BellEquipment;
use App\Models\BellEquipmentCurrentStatus;
use App\Models\BellEquipmentLocationHistory;
use App\Models\Machine;
use App\Models\MachineMetric;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * MachineTelemetryService
 *
 * Integration-agnostic telemetry resolver. Returns a standardised snapshot for
 * every machine, pulling from the highest-fidelity source available:
 *
 *   Priority 1 — Bell Equipment (ISO 15143-3 current status + Locations feed)
 *   Priority 2 — Generic MachineMetric table (IoT sensors / other OEM adapters)
 *   Priority 3 — Machine model fields (operating_hours, odometer, last_location_*)
 *
 * When a new OEM integration stores data in machine_metrics (via the generic
 * adapter pattern), this service automatically surfaces it without any code
 * changes — just write to machine_metrics and the service picks it up.
 *
 * Standardised snapshot keys:
 *   status, status_label, status_color  — derived operational state
 *   engine_running (bool|null)          — live engine state
 *   fuel_remaining_percent (float|null) — 0–100 %
 *   operating_hours (float|null)        — cumulative engine hours
 *   idle_hours (float|null)
 *   load_count (int|null)               — cumulative loads (Bell only)
 *   odometer (float|null)               — km
 *   def_percent (float|null)            — Bell only
 *   payload (float|null)                — kg
 *   latitude, longitude (float|null)
 *   speed_kmh (float|null)
 *   last_seen_at, last_seen_human
 *   equipment_key (int|null)            — Bell-specific identifier (null for others)
 *   telemetry_source (string)           — 'bell' | 'machine_metric' | 'machine' | 'none'
 */
class MachineTelemetryService
{
    /**
     * Minutes without telemetry before a machine is considered Offline.
     * Set to 30 min — the ISO15143-3 snapshot runs every 15 min, so one missed
     * cycle should not immediately flip a machine offline.
     */
    private const OFFLINE_MINUTES = 30;

    /** km/h threshold above which the machine is Travelling */
    private const SPEED_THRESHOLD = 3;

    /**
     * Return a standardised telemetry snapshot for every requested machine ID.
     * Results are keyed by machine_id.
     *
     * @param  array<int>  $machineIds
     * @return array<int, array<string, mixed>>
     */
    public function forMachines(array $machineIds): array
    {
        if (empty($machineIds)) {
            return [];
        }

        // ── Priority 1: Bell Equipment ───────────────────────────────────────
        /** @var Collection<int, BellEquipment> $bellEquipment */
        $bellEquipment = BellEquipment::whereIn('machine_id', $machineIds)
            ->with('currentStatus')
            ->get()
            ->keyBy('machine_id');

        $equipmentKeys = $bellEquipment->pluck('equipment_key')->filter()->values()->all();
        $latestSpeeds = $this->latestSpeedByEquipmentKey($equipmentKeys);

        // Collect IDs that have no Bell equipment so we can fall through.
        $nonBellIds = [];

        $result = [];
        foreach ($machineIds as $machineId) {
            /** @var BellEquipment|null $eq */
            $eq = $bellEquipment->get($machineId);
            $status = $eq?->currentStatus;

            if ($eq !== null && $status !== null) {
                $speedKmh = $latestSpeeds[$eq->equipment_key] ?? null;
                $result[$machineId] = $this->buildBellEntry($status, $speedKmh);

                continue;
            }

            $nonBellIds[] = $machineId;
        }

        if (empty($nonBellIds)) {
            return $result;
        }

        // ── Priority 2: Generic MachineMetric (latest record per machine) ───
        $latestMetrics = $this->latestMetricByMachineId($nonBellIds);
        $stillMissing = [];

        foreach ($nonBellIds as $machineId) {
            if (isset($latestMetrics[$machineId])) {
                $result[$machineId] = $this->buildMetricEntry($latestMetrics[$machineId]);

                continue;
            }

            $stillMissing[] = $machineId;
        }

        if (empty($stillMissing)) {
            return $result;
        }

        // ── Priority 3: Machine model fields (last-known position / status) ──
        $machines = Machine::whereIn('id', $stillMissing)->get()->keyBy('id');

        foreach ($stillMissing as $machineId) {
            $machine = $machines->get($machineId);
            $result[$machineId] = $machine !== null
                ? $this->buildMachineEntry($machine)
                : $this->noTelemetry();
        }

        return $result;
    }

    /**
     * Return a standardised telemetry snapshot for a single machine.
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

    /**
     * Return the single most-recent MachineMetric row per machine ID.
     *
     * @param  array<int>  $machineIds
     * @return array<int, MachineMetric> machine_id → MachineMetric
     */
    private function latestMetricByMachineId(array $machineIds): array
    {
        if (empty($machineIds)) {
            return [];
        }

        // Subquery: max recorded_at per machine_id
        $rows = MachineMetric::whereIn('machine_id', $machineIds)
            ->orderByDesc('recorded_at')
            ->get()
            ->unique('machine_id')
            ->keyBy('machine_id');

        /** @var array<int, MachineMetric> $result */
        $result = [];
        foreach ($rows as $id => $row) {
            $result[(int) $id] = $row;
        }

        return $result;
    }

    // ── Entry builders ───────────────────────────────────────────────────────

    /** @return array<string, mixed> */
    private function buildBellEntry(BellEquipmentCurrentStatus $status, ?float $speedKmh): array
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
            'telemetry_source' => 'bell',
        ];
    }

    /** @return array<string, mixed> */
    private function buildMetricEntry(MachineMetric $metric): array
    {
        // recorded_at is cast to datetime in MachineMetric — always a Carbon instance.
        $lastSeen = Carbon::parse($metric->recorded_at);
        $isOffline = $lastSeen->lt(now()->subMinutes(self::OFFLINE_MINUTES));

        // Infer engine running from RPM when available.
        $engineRunning = $metric->engine_rpm !== null ? $metric->engine_rpm > 100 : null;

        $operationalStatus = $this->deriveStatus(
            engineRunning: (bool) $engineRunning,
            isOffline: $isOffline,
            speedKmh: $metric->speed,
            operatingHours: $metric->total_hours,
            idleHours: $metric->idle_hours,
        );

        [$label, $color] = $this->labelAndColor($operationalStatus);

        return [
            'status' => $operationalStatus,
            'status_label' => $label,
            'status_color' => $color,
            'engine_running' => $engineRunning,
            'fuel_remaining_percent' => $metric->fuel_level !== null ? (float) $metric->fuel_level : null,
            'operating_hours' => $metric->total_hours !== null ? (float) $metric->total_hours : null,
            'idle_hours' => $metric->idle_hours !== null ? (float) $metric->idle_hours : null,
            'load_count' => null,
            'odometer' => null,
            'def_percent' => null,
            'payload' => $metric->load_weight !== null ? (float) $metric->load_weight : null,
            'latitude' => $metric->latitude !== null ? (float) $metric->latitude : null,
            'longitude' => $metric->longitude !== null ? (float) $metric->longitude : null,
            'speed_kmh' => $metric->speed !== null ? (float) $metric->speed : null,
            'last_seen_at' => $lastSeen?->toIso8601String(),
            'last_seen_human' => $lastSeen?->diffForHumans(),
            'equipment_key' => null,
            'telemetry_source' => 'machine_metric',
        ];
    }

    /** @return array<string, mixed> */
    private function buildMachineEntry(Machine $machine): array
    {
        $lastSeen = $machine->last_seen_at
            ? Carbon::parse($machine->last_seen_at)
            : null;
        $isOffline = ! $lastSeen || $lastSeen->lt(now()->subMinutes(self::OFFLINE_MINUTES));

        $operationalStatus = match ($machine->status) {
            'maintenance' => 'maintenance',
            'offline' => 'offline',
            'active' => $isOffline ? 'offline' : 'working',
            default => $isOffline ? 'offline' : 'parked',
        };

        [$label, $color] = $this->labelAndColor($operationalStatus);

        return [
            'status' => $operationalStatus,
            'status_label' => $label,
            'status_color' => $color,
            'engine_running' => $machine->status === 'active' && ! $isOffline,
            'fuel_remaining_percent' => null,
            'operating_hours' => $machine->operating_hours !== null ? (float) $machine->operating_hours : null,
            'idle_hours' => null,
            'load_count' => null,
            'odometer' => $machine->odometer !== null ? (float) $machine->odometer : null,
            'def_percent' => null,
            'payload' => null,
            'latitude' => $machine->last_location_latitude !== null ? (float) $machine->last_location_latitude : null,
            'longitude' => $machine->last_location_longitude !== null ? (float) $machine->last_location_longitude : null,
            'speed_kmh' => null,
            'last_seen_at' => $lastSeen?->toIso8601String(),
            'last_seen_human' => $lastSeen?->diffForHumans(),
            'equipment_key' => null,
            'telemetry_source' => 'machine',
        ];
    }

    // ── Status derivation ────────────────────────────────────────────────────

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

        // Engine is running — determine sub-state from latest GPS speed.
        // NOTE: We intentionally do NOT use the cumulative idle-hours ratio here.
        // The ISO15143-3 API only provides lifetime-cumulative idle hours, which
        // creates a false positive: a machine that has historically idled a lot
        // will always appear as "Idling" even when actively working.
        // Speed from the 5-minute Locations feed is a far more reliable real-time
        // indicator of current operational state.
        if ($speedKmh !== null && $speedKmh > self::SPEED_THRESHOLD) {
            return 'travelling';
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
            'telemetry_source' => 'none',
        ];
    }
}
