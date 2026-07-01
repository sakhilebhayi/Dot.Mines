<?php

namespace App\Services\Integration;

use App\Contracts\BellIso15143ServiceInterface;
use App\Events\MachineLocationUpdated;
use App\Models\BellEquipment;
use App\Models\BellEquipmentCautionCode;
use App\Models\BellEquipmentCurrentStatus;
use App\Models\BellEquipmentDailyKpi;
use App\Models\BellEquipmentFuelUsageHistory;
use App\Models\BellEquipmentHealthHistory;
use App\Models\BellEquipmentIdleHoursHistory;
use App\Models\BellEquipmentLoadCountHistory;
use App\Models\BellEquipmentLocationHistory;
use App\Models\BellEquipmentOperatingHoursHistory;
use App\Models\BellEquipmentTelemetryHistory;
use App\Models\BellFleetSnapshot;
use App\Models\BellIntegrationAuditLog;
use App\Models\Machine;
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

    /** Cached SSO bearer token for the current sync cycle. */
    private ?string $bearerToken = null;

    public function __construct(
        private readonly string $apiUrl,
        private readonly string $apiUsername,
        private readonly string $apiPassword,
        private readonly string $ssoTokenUrl = '',
        private readonly string $clientId = '',
        private readonly string $clientSecret = '',
        private readonly string $scope = 'ISO_Exports',
    ) {}

    /**
     * Run the full sync cycle.
     *
     * @return array{success: bool, processed: int, inserted: int, updated: int, error?: string}
     */
    public function sync(): array
    {
        $this->counters = ['processed' => 0, 'inserted' => 0, 'updated' => 0];
        $startedAt = Carbon::now();

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
     * Uses a bearer token (SSO) when configured, otherwise falls back to Basic Auth.
     *
     * @throws \RuntimeException when the HTTP request fails
     */
    private function fetchXml(): string
    {
        $request = Http::timeout(30)
            ->retry(3, 2000)
            ->accept('application/xml');

        if (! empty($this->ssoTokenUrl)) {
            $request = $request->withToken($this->resolveBearerToken());
        } else {
            $request = $request->withBasicAuth($this->apiUsername, $this->apiPassword);
        }

        $response = $request->get($this->apiUrl);

        if (! $response->successful()) {
            throw new \RuntimeException(
                "Bell API returned HTTP {$response->status()}: {$response->body()}"
            );
        }

        return $response->body();
    }

    /**
     * Obtain (and cache for this sync cycle) a bearer token from the Bell SSO endpoint.
     * Uses the OAuth2 Password Credentials grant with Basic Authentication header.
     *
     * @throws \RuntimeException when the SSO token request fails
     */
    private function resolveBearerToken(): string
    {
        if ($this->bearerToken !== null) {
            return $this->bearerToken;
        }

        $response = Http::withBasicAuth($this->clientId, $this->clientSecret)
            ->timeout(15)
            ->asForm()
            ->post($this->ssoTokenUrl, [
                'grant_type' => 'password',
                'username' => $this->apiUsername,
                'password' => $this->apiPassword,
                'scope' => $this->scope,
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException(
                "Bell SSO token request failed ({$response->status()}): {$response->body()}"
            );
        }

        $token = $response->json('access_token');

        if (empty($token) || ! is_string($token)) {
            throw new \RuntimeException('Bell SSO response did not contain an access_token.');
        }

        $this->bearerToken = $token;

        return $this->bearerToken;
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

            // OEM intelligence fields (Phase 2–3)
            'engine_condition' => (string) ($node->EngineCondition ?? ''),
            'active_regen_hours' => $node->CumulativeActiveRegenerationHours !== null
                ? (float) $node->CumulativeActiveRegenerationHours
                : null,
            'caution_codes' => $this->parseCautionCodes($node),
        ];
    }

    /**
     * Parse the <CautionCodes> block into a flat list of code arrays.
     *
     * @return list<array{code: string, description: string, severity: string}>
     */
    private function parseCautionCodes(SimpleXMLElement $node): array
    {
        $codes = [];

        if (! isset($node->CautionCodes)) {
            return $codes;
        }

        foreach ($node->CautionCodes->CautionCode ?? [] as $cc) {
            $codes[] = [
                'code' => (string) ($cc->Code ?? $cc->FaultCode ?? ''),
                'description' => (string) ($cc->Description ?? $cc->FaultDescription ?? ''),
                'severity' => (string) ($cc->Severity ?? 'Info'),
            ];
        }

        return $codes;
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

            // OEM intelligence – Phase 1–3 specialized history tables
            $this->insertLocationHistoryIfChanged($equipmentModel, $item, $snapshotTime);
            $this->insertFuelUsageHistoryIfChanged($equipmentModel, $item, $snapshotTime);
            $this->insertOperatingHoursHistoryIfChanged($equipmentModel, $item, $snapshotTime);
            $this->insertIdleHoursHistoryIfChanged($equipmentModel, $item, $snapshotTime);
            $this->insertLoadCountHistoryIfChanged($equipmentModel, $item, $snapshotTime);
            $this->syncCautionCodes($equipmentModel, $item, $snapshotTime);
            $this->insertHealthHistory($equipmentModel, $item, $snapshotTime);

            // Push latest telemetry into the canonical machines table.
            $this->bridgeToMachine($equipmentModel, $item, $snapshotTime);

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
    // OEM Intelligence – Specialized history tables (Phase 1–3)           //
    // ------------------------------------------------------------------ //

    /** @param array<string, mixed> $item */
    private function insertLocationHistoryIfChanged(
        BellEquipment $equipmentModel,
        array $item,
        ?Carbon $snapshotTime
    ): void {
        $lat = $item['latitude'] ?? null;
        $lng = $item['longitude'] ?? null;

        if ($lat === null || $lng === null || $snapshotTime === null) {
            return;
        }

        $last = BellEquipmentLocationHistory::where('equipment_key', $equipmentModel->equipment_key)
            ->orderByDesc('recorded_at')
            ->first();

        if (
            $last !== null
            && round((float) $last->latitude, 6) === round((float) $lat, 6)
            && round((float) $last->longitude, 6) === round((float) $lng, 6)
        ) {
            return;
        }

        BellEquipmentLocationHistory::create([
            'equipment_key' => $equipmentModel->equipment_key,
            'latitude' => $lat,
            'longitude' => $lng,
            'source' => 'snapshot',
            'recorded_at' => $snapshotTime,
            'created_at' => now(),
        ]);
    }

    /** @param array<string, mixed> $item */
    private function insertFuelUsageHistoryIfChanged(
        BellEquipment $equipmentModel,
        array $item,
        ?Carbon $snapshotTime
    ): void {
        $fuelUsed = $item['fuel_consumed'] ?? null;

        if ($fuelUsed === null || $snapshotTime === null) {
            return;
        }

        $last = BellEquipmentFuelUsageHistory::where('equipment_key', $equipmentModel->equipment_key)
            ->orderByDesc('recorded_at')
            ->first();

        if ($last !== null && (float) $last->fuel_used_cumulative === (float) $fuelUsed) {
            return;
        }

        BellEquipmentFuelUsageHistory::create([
            'equipment_key' => $equipmentModel->equipment_key,
            'fuel_used_cumulative' => $fuelUsed,
            'fuel_remaining_percent' => $item['fuel_remaining_percent'] ?? null,
            'fuel_units' => $item['fuel_units'] ?? 'litre',
            'source' => 'snapshot',
            'recorded_at' => $snapshotTime,
            'created_at' => now(),
        ]);
    }

    /** @param array<string, mixed> $item */
    private function insertOperatingHoursHistoryIfChanged(
        BellEquipment $equipmentModel,
        array $item,
        ?Carbon $snapshotTime
    ): void {
        $hours = $item['operating_hours'] ?? null;

        if ($hours === null || $snapshotTime === null) {
            return;
        }

        $last = BellEquipmentOperatingHoursHistory::where('equipment_key', $equipmentModel->equipment_key)
            ->orderByDesc('recorded_at')
            ->first();

        if ($last !== null && (float) $last->operating_hours === (float) $hours) {
            return;
        }

        BellEquipmentOperatingHoursHistory::create([
            'equipment_key' => $equipmentModel->equipment_key,
            'operating_hours' => $hours,
            'source' => 'snapshot',
            'recorded_at' => $snapshotTime,
            'created_at' => now(),
        ]);
    }

    /** @param array<string, mixed> $item */
    private function insertIdleHoursHistoryIfChanged(
        BellEquipment $equipmentModel,
        array $item,
        ?Carbon $snapshotTime
    ): void {
        $idleHours = $item['idle_hours'] ?? null;

        if ($idleHours === null || $snapshotTime === null) {
            return;
        }

        $last = BellEquipmentIdleHoursHistory::where('equipment_key', $equipmentModel->equipment_key)
            ->orderByDesc('recorded_at')
            ->first();

        if ($last !== null && (float) $last->idle_hours === (float) $idleHours) {
            return;
        }

        BellEquipmentIdleHoursHistory::create([
            'equipment_key' => $equipmentModel->equipment_key,
            'idle_hours' => $idleHours,
            'source' => 'snapshot',
            'recorded_at' => $snapshotTime,
            'created_at' => now(),
        ]);
    }

    /** @param array<string, mixed> $item */
    private function insertLoadCountHistoryIfChanged(
        BellEquipment $equipmentModel,
        array $item,
        ?Carbon $snapshotTime
    ): void {
        $loadCount = $item['load_count'] ?? null;

        if ($loadCount === null || $snapshotTime === null) {
            return;
        }

        $last = BellEquipmentLoadCountHistory::where('equipment_key', $equipmentModel->equipment_key)
            ->orderByDesc('recorded_at')
            ->first();

        if ($last !== null && (int) $last->load_count === (int) $loadCount) {
            return;
        }

        BellEquipmentLoadCountHistory::create([
            'equipment_key' => $equipmentModel->equipment_key,
            'load_count' => $loadCount,
            'cumulative_payload' => $item['payload'] ?? null,
            'payload_units' => $item['payload_units'] ?? 'kilogram',
            'source' => 'snapshot',
            'recorded_at' => $snapshotTime,
            'created_at' => now(),
        ]);
    }

    /**
     * Upsert caution codes from the current snapshot.
     * New codes are recorded as active; codes absent from this snapshot are cleared.
     *
     * @param  array<string, mixed>  $item
     */
    private function syncCautionCodes(
        BellEquipment $equipmentModel,
        array $item,
        ?Carbon $snapshotTime
    ): void {
        /** @var list<array{code: string, description: string, severity: string}> $incoming */
        $incoming = $item['caution_codes'] ?? [];
        $occurredAt = $snapshotTime ?? now();
        $incomingCodes = array_column($incoming, 'code');

        // Mark previously-active codes that are no longer in this snapshot as cleared
        $clearQuery = BellEquipmentCautionCode::where('equipment_key', $equipmentModel->equipment_key)
            ->where('is_active', true);

        if (! empty($incomingCodes)) {
            $clearQuery->whereNotIn('fault_code', $incomingCodes);
        }

        $clearQuery->update(['is_active' => false, 'cleared_at' => $occurredAt]);

        // Upsert each incoming code
        foreach ($incoming as $cc) {
            if (empty($cc['code'])) {
                continue;
            }

            BellEquipmentCautionCode::firstOrCreate(
                [
                    'equipment_key' => $equipmentModel->equipment_key,
                    'fault_code' => $cc['code'],
                    'is_active' => true,
                ],
                [
                    'fault_description' => $cc['description'] ?: null,
                    'severity' => $cc['severity'] ?: 'Info',
                    'source' => 'snapshot',
                    'occurred_at' => $occurredAt,
                ]
            );
        }
    }

    /**
     * Record a health snapshot for every sync cycle.
     * Provides a full audit trail of machine health over time.
     *
     * @param  array<string, mixed>  $item
     */
    private function insertHealthHistory(
        BellEquipment $equipmentModel,
        array $item,
        ?Carbon $snapshotTime
    ): void {
        if ($snapshotTime === null) {
            return;
        }

        /** @var list<array{code: string, description: string, severity: string}> $cautionCodes */
        $cautionCodes = $item['caution_codes'] ?? [];
        $cautionCodeCount = count($cautionCodes);
        $engineCondition = ! empty($item['engine_condition']) ? $item['engine_condition'] : null;
        $defPercent = $item['def_percent'] ?? null;
        $activeRegenHours = $item['active_regen_hours'] ?? null;
        $operatingHours = $item['operating_hours'] ?? null;

        $healthScore = $this->computeHealthScore(
            $engineCondition,
            $defPercent !== null ? (float) $defPercent : null,
            $activeRegenHours !== null ? (float) $activeRegenHours : null,
            $operatingHours !== null ? (float) $operatingHours : null,
            $cautionCodeCount,
        );

        BellEquipmentHealthHistory::create([
            'equipment_key' => $equipmentModel->equipment_key,
            'engine_condition' => $engineCondition,
            'def_remaining_percent' => $defPercent,
            'active_regen_hours' => $activeRegenHours,
            'caution_code_count' => $cautionCodeCount,
            'health_score' => $healthScore,
            'recorded_at' => $snapshotTime,
            'created_at' => now(),
        ]);
    }

    /**
     * Derive a 0–100 machine health score from OEM telemetry signals.
     *
     * Deductions:
     *   Engine condition not OK             : -20
     *   DEF < 5 %                           : -25  |  < 10 %: -20  |  < 20 %: -10
     *   Active regen rate > 10 % of hours   : -20  |  >  5 %: -10
     *   Per active caution code             :  -5  (capped at -30)
     */
    private function computeHealthScore(
        ?string $engineCondition,
        ?float $defPercent,
        ?float $activeRegenHours,
        ?float $operatingHours,
        int $cautionCodeCount,
    ): float {
        $score = 100.0;

        if ($engineCondition !== null && strtolower($engineCondition) !== 'ok') {
            $score -= 20;
        }

        if ($defPercent !== null) {
            if ($defPercent < 5) {
                $score -= 25;
            } elseif ($defPercent < 10) {
                $score -= 20;
            } elseif ($defPercent < 20) {
                $score -= 10;
            }
        }

        if ($activeRegenHours !== null && $operatingHours !== null && $operatingHours > 0) {
            $regenRate = $activeRegenHours / $operatingHours;
            if ($regenRate > 0.10) {
                $score -= 20;
            } elseif ($regenRate > 0.05) {
                $score -= 10;
            }
        }

        $score -= min(30.0, $cautionCodeCount * 5.0);

        return max(0.0, round($score, 2));
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
     * Match a BellEquipment record to its corresponding Machine row and push
     * the latest telemetry (GPS, engine hours, status, odometer) into the
     * canonical machines table. Fires MachineLocationUpdated so the live map
     * receives the updated position in real-time.
     *
     * Matching strategy (in order):
     *   1. bell_equipment.machine_id already set → use directly
     *   2. machines.serial_number  = bell_equipment.serial_number
     *   3. machines.external_id    = bell_equipment.equipment_id
     *   4. No match → skip (machine not yet registered in this platform)
     *
     * @param  array<string, mixed>  $item
     */
    private function bridgeToMachine(BellEquipment $bellEquipment, array $item, ?Carbon $snapshotTime): void
    {
        // 1. Reuse confirmed link if already set.
        $machine = $bellEquipment->machine_id !== null
            ? Machine::find($bellEquipment->machine_id)
            : null;

        // 2. Match by serial number.
        if ($machine === null && ! empty($item['serial_number'])) {
            $machine = Machine::where('serial_number', $item['serial_number'])->first();
        }

        // 3. Match by external_id / equipment_id.
        if ($machine === null && ! empty($item['equipment_id'])) {
            $machine = Machine::where('external_id', $item['equipment_id'])->first();
        }

        if ($machine === null) {
            Log::debug('BellIso15143Service: no Machine match for equipment_id='.$item['equipment_id']);

            return;
        }

        // Persist the confirmed link so future syncs avoid the lookup queries.
        if ($bellEquipment->machine_id !== $machine->id) {
            $bellEquipment->update([
                'machine_id' => $machine->id,
                'machine_matched_at' => now(),
            ]);
        }

        // Determine status from engine_running flag.
        $status = match (true) {
            $item['engine_running'] === true => 'active',
            $item['engine_running'] === false => 'idle',
            default => $machine->status,
        };

        // Convert odometer to km (Bell default unit is 'kilometre').
        $oemOdometer = isset($item['odometer']) ? (float) $item['odometer'] : null;
        $oemUnits = strtolower($item['odometer_units'] ?? 'kilometre');
        $totalDistanceKm = match ($oemUnits) {
            'mile' => $oemOdometer !== null ? round($oemOdometer * 1.60934, 2) : null,
            'kilometre', 'kilometer', 'km' => $oemOdometer,
            default => $oemOdometer,
        };

        $operatingHours = isset($item['operating_hours']) ? (float) $item['operating_hours'] : null;
        $lat = isset($item['latitude']) ? (float) $item['latitude'] : null;
        $lng = isset($item['longitude']) ? (float) $item['longitude'] : null;

        $updates = [
            'external_id' => $item['equipment_id'],
            'status' => $status,
            'last_seen_at' => $snapshotTime ?? now(),
        ];

        if ($lat !== null && $lng !== null) {
            $updates['last_location_latitude'] = $lat;
            $updates['last_location_longitude'] = $lng;
            $updates['last_location_update'] = $snapshotTime ?? now();
        }

        if ($operatingHours !== null) {
            $updates['operating_hours'] = $operatingHours;
            $updates['hours_meter'] = $operatingHours;
        }

        if ($oemOdometer !== null) {
            $updates['odometer'] = $oemOdometer;
            $updates['total_distance_km'] = $totalDistanceKm;
        }

        $machine->update($updates);

        // Broadcast GPS update to the live map (only when coordinates are present).
        // Wrapped in try-catch so a broadcasting failure (e.g. Pusher/Reverb unreachable)
        // never rolls back the outer DB transaction.
        if ($lat !== null && $lng !== null) {
            try {
                MachineLocationUpdated::dispatch($machine->fresh(), [
                    'latitude' => $lat,
                    'longitude' => $lng,
                ]);
            } catch (\Throwable $e) {
                Log::warning('BellIso15143Service: failed to broadcast MachineLocationUpdated', [
                    'machine_id' => $machine->id,
                    'error' => $e->getMessage(),
                ]);
            }
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
