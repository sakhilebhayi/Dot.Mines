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
 *   idle_hours (float|null)             — cumulative idle hours
 *   working_hours (float|null)          — operating_hours − idle_hours
 *   load_count (int|null)               — cumulative loads (Bell only)
 *   odometer (float|null)               — km
 *   def_percent (float|null)            — Bell only
 *   payload (float|null)                — kg
 *   latitude, longitude (float|null)
 *   speed_kmh (float|null)
 *   heading_degrees (float|null)        — GPS heading 0–360
 *   engine_rpm (float|null)             — from MachineMetric
 *   coolant_temperature (float|null)    — °C from MachineMetric
 *   engine_temperature (float|null)     — °C from MachineMetric
 *   battery_voltage (float|null)        — V from MachineMetric
 *   last_seen_at, last_seen_human
 *   data_age_minutes (int|null)         — minutes since last telemetry
 *   is_stale (bool)                     — true when data is > STALE_MINUTES old
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

    /**
     * Minutes after which data is considered stale but not yet offline.
     * Show a warning badge when data is between STALE_MINUTES and OFFLINE_MINUTES old.
     */
    private const STALE_MINUTES = 15;

    /** km/h threshold above which the machine is Travelling */
    private const SPEED_THRESHOLD = 3;

    /**
     * Payload threshold (kg) above which the machine is considered under load.
     * Bell ISO15143-3 CumulativePayload is in kg. Anything above this threshold
     * means the machine is carrying a load and is likely Working (not Idling).
     */
    private const PAYLOAD_THRESHOLD_KG = 500;

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
                $locationSnap = $latestSpeeds[$eq->equipment_key] ?? [];
                $speedKmh = $locationSnap['speed_kmh'] ?? null;
                $headingDegrees = $locationSnap['heading_degrees'] ?? null;
                $result[$machineId] = $this->buildBellEntry($status, $speedKmh, $headingDegrees);

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
     * @return array<int, array{speed_kmh: float|null, heading_degrees: float|null}> equipment_key → location snapshot
     */
    private function latestSpeedByEquipmentKey(array $equipmentKeys): array
    {
        if (empty($equipmentKeys)) {
            return [];
        }

        $rows = BellEquipmentLocationHistory::whereIn('equipment_key', $equipmentKeys)
            ->select('equipment_key', 'speed_kmh', 'heading_degrees', 'recorded_at')
            ->orderByDesc('recorded_at')
            ->get()
            ->unique('equipment_key')
            ->keyBy('equipment_key');

        $result = [];
        foreach ($rows as $key => $row) {
            $result[(int) $key] = [
                'speed_kmh' => $row->speed_kmh !== null ? (float) $row->speed_kmh : null,
                'heading_degrees' => $row->heading_degrees !== null ? (float) $row->heading_degrees : null,
            ];
        }

        return $result;
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
    private function buildBellEntry(BellEquipmentCurrentStatus $status, ?float $speedKmh, ?float $headingDegrees = null): array
    {
        $lastSeen = $status->updated_date ? Carbon::parse($status->updated_date) : null;
        $isOffline = ! $lastSeen || $lastSeen->lt(now()->subMinutes(self::OFFLINE_MINUTES));
        $isStale = ! $isOffline && $lastSeen && $lastSeen->lt(now()->subMinutes(self::STALE_MINUTES));
        $dataAgeMinutes = $lastSeen ? (int) $lastSeen->diffInMinutes(now()) : null;

        $payloadKg = $status->payload !== null ? (float) $status->payload : null;

        $operationalStatus = $this->deriveStatus(
            engineRunning: (bool) $status->engine_running,
            isOffline: $isOffline,
            speedKmh: $speedKmh,
            payloadKg: $payloadKg,
        );

        [$label, $color] = $this->labelAndColor($operationalStatus);

        $operatingHours = $status->operating_hours !== null ? (float) $status->operating_hours : null;
        $idleHours = $status->idle_hours !== null ? (float) $status->idle_hours : null;
        $workingHours = ($operatingHours !== null && $idleHours !== null)
            ? max(0.0, round($operatingHours - $idleHours, 2))
            : null;

        return [
            'status' => $operationalStatus,
            'status_label' => $label,
            'status_color' => $color,
            'engine_running' => (bool) $status->engine_running,
            'fuel_remaining_percent' => $status->fuel_remaining_percent !== null ? (float) $status->fuel_remaining_percent : null,
            'operating_hours' => $operatingHours,
            'idle_hours' => $idleHours,
            'working_hours' => $workingHours,
            'load_count' => $status->load_count !== null ? (int) $status->load_count : null,
            'odometer' => $status->odometer !== null ? (float) $status->odometer : null,
            'def_percent' => $status->def_percent !== null ? (float) $status->def_percent : null,
            'payload' => $payloadKg,
            'latitude' => $status->latitude !== null ? (float) $status->latitude : null,
            'longitude' => $status->longitude !== null ? (float) $status->longitude : null,
            'speed_kmh' => $speedKmh,
            'heading_degrees' => $headingDegrees,
            // MachineMetric-only fields (null for Bell ISO15143-3 which doesn't report these)
            'engine_rpm' => null,
            'coolant_temperature' => null,
            'engine_temperature' => null,
            'battery_voltage' => null,
            'last_seen_at' => $lastSeen?->toIso8601String(),
            'last_seen_human' => $lastSeen?->diffForHumans(),
            'data_age_minutes' => $dataAgeMinutes,
            'is_stale' => $isStale,
            'equipment_key' => $status->equipment_key,
            'telemetry_source' => 'bell',
        ];
    }

    /** @return array<string, mixed> */
    private function buildMetricEntry(MachineMetric $metric): array
    {
        $lastSeen = Carbon::parse($metric->recorded_at);
        $isOffline = $lastSeen->lt(now()->subMinutes(self::OFFLINE_MINUTES));
        $isStale = ! $isOffline && $lastSeen->lt(now()->subMinutes(self::STALE_MINUTES));
        $dataAgeMinutes = (int) $lastSeen->diffInMinutes(now());

        // Infer engine running from RPM when available, otherwise from total_hours change.
        $engineRunning = null;
        if ($metric->engine_rpm !== null) {
            $engineRunning = $metric->engine_rpm > 100;
        }

        $payloadKg = $metric->load_weight !== null ? (float) $metric->load_weight : null;

        $operationalStatus = $this->deriveStatus(
            engineRunning: (bool) $engineRunning,
            isOffline: $isOffline,
            speedKmh: $metric->speed,
            payloadKg: $payloadKg,
        );

        [$label, $color] = $this->labelAndColor($operationalStatus);

        $operatingHours = $metric->total_hours !== null ? (float) $metric->total_hours : null;
        $idleHours = $metric->idle_hours !== null ? (float) $metric->idle_hours : null;
        $workingHours = ($operatingHours !== null && $idleHours !== null)
            ? max(0.0, round($operatingHours - $idleHours, 2))
            : null;

        return [
            'status' => $operationalStatus,
            'status_label' => $label,
            'status_color' => $color,
            'engine_running' => $engineRunning,
            'fuel_remaining_percent' => $metric->fuel_level !== null ? (float) $metric->fuel_level : null,
            'operating_hours' => $operatingHours,
            'idle_hours' => $idleHours,
            'working_hours' => $workingHours,
            'load_count' => null,
            'odometer' => null,
            'def_percent' => null,
            'payload' => $payloadKg,
            'latitude' => $metric->latitude !== null ? (float) $metric->latitude : null,
            'longitude' => $metric->longitude !== null ? (float) $metric->longitude : null,
            'speed_kmh' => $metric->speed !== null ? (float) $metric->speed : null,
            'heading_degrees' => $metric->heading !== null ? (float) $metric->heading : null,
            'engine_rpm' => $metric->engine_rpm !== null ? (float) $metric->engine_rpm : null,
            'coolant_temperature' => $metric->coolant_temperature !== null ? (float) $metric->coolant_temperature : null,
            'engine_temperature' => $metric->engine_temperature !== null ? (float) $metric->engine_temperature : null,
            'battery_voltage' => $metric->battery_voltage !== null ? (float) $metric->battery_voltage : null,
            'last_seen_at' => $lastSeen->toIso8601String(),
            'last_seen_human' => $lastSeen->diffForHumans(),
            'data_age_minutes' => $dataAgeMinutes,
            'is_stale' => $isStale,
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
        $isStale = ! $isOffline && $lastSeen && $lastSeen->lt(now()->subMinutes(self::STALE_MINUTES));
        $dataAgeMinutes = $lastSeen ? (int) $lastSeen->diffInMinutes(now()) : null;

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
            'working_hours' => null,
            'load_count' => null,
            'odometer' => $machine->odometer !== null ? (float) $machine->odometer : null,
            'def_percent' => null,
            'payload' => null,
            'latitude' => $machine->last_location_latitude !== null ? (float) $machine->last_location_latitude : null,
            'longitude' => $machine->last_location_longitude !== null ? (float) $machine->last_location_longitude : null,
            'speed_kmh' => null,
            'heading_degrees' => null,
            'engine_rpm' => null,
            'coolant_temperature' => null,
            'engine_temperature' => null,
            'battery_voltage' => null,
            'last_seen_at' => $lastSeen?->toIso8601String(),
            'last_seen_human' => $lastSeen?->diffForHumans(),
            'data_age_minutes' => $dataAgeMinutes,
            'is_stale' => $isStale,
            'equipment_key' => null,
            'telemetry_source' => 'machine',
        ];
    }

    // ── Status derivation ────────────────────────────────────────────

    /**
     * Determine the operational status of a machine from real-time telemetry.
     *
     * Decision tree:
     *   1. Offline    — no telemetry for > OFFLINE_MINUTES
     *   2. Parked     — engine not running (ignition off)
     *   3. Travelling — engine running + speed > SPEED_THRESHOLD
     *   4. Working    — engine running + speed ≤ threshold + payload > PAYLOAD_THRESHOLD (under load)
     *   5. Idling     — engine running + speed ≤ threshold + no significant payload
     *   6. Working    — engine running + no speed data (fallback: assume working)
     */
    private function deriveStatus(
        bool $engineRunning,
        bool $isOffline,
        ?float $speedKmh,
        ?float $payloadKg = null,
    ): string {
        if ($isOffline) {
            return 'offline';
        }

        if (! $engineRunning) {
            return 'parked';
        }

        // Engine is running — use speed to distinguish Travelling vs stationary.
        if ($speedKmh !== null && $speedKmh > self::SPEED_THRESHOLD) {
            return 'travelling';
        }

        // Machine is stationary with engine running.
        if ($speedKmh !== null) {
            if ($payloadKg !== null && $payloadKg > self::PAYLOAD_THRESHOLD_KG) {
                return 'working'; // carrying a load
            }

            return 'idling'; // engine on, stationary, no load
        }

        // No speed data — use payload as proxy; default to 'working' to avoid false idle alarms.
        if ($payloadKg !== null && $payloadKg > self::PAYLOAD_THRESHOLD_KG) {
            return 'working';
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
            'loading' => ['Loading',     'blue'],
            'dumping' => ['Dumping',     'purple'],
            'idling' => ['Idling',      'amber'],
            'parked' => ['Parked',      'slate'],
            'offline' => ['Offline',     'red'],
            'maintenance' => ['Maintenance', 'orange'],
            default => ['Unknown',     'gray'],
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
            'working_hours' => null,
            'load_count' => null,
            'odometer' => null,
            'def_percent' => null,
            'payload' => null,
            'latitude' => null,
            'longitude' => null,
            'speed_kmh' => null,
            'heading_degrees' => null,
            'engine_rpm' => null,
            'coolant_temperature' => null,
            'engine_temperature' => null,
            'battery_voltage' => null,
            'last_seen_at' => null,
            'last_seen_human' => null,
            'data_age_minutes' => null,
            'is_stale' => false,
            'equipment_key' => null,
            'telemetry_source' => 'none',
        ];
    }
}
