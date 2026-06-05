<?php

namespace App\Services\Integration;

use App\Contracts\BellIso15143ServiceInterface;
use App\Models\BellEquipment;
use App\Models\BellEquipmentCurrentStatus;
use App\Models\BellEquipmentDailyKpi;
use App\Models\BellEquipmentTelemetryHistory;
use App\Models\BellFleetSnapshot;
use App\Models\BellIntegrationAuditLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use SimpleXMLElement;

/**
 * Bell ISO15143-3 Fleet API Integration Service
 *
 * Fetches the Bell AEMP/ISO15143-3 fleet XML snapshot, converts it to JSON,
 * validates each equipment record, persists data across all fleet tables,
 * and calculates daily KPIs.
 *
 * Flow:
 *   fetch XML → parse → validate → save snapshot → upsert equipment master
 *   → merge current status → insert history → calculate KPIs → write audit log
 */
class BellIso15143Service implements BellIso15143ServiceInterface
{
    /** @var array{processed: int, inserted: int, updated: int} */
    private array $counters = ['processed' => 0, 'inserted' => 0, 'updated' => 0];

    public function __construct(
        private readonly string $apiUrl,
        private readonly string $apiUsername,
        private readonly string $apiPassword,
    ) {}

    /**
     * Run the full sync cycle.
     *
     * @return array{success: bool, processed: int, inserted: int, updated: int, error?: string}
     */
    public function sync(): array
    {
        $this->counters = ['processed' => 0, 'inserted' => 0, 'updated' => 0];
        $startedAt = now();

        try {
            $xml = $this->fetchXml();
            $fleet = $this->parseXml($xml);
            $equipment = $this->buildEquipmentCollection($fleet);

            DB::transaction(function () use ($fleet, $equipment): void {
                $this->saveFleetSnapshot($fleet, $equipment);
                $this->processEquipment($fleet, $equipment);
            });

            $this->writeAuditLog($startedAt, true);

            return [
                'success' => true,
                'processed' => $this->counters['processed'],
                'inserted' => $this->counters['inserted'],
                'updated' => $this->counters['updated'],
            ];
        } catch (\Throwable $e) {
            Log::error('BellIso15143Service sync failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->writeAuditLog($startedAt, false, $e->getMessage());

            return [
                'success' => false,
                'processed' => $this->counters['processed'],
                'inserted' => $this->counters['inserted'],
                'updated' => $this->counters['updated'],
                'error' => $e->getMessage(),
            ];
        }
    }

    // ------------------------------------------------------------------ //
    // HTTP fetch                                                           //
    // ------------------------------------------------------------------ //

    /**
     * Fetch the raw XML string from the Bell ISO15143-3 endpoint.
     *
     * @throws \RuntimeException when the HTTP request fails
     */
    private function fetchXml(): string
    {
        $response = Http::withBasicAuth($this->apiUsername, $this->apiPassword)
            ->timeout(30)
            ->retry(3, 2000)
            ->accept('application/xml')
            ->get($this->apiUrl);

        if (! $response->successful()) {
            throw new \RuntimeException(
                "Bell API returned HTTP {$response->status()}: {$response->body()}"
            );
        }

        return $response->body();
    }

    // ------------------------------------------------------------------ //
    // XML parsing                                                          //
    // ------------------------------------------------------------------ //

    /**
     * Parse the raw XML into a structured array.
     *
     * @return array{version: string, snapshot_time: string, equipment: list<array<string,mixed>>}
     *
     * @throws \RuntimeException on malformed XML
     */
    private function parseXml(string $xml): array
    {
        libxml_use_internal_errors(true);
        $root = simplexml_load_string($xml, SimpleXMLElement::class, LIBXML_NOCDATA);

        if ($root === false) {
            $errors = array_map(fn ($e) => $e->message, libxml_get_errors());
            libxml_clear_errors();
            throw new \RuntimeException('Failed to parse Bell XML: '.implode('; ', $errors));
        }

        $attrs = $root->attributes();
        $fleet = [
            'version' => (string) ($attrs['version'] ?? ''),
            'snapshot_time' => (string) ($attrs['snapshotTime'] ?? ''),
            'equipment' => [],
        ];

        foreach ($root->Equipment ?? [] as $equipmentNode) {
            $fleet['equipment'][] = $this->parseEquipmentNode($equipmentNode);
        }

        return $fleet;
    }

    /**
     * Extract fields from a single <Equipment> XML node.
     *
     * @return array<string,mixed>
     */
    private function parseEquipmentNode(SimpleXMLElement $node): array
    {
        $header = $node->EquipmentHeader ?? null;
        $location = $node->Location ?? null;
        $cumulative = $node->CumulativeOperatingHours ?? null;
        $idleHours = $node->IdleHours ?? null;
        $loadCount = $node->LoadCount ?? null;
        $payload = $node->CumulativePayload ?? null;
        $def = $node->DEFTankLevel ?? null;
        $odometer = $node->Odometer ?? null;
        $fuel = $node->FuelUsed ?? null;
        $fuelRemaining = $node->FuelLevel ?? null;
        $engine = $node->EngineStatus ?? null;

        return [
            'equipment_id' => (string) ($header?->EquipmentID ?? ''),
            'oem_name' => (string) ($header?->OEMName ?? ''),
            'model' => (string) ($header?->Model ?? ''),
            'serial_number' => (string) ($header?->SerialNumber ?? ''),
            'pin' => (string) ($header?->PIN ?? ''),
            'unit_install_date_time' => (string) ($header?->UnitInstallDateTime ?? ''),

            'latitude' => $location !== null ? (float) ($location->Latitude ?? 0) : null,
            'longitude' => $location !== null ? (float) ($location->Longitude ?? 0) : null,

            'idle_hours' => $idleHours !== null ? (float) $idleHours : null,
            'load_count' => $loadCount !== null ? (int) $loadCount : null,
            'operating_hours' => $cumulative !== null ? (float) $cumulative : null,

            'payload' => $payload !== null ? (float) ($payload->Payload ?? $payload) : null,
            'payload_units' => (string) ($payload?->attributes()['unit'] ?? 'kilogram'),

            'def_percent' => $def !== null ? (float) $def : null,

            'odometer' => $odometer !== null ? (float) ($odometer->Distance ?? $odometer) : null,
            'odometer_units' => (string) ($odometer?->attributes()['unit'] ?? 'kilometre'),

            'fuel_consumed' => $fuel !== null ? (float) ($fuel->FuelUsed ?? $fuel) : null,
            'fuel_units' => (string) ($fuel?->attributes()['unit'] ?? 'litre'),

            'fuel_remaining_percent' => $fuelRemaining !== null ? (float) $fuelRemaining : null,

            'engine_running' => $engine !== null
                ? strtolower((string) $engine) === 'running'
                : null,

            'engine_number' => (string) ($node->EngineNumber ?? ''),

            'telemetry_date' => (string) ($node->TelematicDataDate ?? ''),
        ];
    }

    // ------------------------------------------------------------------ //
    // Validation                                                           //
    // ------------------------------------------------------------------ //

    /**
     * Validate and filter equipment records from the parsed fleet array.
     *
     * @param  array{equipment: list<array<string,mixed>>}  $fleet
     * @return list<array<string,mixed>>
     */
    private function buildEquipmentCollection(array $fleet): array
    {
        $valid = [];

        foreach ($fleet['equipment'] as $item) {
            if (! $this->isValidEquipmentRecord($item)) {
                Log::warning('BellIso15143Service: skipping invalid equipment record', [
                    'equipment_id' => $item['equipment_id'] ?? null,
                    'serial_number' => $item['serial_number'] ?? null,
                ]);

                continue;
            }

            $valid[] = $item;
        }

        return $valid;
    }

    /**
     * Apply data quality rules from the specification.
     *
     * @param  array<string,mixed>  $item
     */
    private function isValidEquipmentRecord(array $item): bool
    {
        if (empty($item['equipment_id'])) {
            return false;
        }

        if (empty($item['serial_number'])) {
            return false;
        }

        if (isset($item['latitude']) && ($item['latitude'] < -90 || $item['latitude'] > 90)) {
            return false;
        }

        if (isset($item['longitude']) && ($item['longitude'] < -180 || $item['longitude'] > 180)) {
            return false;
        }

        if (isset($item['fuel_remaining_percent']) && ($item['fuel_remaining_percent'] < 0 || $item['fuel_remaining_percent'] > 100)) {
            return false;
        }

        if (isset($item['engine_running']) && ! is_bool($item['engine_running'])) {
            return false;
        }

        return true;
    }

    // ------------------------------------------------------------------ //
    // Persistence                                                          //
    // ------------------------------------------------------------------ //

    /**
     * Save a fleet-level snapshot record with the raw JSON payload.
     *
     * @param  array{version: string, snapshot_time: string}  $fleet
     * @param  list<array<string,mixed>>  $equipment
     */
    private function saveFleetSnapshot(array $fleet, array $equipment): void
    {
        BellFleetSnapshot::create([
            'snapshot_time' => $this->parseTimestamp($fleet['snapshot_time']),
            'fleet_version' => $fleet['version'],
            'equipment_count' => count($equipment),
            'raw_json' => json_encode([
                'SnapshotTime' => $fleet['snapshot_time'],
                'Equipment' => $equipment,
            ], JSON_UNESCAPED_SLASHES),
            'created_date' => now(),
        ]);
    }

    /**
     * Upsert equipment master, merge current status, and insert history for each record.
     *
     * @param  array{snapshot_time: string}  $fleet
     * @param  list<array<string,mixed>>  $equipment
     */
    private function processEquipment(array $fleet, array $equipment): void
    {
        $snapshotTime = $this->parseTimestamp($fleet['snapshot_time']);

        foreach ($equipment as $item) {
            $equipmentModel = $this->upsertEquipmentMaster($item);
            $this->mergeCurrentStatus($equipmentModel, $item, $snapshotTime);
            $this->insertTelemetryHistory($equipmentModel, $item, $snapshotTime);
            $this->calculateAndSaveDailyKpis($equipmentModel, $item, $snapshotTime);
            $this->counters['processed']++;
        }
    }

    /**
     * Insert or update the equipment master record.
     */
    /** @param array<string, mixed> $item */
    private function upsertEquipmentMaster(array $item): BellEquipment
    {
        $existing = BellEquipment::where('equipment_id', $item['equipment_id'])->first();

        if ($existing !== null) {
            $existing->update([
                'oem_name' => $item['oem_name'] ?: $existing->oem_name,
                'model' => $item['model'] ?: $existing->model,
                'serial_number' => $item['serial_number'] ?: $existing->serial_number,
                'pin' => $item['pin'] ?: $existing->pin,
                'unit_install_date_time' => $item['unit_install_date_time']
                    ? $this->parseTimestamp($item['unit_install_date_time'])
                    : $existing->unit_install_date_time,
            ]);

            $this->counters['updated']++;

            return $existing->fresh();
        }

        $this->counters['inserted']++;

        return BellEquipment::create([
            'equipment_id' => $item['equipment_id'],
            'oem_name' => $item['oem_name'],
            'model' => $item['model'],
            'serial_number' => $item['serial_number'],
            'pin' => $item['pin'],
            'unit_install_date_time' => $item['unit_install_date_time']
                ? $this->parseTimestamp($item['unit_install_date_time'])
                : null,
        ]);
    }

    /**
     * Replace the current-status row for this machine (merge / upsert pattern).
     */
    /**
     * @param  array<string, mixed>  $item
     */
    private function mergeCurrentStatus(
        BellEquipment $equipmentModel,
        array $item,
        ?Carbon $snapshotTime
    ): void {
        BellEquipmentCurrentStatus::where('equipment_key', $equipmentModel->equipment_key)->delete();

        BellEquipmentCurrentStatus::create(
            $this->buildTelemetryPayload($equipmentModel->equipment_key, $item, $snapshotTime)
            + ['updated_date' => now()]
        );
    }

    /**
     * Append a new history record – never update existing history.
     */
    /**
     * @param  array<string, mixed>  $item
     */
    private function insertTelemetryHistory(
        BellEquipment $equipmentModel,
        array $item,
        ?Carbon $snapshotTime
    ): void {
        $payload = $this->buildTelemetryPayload($equipmentModel->equipment_key, $item, $snapshotTime);
        unset($payload['last_telemetry_date']);

        BellEquipmentTelemetryHistory::create(
            $payload
            + [
                'telemetry_date' => $this->parseTimestamp($item['telemetry_date']),
                'created_date' => now(),
            ]
        );
    }

    /**
     * Calculate derived daily KPIs by comparing today's telemetry with the previous snapshot.
     */
    /**
     * @param  array<string, mixed>  $item
     */
    private function calculateAndSaveDailyKpis(
        BellEquipment $equipmentModel,
        array $item,
        ?Carbon $snapshotTime
    ): void {
        $kpiDate = $snapshotTime?->toDateString() ?? now()->toDateString();

        $previous = BellEquipmentTelemetryHistory::where('equipment_key', $equipmentModel->equipment_key)
            ->whereDate('telemetry_date', '<', $kpiDate)
            ->orderByDesc('telemetry_date')
            ->first();

        $loadsMoved = max(0, ($item['load_count'] ?? 0) - ($previous?->load_count ?? 0));
        $payloadMoved = max(0.0, (float) ($item['payload'] ?? 0) - (float) ($previous?->payload ?? 0));
        $fuelUsed = max(0.0, (float) ($item['fuel_consumed'] ?? 0) - (float) ($previous?->fuel_consumed ?? 0));
        $distanceTravelled = max(0.0, (float) ($item['odometer'] ?? 0) - (float) ($previous?->odometer ?? 0));

        $operatingHours = (float) ($item['operating_hours'] ?? 0);
        $idleHours = (float) ($item['idle_hours'] ?? 0);
        $totalHours = $operatingHours + $idleHours;
        $utilizationPercent = $totalHours > 0
            ? round(($operatingHours / $totalHours) * 100, 2)
            : 0.0;

        BellEquipmentDailyKpi::where('equipment_key', $equipmentModel->equipment_key)
            ->whereDate('kpi_date', $kpiDate)
            ->first()
            ?->update([
                'loads_moved' => $loadsMoved,
                'payload_moved' => $payloadMoved,
                'operating_hours' => $operatingHours,
                'idle_hours' => $idleHours,
                'distance_travelled' => $distanceTravelled,
                'fuel_used' => $fuelUsed,
                'utilization_percent' => $utilizationPercent,
            ])
            ?? BellEquipmentDailyKpi::create([
                'equipment_key' => $equipmentModel->equipment_key,
                'kpi_date' => $kpiDate,
                'loads_moved' => $loadsMoved,
                'payload_moved' => $payloadMoved,
                'operating_hours' => $operatingHours,
                'idle_hours' => $idleHours,
                'distance_travelled' => $distanceTravelled,
                'fuel_used' => $fuelUsed,
                'utilization_percent' => $utilizationPercent,
                'created_date' => now(),
            ]);
    }

    // ------------------------------------------------------------------ //
    // Helpers                                                              //
    // ------------------------------------------------------------------ //

    /**
     * Build the shared telemetry column array used by both current-status and history tables.
     *
     * @param  array<string, mixed>  $item
     * @return array<string,mixed>
     */
    private function buildTelemetryPayload(int $equipmentKey, array $item, ?Carbon $snapshotTime): array
    {
        return [
            'equipment_key' => $equipmentKey,
            'snapshot_time' => $snapshotTime,
            'latitude' => $item['latitude'],
            'longitude' => $item['longitude'],
            'idle_hours' => $item['idle_hours'],
            'load_count' => $item['load_count'],
            'operating_hours' => $item['operating_hours'],
            'payload' => $item['payload'],
            'payload_units' => $item['payload_units'],
            'def_percent' => $item['def_percent'],
            'odometer' => $item['odometer'],
            'odometer_units' => $item['odometer_units'],
            'fuel_consumed' => $item['fuel_consumed'],
            'fuel_units' => $item['fuel_units'],
            'fuel_remaining_percent' => $item['fuel_remaining_percent'],
            'engine_running' => $item['engine_running'],
            'engine_number' => $item['engine_number'],
            'last_telemetry_date' => $this->parseTimestamp($item['telemetry_date']),
        ];
    }

    /**
     * Parse an ISO 8601 timestamp string into a Carbon instance, returning null for empty values.
     */
    private function parseTimestamp(string $value): ?Carbon
    {
        if ($value === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Write an audit log entry for the current execution.
     */
    private function writeAuditLog(Carbon $startedAt, bool $success, ?string $errorMessage = null): void
    {
        BellIntegrationAuditLog::create([
            'execution_date' => $startedAt,
            'success' => $success,
            'records_processed' => $this->counters['processed'],
            'records_inserted' => $this->counters['inserted'],
            'records_updated' => $this->counters['updated'],
            'error_message' => $errorMessage,
        ]);
    }
}
