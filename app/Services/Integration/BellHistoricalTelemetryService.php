<?php

namespace App\Services\Integration;

use App\Events\BellEngineWarningDetected;
use App\Events\BellFuelLowDetected;
use App\Events\BellLocationUpdated;
use App\Events\BellTelemetryReceived;
use App\Models\BellDefLevel;
use App\Models\BellDistanceTravelled;
use App\Models\BellEquipment;
use App\Models\BellEquipmentCautionCode;
use App\Models\BellEquipmentFuelUsageHistory;
use App\Models\BellEquipmentHealthHistory;
use App\Models\BellEquipmentIdleHoursHistory;
use App\Models\BellEquipmentLoadCountHistory;
use App\Models\BellEquipmentLocationHistory;
use App\Models\BellEquipmentOperatingHoursHistory;
use App\Models\BellFuelLevel;
use App\Models\BellPayloadTotal;
use App\Models\BellRegenerationHour;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use SimpleXMLElement;

/**
 * Bell Historical Telemetry Service
 *
 * Runs every hour to fetch per-machine historical data from the Bell Equipment
 * REST API at https://b-fleet03.bellequipment.com:8080.
 *
 * Authentication: OAuth2 Password Credentials grant via Bell SSO
 *   POST https://sso.bellequipment.com/connect/token
 *   client_id/client_secret sent as Basic Auth header.
 *   Falls back to Basic Auth when SSO is not configured.
 *
 * Endpoint pattern:
 *   GET /Fleet/Equipment/{OEM ISO Identifier}/{Signal}/{startDateUTC}/{endDateUTC}
 *
 * Signals consumed:
 *   Locations                         → bell_equipment_location_history
 *   CumulativeOperatingHours          → bell_equipment_operating_hours_history
 *   CumulativeFuelUsed                → bell_equipment_fuel_usage_history
 *   FuelUsedInThePreceding24Hours     → bell_equipment_fuel_usage_history
 *   CumulativeIdleHours               → bell_equipment_idle_hours_history
 *   FuelRemainingRatio                → bell_equipment_fuel_usage_history
 *   CumulativeLoadCount               → bell_equipment_load_count_history
 *   CumulativePayloadTotals           → bell_equipment_load_count_history
 *   CautionCodes                      → bell_equipment_caution_codes
 *   DEFRemaining                      → bell_equipment_health_history
 *   EngineCondition                   → bell_equipment_health_history
 *   CumulativeActiveRegenerationHours → bell_equipment_health_history
 *   Distance                          → logged only (no dedicated table)
 */
class BellHistoricalTelemetryService
{
    /** @var array{fetched: int, inserted: int, skipped: int} */
    private array $counters = ['fetched' => 0, 'inserted' => 0, 'skipped' => 0];

    /** Cached SSO bearer token for the current sync cycle. */
    private ?string $bearerToken = null;

    public function __construct(
        private readonly string $baseUrl,
        private readonly string $apiUsername,
        private readonly string $apiPassword,
        private readonly string $ssoTokenUrl = '',
        private readonly string $clientId = '',
        private readonly string $clientSecret = '',
        private readonly string $scope = 'ISO_Exports',
    ) {}

    // ------------------------------------------------------------------ //
    // Public API                                                           //
    // ------------------------------------------------------------------ //

    /**
     * Sync a single named signal for all known Bell machines.
     *
     * Allows per-signal jobs to call only the endpoint they need rather than
     * running all 13 signals. The $hours parameter sets the lookback window
     * (e.g. 0.25 = 15-minute window for a 5-minute polling job).
     *
     * @return array{fetched: int, inserted: int, skipped: int}
     */
    public function syncSignal(string $signal, float $hours = 1.0): array
    {
        $this->counters = ['fetched' => 0, 'inserted' => 0, 'skipped' => 0];
        $this->bearerToken = null;

        $from = now()->subSeconds((int) ($hours * 3600))->utc()->format('Y-m-d\TH:i:s\Z');
        $to = now()->utc()->format('Y-m-d\TH:i:s\Z');

        BellEquipment::orderBy('equipment_key')->each(function (BellEquipment $machine) use ($signal, $from, $to): void {
            $key = $machine->equipment_key;
            $id = urlencode($machine->serial_number ?: $machine->equipment_id);

            try {
                match ($signal) {
                    'Locations' => $this->syncLocations($key, $id, $from, $to, $machine),
                    'CumulativeOperatingHours' => $this->syncCumulativeOperatingHours($key, $id, $from, $to),
                    'CumulativeFuelUsed' => $this->syncCumulativeFuelUsed($key, $id, $from, $to),
                    'FuelUsedInThePreceding24Hours' => $this->syncFuelUsed24h($key, $id, $from, $to),
                    'CumulativeIdleHours' => $this->syncCumulativeIdleHours($key, $id, $from, $to),
                    'FuelRemainingRatio' => $this->syncFuelRemainingRatio($key, $id, $from, $to, $machine),
                    'CumulativeLoadCount' => $this->syncCumulativeLoadCount($key, $id, $from, $to),
                    'CumulativePayloadTotals' => $this->syncCumulativePayloadTotals($key, $id, $from, $to),
                    'CautionCodes' => $this->syncCautionCodes($key, $id, $from, $to, $machine->equipment_id),
                    'DEFRemaining' => $this->syncDefRemaining($key, $id, $from, $to),
                    'EngineCondition' => $this->syncEngineCondition($key, $id, $from, $to, $machine),
                    'CumulativeActiveRegenerationHours' => $this->syncActiveRegenerationHours($key, $id, $from, $to),
                    'Distance' => $this->syncDistance($key, $id, $from, $to),
                    default => Log::warning('BellHistoricalTelemetryService: unknown signal', ['signal' => $signal]),
                };
            } catch (\Throwable $e) {
                Log::warning('BellHistoricalTelemetryService: signal sync failed', [
                    'signal' => $signal,
                    'equipment_id' => $machine->equipment_id,
                    'error' => $e->getMessage(),
                ]);
            }
        });

        return $this->counters;
    }

    /**
     * Fetch and store the last $hours hours of historical data for all known Bell equipment.
     *
     * @return array{fetched: int, inserted: int, skipped: int}
     */
    /**
     * Sync historical telemetry for all machines within an explicit UTC date range.
     *
     * @param  string  $from  ISO 8601 UTC, e.g. "2026-05-01T00:00:00Z"
     * @param  string  $to  ISO 8601 UTC, e.g. "2026-07-01T00:00:00Z"
     * @return array{fetched: int, inserted: int, skipped: int}
     */
    public function syncRange(string $from, string $to): array
    {
        $this->counters = ['fetched' => 0, 'inserted' => 0, 'skipped' => 0];
        $this->bearerToken = null;

        $equipment = BellEquipment::all();

        if ($equipment->isEmpty()) {
            Log::info('BellHistoricalTelemetryService: no equipment in database – skipping.');

            return $this->counters;
        }

        foreach ($equipment as $machine) {
            try {
                $this->syncMachine($machine, $from, $to);
            } catch (\Throwable $e) {
                Log::warning('BellHistoricalTelemetryService: machine sync failed', [
                    'equipment_key' => $machine->equipment_key,
                    'equipment_id' => $machine->equipment_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $this->counters;
    }

    /** @return array{fetched: int, inserted: int, skipped: int} */
    public function syncHistoricalData(int $hours = 1): array
    {
        $this->counters = ['fetched' => 0, 'inserted' => 0, 'skipped' => 0];
        $this->bearerToken = null;

        $from = now()->subHours($hours)->utc()->format('Y-m-d\TH:i:s\Z');
        $to = now()->utc()->format('Y-m-d\TH:i:s\Z');

        $equipment = BellEquipment::all();

        if ($equipment->isEmpty()) {
            Log::info('BellHistoricalTelemetryService: no equipment in database – skipping.');

            return $this->counters;
        }

        foreach ($equipment as $machine) {
            try {
                $this->syncMachine($machine, $from, $to);
            } catch (\Throwable $e) {
                Log::warning('BellHistoricalTelemetryService: machine sync failed', [
                    'equipment_key' => $machine->equipment_key,
                    'equipment_id' => $machine->equipment_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $this->counters;
    }

    // ------------------------------------------------------------------ //
    // Per-machine sync                                                     //
    // ------------------------------------------------------------------ //

    private function syncMachine(BellEquipment $machine, string $from, string $to): void
    {
        $key = $machine->equipment_key;
        $id = urlencode($machine->serial_number ?: $machine->equipment_id);
        $insertedBefore = $this->counters['inserted'];

        $this->syncLocations($key, $id, $from, $to, $machine);
        $this->syncCumulativeOperatingHours($key, $id, $from, $to);
        $this->syncCumulativeFuelUsed($key, $id, $from, $to);
        $this->syncFuelUsed24h($key, $id, $from, $to);
        $this->syncCumulativeIdleHours($key, $id, $from, $to);
        $this->syncFuelRemainingRatio($key, $id, $from, $to, $machine);
        $this->syncCumulativeLoadCount($key, $id, $from, $to);
        $this->syncCumulativePayloadTotals($key, $id, $from, $to);
        $this->syncCautionCodes($key, $id, $from, $to, $machine->equipment_id);
        $this->syncDefRemaining($key, $id, $from, $to);
        $this->syncEngineCondition($key, $id, $from, $to, $machine);
        $this->syncActiveRegenerationHours($key, $id, $from, $to);
        $this->syncDistance($key, $id, $from, $to);

        if ($this->counters['inserted'] > $insertedBefore) {
            BellTelemetryReceived::dispatch($machine, 'full_cycle', [
                'new_records' => $this->counters['inserted'] - $insertedBefore,
            ]);
        }
    }

    // ------------------------------------------------------------------ //
    // Signal syncs                                                         //
    // ------------------------------------------------------------------ //

    private function syncLocations(int $key, string $id, string $from, string $to, ?BellEquipment $equipment = null): void
    {
        $entries = $this->fetchEntries("Fleet/Equipment/{$id}/Locations/{$from}/{$to}");

        $last = BellEquipmentLocationHistory::where('equipment_key', $key)
            ->orderByDesc('recorded_at')->first();

        foreach ($entries as $entry) {
            $lat = $this->floatField($entry, ['Latitude', 'lat', 'latitude']);
            $lng = $this->floatField($entry, ['Longitude', 'long', 'longitude']);
            $recordedAt = $this->dateField($entry);

            if ($recordedAt === null) {
                continue;
            }

            $this->counters['fetched']++;

            if (
                $last !== null
                && $lat !== null && $lng !== null
                && round((float) $last->latitude, 6) === round($lat, 6)
                && round((float) $last->longitude, 6) === round($lng, 6)
            ) {
                $this->counters['skipped']++;

                continue;
            }

            $heading = $this->floatField($entry, ['Heading', 'heading']);
            $speed = $this->floatField($entry, ['Speed', 'speedKmh', 'speed']);

            BellEquipmentLocationHistory::create([
                'equipment_key' => $key,
                'latitude' => $lat,
                'longitude' => $lng,
                'heading_degrees' => $heading,
                'speed_kmh' => $speed,
                'source' => 'historical_api',
                'recorded_at' => $recordedAt,
                'created_at' => now(),
            ]);

            if ($equipment !== null && $lat !== null && $lng !== null) {
                BellLocationUpdated::dispatch($equipment, $lat, $lng, $heading, $speed, $recordedAt);
            }

            $last = null;
            $this->counters['inserted']++;
        }
    }

    private function syncCumulativeOperatingHours(int $key, string $id, string $from, string $to): void
    {
        $entries = $this->fetchEntries("Fleet/Equipment/{$id}/CumulativeOperatingHours/{$from}/{$to}");

        $last = BellEquipmentOperatingHoursHistory::where('equipment_key', $key)
            ->orderByDesc('recorded_at')->first();

        foreach ($entries as $entry) {
            $hours = $this->floatField($entry, ['hour', 'Hour', 'CumulativeOperatingHours', 'value', 'Value']);
            $recordedAt = $this->dateField($entry);

            if ($recordedAt === null || $hours === null) {
                continue;
            }

            $this->counters['fetched']++;

            if ($last !== null && (float) $last->operating_hours === $hours) {
                $this->counters['skipped']++;

                continue;
            }

            BellEquipmentOperatingHoursHistory::create([
                'equipment_key' => $key,
                'operating_hours' => $hours,
                'source' => 'historical_api',
                'recorded_at' => $recordedAt,
                'created_at' => now(),
            ]);

            $last = null;
            $this->counters['inserted']++;
        }
    }

    private function syncCumulativeFuelUsed(int $key, string $id, string $from, string $to): void
    {
        $entries = $this->fetchEntries("Fleet/Equipment/{$id}/CumulativeFuelUsed/{$from}/{$to}");

        $last = BellEquipmentFuelUsageHistory::where('equipment_key', $key)
            ->orderByDesc('recorded_at')->first();

        foreach ($entries as $entry) {
            $fuelUsed = $this->floatField($entry, ['FuelConsumed', 'fuelConsumed', 'CumulativeFuelUsed', 'value', 'Value']);
            $recordedAt = $this->dateField($entry);

            if ($recordedAt === null || $fuelUsed === null) {
                continue;
            }

            $this->counters['fetched']++;

            if ($last !== null && (float) $last->fuel_used_cumulative === $fuelUsed) {
                $this->counters['skipped']++;

                continue;
            }

            BellEquipmentFuelUsageHistory::create([
                'equipment_key' => $key,
                'fuel_used_cumulative' => $fuelUsed,
                'fuel_units' => $this->stringField($entry, ['unit', 'Unit']) ?? 'litre',
                'source' => 'historical_api',
                'recorded_at' => $recordedAt,
                'created_at' => now(),
            ]);

            $last = null;
            $this->counters['inserted']++;
        }
    }

    private function syncFuelUsed24h(int $key, string $id, string $from, string $to): void
    {
        $entries = $this->fetchEntries("Fleet/Equipment/{$id}/FuelUsedInThePreceding24Hours/{$from}/{$to}");

        foreach ($entries as $entry) {
            $fuelUsed24h = $this->floatField($entry, ['FuelUsed', 'fuelUsed', 'value', 'Value']);
            $recordedAt = $this->dateField($entry);

            if ($recordedAt === null || $fuelUsed24h === null) {
                continue;
            }

            $this->counters['fetched']++;

            // Store as a distinct row tagged with source 'historical_24h'
            BellEquipmentFuelUsageHistory::create([
                'equipment_key' => $key,
                'fuel_used_cumulative' => $fuelUsed24h,
                'fuel_units' => $this->stringField($entry, ['unit', 'Unit']) ?? 'litre',
                'source' => 'historical_24h',
                'recorded_at' => $recordedAt,
                'created_at' => now(),
            ]);

            $this->counters['inserted']++;
        }
    }

    private function syncCumulativeIdleHours(int $key, string $id, string $from, string $to): void
    {
        $entries = $this->fetchEntries("Fleet/Equipment/{$id}/CumulativeIdleHours/{$from}/{$to}");

        $last = BellEquipmentIdleHoursHistory::where('equipment_key', $key)
            ->orderByDesc('recorded_at')->first();

        foreach ($entries as $entry) {
            $idleHours = $this->floatField($entry, ['hour', 'Hour', 'CumulativeIdleHours', 'value', 'Value']);
            $recordedAt = $this->dateField($entry);

            if ($recordedAt === null || $idleHours === null) {
                continue;
            }

            $this->counters['fetched']++;

            if ($last !== null && (float) $last->idle_hours === $idleHours) {
                $this->counters['skipped']++;

                continue;
            }

            BellEquipmentIdleHoursHistory::create([
                'equipment_key' => $key,
                'idle_hours' => $idleHours,
                'source' => 'historical_api',
                'recorded_at' => $recordedAt,
                'created_at' => now(),
            ]);

            $last = null;
            $this->counters['inserted']++;
        }
    }

    private function syncFuelRemainingRatio(int $key, string $id, string $from, string $to, ?BellEquipment $equipment = null): void
    {
        $entries = $this->fetchEntries("Fleet/Equipment/{$id}/FuelRemainingRatio/{$from}/{$to}");

        $last = BellEquipmentFuelUsageHistory::where('equipment_key', $key)
            ->whereNotNull('fuel_remaining_percent')
            ->orderByDesc('recorded_at')->first();

        foreach ($entries as $entry) {
            // API returns 0-1 ratio; multiply by 100 for percentage
            $ratio = $this->floatField($entry, ['FuelRemainingRatio', 'ratio', 'Ratio', 'value', 'Value']);
            $recordedAt = $this->dateField($entry);

            if ($recordedAt === null || $ratio === null) {
                continue;
            }

            $percent = $ratio <= 1.0 ? round($ratio * 100, 2) : round($ratio, 2);

            $this->counters['fetched']++;

            if ($last !== null && (float) $last->fuel_remaining_percent === $percent) {
                $this->counters['skipped']++;

                continue;
            }

            BellEquipmentFuelUsageHistory::create([
                'equipment_key' => $key,
                'fuel_remaining_percent' => $percent,
                'source' => 'historical_api',
                'recorded_at' => $recordedAt,
                'created_at' => now(),
            ]);

            BellFuelLevel::create([
                'equipment_key' => $key,
                'fuel_remaining_percent' => $percent,
                'snapshot_time' => $recordedAt,
                'created_at' => now(),
            ]);

            if ($equipment !== null && $percent <= 20.0) {
                BellFuelLowDetected::dispatch($equipment, $percent, $recordedAt);
            }

            $last = null;
            $this->counters['inserted']++;
        }
    }

    private function syncCumulativeLoadCount(int $key, string $id, string $from, string $to): void
    {
        $entries = $this->fetchEntries("Fleet/Equipment/{$id}/CumulativeLoadCount/{$from}/{$to}");

        $last = BellEquipmentLoadCountHistory::where('equipment_key', $key)
            ->orderByDesc('recorded_at')->first();

        foreach ($entries as $entry) {
            $loadCount = $this->intField($entry, ['count', 'Count', 'CumulativeLoadCount', 'value', 'Value']);
            $recordedAt = $this->dateField($entry);

            if ($recordedAt === null || $loadCount === null) {
                continue;
            }

            $this->counters['fetched']++;

            if ($last !== null && (int) $last->load_count === $loadCount) {
                $this->counters['skipped']++;

                continue;
            }

            BellEquipmentLoadCountHistory::create([
                'equipment_key' => $key,
                'load_count' => $loadCount,
                'source' => 'historical_api',
                'recorded_at' => $recordedAt,
                'created_at' => now(),
            ]);

            $last = null;
            $this->counters['inserted']++;
        }
    }

    private function syncCumulativePayloadTotals(int $key, string $id, string $from, string $to): void
    {
        $entries = $this->fetchEntries("Fleet/Equipment/{$id}/CumulativePayloadTotals/{$from}/{$to}");

        $last = BellEquipmentLoadCountHistory::where('equipment_key', $key)
            ->whereNotNull('cumulative_payload')
            ->orderByDesc('recorded_at')->first();

        foreach ($entries as $entry) {
            $payload = $this->floatField($entry, ['Payload', 'payload', 'CumulativePayload', 'value', 'Value']);
            $recordedAt = $this->dateField($entry);

            if ($recordedAt === null || $payload === null) {
                continue;
            }

            $this->counters['fetched']++;

            if ($last !== null && (float) $last->cumulative_payload === $payload) {
                $this->counters['skipped']++;

                continue;
            }

            $payloadUnits = $this->stringField($entry, ['unit', 'Unit']) ?? 'kilogram';

            BellEquipmentLoadCountHistory::create([
                'equipment_key' => $key,
                'cumulative_payload' => $payload,
                'payload_units' => $payloadUnits,
                'source' => 'historical_api',
                'recorded_at' => $recordedAt,
                'created_at' => now(),
            ]);

            $payloadTonnes = strtolower($payloadUnits) === 'kilogram'
                ? round($payload / 1000, 3)
                : round($payload, 3);

            BellPayloadTotal::create([
                'equipment_key' => $key,
                'payload_tonnes' => $payloadTonnes,
                'snapshot_time' => $recordedAt,
                'created_at' => now(),
            ]);

            $last = null;
            $this->counters['inserted']++;
        }
    }

    private function syncCautionCodes(int $key, string $id, string $from, string $to, string $rawEquipmentId): void
    {
        $entries = $this->fetchEntries("Fleet/Equipment/{$id}/CautionCodes/{$from}/{$to}");

        foreach ($entries as $entry) {
            $code = $this->stringField($entry, ['FaultCode', 'faultCode', 'Code', 'code']);
            $recordedAt = $this->dateField($entry);

            if (empty($code) || $recordedAt === null) {
                continue;
            }

            $this->counters['fetched']++;

            BellEquipmentCautionCode::firstOrCreate(
                ['equipment_key' => $key, 'fault_code' => $code, 'is_active' => true],
                [
                    'fault_description' => $this->stringField($entry, ['Description', 'description', 'FaultDescription']) ?: null,
                    'severity' => $this->stringField($entry, ['Severity', 'severity']) ?: 'Info',
                    'source' => 'historical_api',
                    'occurred_at' => $recordedAt,
                ]
            );

            $this->counters['inserted']++;
        }
    }

    private function syncDefRemaining(int $key, string $id, string $from, string $to): void
    {
        $entries = $this->fetchEntries("Fleet/Equipment/{$id}/DEFRemaining/{$from}/{$to}");

        foreach ($entries as $entry) {
            $defPercent = $this->floatField($entry, ['DEFRemaining', 'defRemaining', 'percent', 'Percent', 'value', 'Value']);
            $recordedAt = $this->dateField($entry);

            if ($recordedAt === null || $defPercent === null) {
                continue;
            }

            // Normalise: if value is 0-1 ratio, convert to percentage
            if ($defPercent <= 1.0) {
                $defPercent = round($defPercent * 100, 2);
            }

            $this->counters['fetched']++;

            BellEquipmentHealthHistory::create([
                'equipment_key' => $key,
                'def_remaining_percent' => $defPercent,
                'caution_code_count' => 0,
                'recorded_at' => $recordedAt,
                'created_at' => now(),
            ]);

            BellDefLevel::create([
                'equipment_key' => $key,
                'def_remaining_percent' => $defPercent,
                'snapshot_time' => $recordedAt,
                'created_at' => now(),
            ]);

            $this->counters['inserted']++;
        }
    }

    private function syncEngineCondition(int $key, string $id, string $from, string $to, ?BellEquipment $equipment = null): void
    {
        $entries = $this->fetchEntries("Fleet/Equipment/{$id}/EngineCondition/{$from}/{$to}");

        foreach ($entries as $entry) {
            $condition = $this->stringField($entry, ['EngineCondition', 'engineCondition', 'Condition', 'condition', 'value', 'Value']);
            $recordedAt = $this->dateField($entry);

            if ($recordedAt === null || empty($condition)) {
                continue;
            }

            $this->counters['fetched']++;

            BellEquipmentHealthHistory::create([
                'equipment_key' => $key,
                'engine_condition' => $condition,
                'caution_code_count' => 0,
                'recorded_at' => $recordedAt,
                'created_at' => now(),
            ]);

            if ($equipment !== null && ! in_array(strtolower($condition), ['normal', 'ok', ''], true)) {
                BellEngineWarningDetected::dispatch($equipment, $condition, $recordedAt);
            }

            $this->counters['inserted']++;
        }
    }

    private function syncActiveRegenerationHours(int $key, string $id, string $from, string $to): void
    {
        $entries = $this->fetchEntries("Fleet/Equipment/{$id}/CumulativeActiveRegenerationHours/{$from}/{$to}");

        foreach ($entries as $entry) {
            $regenHours = $this->floatField($entry, ['hour', 'Hour', 'CumulativeActiveRegenerationHours', 'value', 'Value']);
            $recordedAt = $this->dateField($entry);

            if ($recordedAt === null || $regenHours === null) {
                continue;
            }

            $this->counters['fetched']++;

            BellEquipmentHealthHistory::create([
                'equipment_key' => $key,
                'active_regen_hours' => $regenHours,
                'caution_code_count' => 0,
                'recorded_at' => $recordedAt,
                'created_at' => now(),
            ]);

            BellRegenerationHour::create([
                'equipment_key' => $key,
                'regeneration_hours' => $regenHours,
                'snapshot_time' => $recordedAt,
                'created_at' => now(),
            ]);

            $this->counters['inserted']++;
        }
    }

    /**
     * Fetch Distance signal and persist to bell_distance_travelled.
     * Also captured via ISO15143-3 snapshot in bell_equipment_telemetry_history.
     */
    private function syncDistance(int $key, string $id, string $from, string $to): void
    {
        $entries = $this->fetchEntries("Fleet/Equipment/{$id}/Distance/{$from}/{$to}");

        $last = BellDistanceTravelled::where('equipment_key', $key)
            ->orderByDesc('snapshot_time')
            ->first();

        foreach ($entries as $entry) {
            $distance = $this->floatField($entry, ['Distance', 'distance', 'Odometer', 'odometer', 'value', 'Value']);
            $recordedAt = $this->dateField($entry);

            if ($recordedAt === null || $distance === null) {
                continue;
            }

            $this->counters['fetched']++;

            if ($last !== null && (float) $last->distance_km === $distance) {
                $this->counters['skipped']++;

                continue;
            }

            BellDistanceTravelled::create([
                'equipment_key' => $key,
                'distance_km' => $distance,
                'snapshot_time' => $recordedAt,
                'created_at' => now(),
            ]);

            $last = null;
            $this->counters['inserted']++;
        }
    }

    // ------------------------------------------------------------------ //
    // HTTP helpers                                                         //
    // ------------------------------------------------------------------ //

    /**
     * Fetch all entries from a Bell Equipment historical endpoint.
     * URL pattern: {baseUrl}/{path} (dates already embedded in path).
     * Returns a parsed array of entry elements.
     *
     * @return list<array<string, mixed>>
     */
    private function fetchEntries(string $path): array
    {
        $url = rtrim($this->baseUrl, '/').'/'.ltrim($path, '/');

        $request = Http::timeout(30)->retry(2, 1000)->accept('application/xml, application/json');

        if (! empty($this->ssoTokenUrl)) {
            $request = $request->withToken($this->resolveBearerToken());
        } else {
            $request = $request->withBasicAuth($this->apiUsername, $this->apiPassword);
        }

        $response = $request->get($url);

        if (! $response->successful()) {
            Log::warning('BellHistoricalTelemetryService: request failed', [
                'path' => $path,
                'status' => $response->status(),
            ]);

            return [];
        }

        return $this->parseResponse($response->body());
    }

    /**
     * Parse a Bell API response body – handles both XML (ISO 15143-3) and JSON.
     *
     * @return list<array<string, mixed>>
     */
    private function parseResponse(string $body): array
    {
        $body = trim($body);

        if ($body === '' || $body === '[]' || $body === '{}') {
            return [];
        }

        // Try JSON first
        if ($body[0] === '[' || $body[0] === '{') {
            $decoded = json_decode($body, true);

            if (is_array($decoded)) {
                // If root is a list, return directly; if object, wrap
                return isset($decoded[0]) ? array_values($decoded) : [$decoded];
            }
        }

        // Fall back to XML
        return $this->parseXmlResponse($body);
    }

    /**
     * Parse ISO 15143-3 XML response into a flat list of entry arrays.
     *
     * @return list<array<string, mixed>>
     */
    private function parseXmlResponse(string $xml): array
    {
        libxml_use_internal_errors(true);
        $root = simplexml_load_string($xml, SimpleXMLElement::class, LIBXML_NOCDATA);

        if ($root === false) {
            libxml_clear_errors();

            return [];
        }

        $entries = [];

        // ISO 15143-3 responses wrap records inside child elements.
        // Try children of root first; fall back to root itself as single entry.
        $children = $root->children();

        if ($children->count() > 0) {
            foreach ($children as $child) {
                $entries[] = $this->xmlNodeToArray($child);
            }
        } else {
            $entries[] = $this->xmlNodeToArray($root);
        }

        return $entries;
    }

    /**
     * Recursively convert a SimpleXMLElement node to an associative array.
     *
     * @return array<string, mixed>
     */
    private function xmlNodeToArray(SimpleXMLElement $node): array
    {
        $result = [];

        // Attributes
        foreach ($node->attributes() as $name => $value) {
            $result[(string) $name] = (string) $value;
        }

        // Child elements
        foreach ($node->children() as $name => $child) {
            $childCount = $child->children()->count();
            $result[(string) $name] = $childCount > 0
                ? $this->xmlNodeToArray($child)
                : (string) $child;
        }

        // If no children, the node value itself
        if (empty($result)) {
            return ['value' => (string) $node];
        }

        return $result;
    }

    // ------------------------------------------------------------------ //
    // SSO                                                                  //
    // ------------------------------------------------------------------ //

    /**
     * Obtain (and cache) a bearer token from the Bell SSO endpoint.
     * Uses OAuth2 Password Credentials grant with Basic Auth header.
     *
     * @throws \RuntimeException
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
    // Field extraction helpers                                             //
    // ------------------------------------------------------------------ //

    /**
     * Try a list of key names and return the first float value found.
     *
     * @param  array<string, mixed>  $entry
     * @param  list<string>  $keys
     */
    private function floatField(array $entry, array $keys): ?float
    {
        foreach ($keys as $key) {
            if (isset($entry[$key]) && $entry[$key] !== '') {
                return (float) $entry[$key];
            }
        }

        return null;
    }

    /**
     * Try a list of key names and return the first int value found.
     *
     * @param  array<string, mixed>  $entry
     * @param  list<string>  $keys
     */
    private function intField(array $entry, array $keys): ?int
    {
        foreach ($keys as $key) {
            if (isset($entry[$key]) && $entry[$key] !== '') {
                return (int) $entry[$key];
            }
        }

        return null;
    }

    /**
     * Try a list of key names and return the first non-empty string found.
     *
     * @param  array<string, mixed>  $entry
     * @param  list<string>  $keys
     */
    private function stringField(array $entry, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (isset($entry[$key]) && (string) $entry[$key] !== '') {
                return (string) $entry[$key];
            }
        }

        return null;
    }

    /**
     * Extract a datetime value from a response entry, trying common field names.
     *
     * @param  array<string, mixed>  $entry
     */
    private function dateField(array $entry): ?Carbon
    {
        $value = $this->stringField($entry, ['datetime', 'DateTime', 'Datetime', 'timestamp', 'Timestamp', 'date', 'Date']);

        if ($value === null) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
