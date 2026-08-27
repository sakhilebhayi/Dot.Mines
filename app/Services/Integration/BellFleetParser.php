<?php

namespace App\Services\Integration;

use Illuminate\Support\Carbon;
use SimpleXMLElement;

/**
 * Pure XML -> array parsing for Bell's ISO 15143-3 responses (refactor
 * program R2: extracted from BellService so the subtlest live-API traps
 * -- section-scoped child-name collisions, fuel ratio-vs-percent
 * normalisation, coordinate validation -- are testable against XML
 * fixtures with no HTTP, cache, or error state involved). Every method
 * is a pure function of its input.
 */
final class BellFleetParser
{
    /**
     * @param  list<array{timestamp: string, value: string, attributes: array<string, string>}>  $readings
     * @return list<array{timestamp: string, value: float, units: ?string}>
     */
    public function toProductionReadings(array $readings): array
    {
        $parsed = [];

        foreach ($readings as $reading) {
            $value = $this->toFloatOrNull($reading['value'] ?? null);

            if ($value === null) {
                continue;
            }

            $parsed[] = [
                'timestamp' => $reading['timestamp'],
                'value' => $value,
                'units' => $reading['attributes']['PayloadUnits']
                    ?? $reading['attributes']['Units']
                    ?? null,
            ];
        }

        return $parsed;
    }

    /**
     * Parse a single <Equipment> node from the /Fleet snapshot into this
     * app's standard machine-sync shape (see IntegrationService::syncMachine()).
     *
     * @return array<string, mixed>
     */
    public function parseEquipmentNode(SimpleXMLElement $equipment): array
    {
        $header = $equipment->xpath(".//*[local-name()='EquipmentHeader']")[0] ?? $equipment;

        $externalId = $this->findValue($header, ['EquipmentID']);
        $latitude = $this->toFloatOrNull($this->sectionValue($equipment, 'Location', 'Latitude') ?? $this->findValue($equipment, ['Latitude']));
        $longitude = $this->toFloatOrNull($this->sectionValue($equipment, 'Location', 'Longitude') ?? $this->findValue($equipment, ['Longitude']));
        // Deliberately the LOCATION section's own datetime (falling back
        // to the flattened TelemetryDate): the live map's "position
        // reported X ago" is only honest if it is stamped with when the
        // position was reported, not when any other section last ticked.
        $telemetryDate = $this->sectionDatetime($equipment, 'Location')
            ?? $this->findValue($equipment, ['TelemetryDate'])
            ?? now()->toIso8601String();
        $engineRunning = $this->parseEngineRunning($equipment);

        return [
            'external_id' => $externalId,
            'name' => $externalId ?? 'Unknown Bell Machine',
            'model' => $this->findValue($header, ['Model']),
            'manufacturer' => 'Bell',
            'serial_number' => $this->findValue($header, ['SerialNumber']),
            'status' => $engineRunning === null
                ? 'unknown'
                : ($engineRunning ? 'active' : 'idle'), // Base::parseStatus maps running->active, idle->idle
            'last_location' => $this->isValidCoordinate($latitude, $longitude) ? [
                'latitude' => $latitude,
                'longitude' => $longitude,
                'timestamp' => $telemetryDate,
            ] : null,
            'specifications' => [
                'type' => 'haul_truck',
                'pin' => $this->findValue($header, ['PIN']),
            ],
            // IntegrationService::syncMachineMetrics() feeds this directly
            // into `new MachineMetric($metrics)` -- a single flat array of
            // MachineMetric's own fillable columns, not a list of readings.
            'metrics' => $this->buildCurrentMetric($equipment),
            'alerts' => [],
        ];
    }

    /**
     * The current-status shape shared by fetchMachines() (per equipment
     * node in the /Fleet snapshot) and fetchMachineMetrics() (looked up by
     * ID from that same snapshot) -- a single flat array of MachineMetric's
     * fillable columns. 'recorded_at' (not 'timestamp') is the fillable
     * column name.
     *
     * @return array<string, mixed>
     */
    public function buildCurrentMetric(SimpleXMLElement $equipment): array
    {
        $latitude = $this->toFloatOrNull($this->sectionValue($equipment, 'Location', 'Latitude') ?? $this->findValue($equipment, ['Latitude']));
        $longitude = $this->toFloatOrNull($this->sectionValue($equipment, 'Location', 'Longitude') ?? $this->findValue($equipment, ['Longitude']));
        // The NEWEST section datetime, not Location's: every ISO section
        // carries its own timestamp, and a stationary machine keeps
        // reporting cumulative counters (hours, payload) under a frozen
        // Location datetime. recorded_at drives the §19 dedupe in
        // IntegrationService::syncMachineMetrics(), so stamping it with
        // Location's time made the dedupe discard every row whose only
        // news was cumulative -- the Fleet page then froze on stale
        // hours/tonnage while the API had newer values.
        $telemetryDate = $this->newestSectionDatetime($equipment)
            ?? $this->findValue($equipment, ['TelemetryDate'])
            ?? now()->toIso8601String();
        $engineRunning = $this->parseEngineRunning($equipment);

        $fuelRemaining = $this->toFloatOrNull(
            $this->sectionValue($equipment, 'FuelRemaining', 'Percent')
            ?? $this->findValue($equipment, ['FuelRemainingPercent', 'FuelRemainingRatio'])
        );
        // ISO 15143-3's own element is a *ratio* (0-1); Bell's flattened
        // example uses a percent (0-100) under a differently named field.
        // Normalise defensively either way rather than assuming which one a
        // given response actually used.
        if ($fuelRemaining !== null && $fuelRemaining <= 1) {
            $fuelRemaining = $fuelRemaining * 100.0;
        }

        return [
            'recorded_at' => $telemetryDate,
            'latitude' => $this->isValidLatitude($latitude) ? $latitude : null,
            'longitude' => $this->isValidLongitude($longitude) ? $longitude : null,
            'fuel_level' => $this->isValidPercent($fuelRemaining) ? $fuelRemaining : null,
            // Live Bell omits Speed today; when a provider sends it, the
            // dispatch classifier must get the machine's own reading.
            // null (never 0) when absent: 0 claims "measured stationary",
            // and the classifier falls back to position-derived movement
            // only on null.
            'speed' => $this->toFloatOrNull($this->sectionValue($equipment, 'Location', 'Speed') ?? $this->findValue($equipment, ['Speed'])),
            // Live Bell nests these under sections whose CHILD names
            // collide (CumulativeIdleHours/Hour vs CumulativeOperatingHours/
            // Hour, DEFRemaining/Percent vs FuelRemaining/Percent), so every
            // read must be scoped to its section -- a document-order search
            // for the bare child name would silently return the wrong
            // section's value. Flattened-attribute fallbacks keep the
            // spec-derived shape parsing too.
            'operating_hours' => $this->toFloatOrNull($this->sectionValue($equipment, 'CumulativeOperatingHours', 'Hour') ?? $this->findValue($equipment, ['OperatingHours'])),
            'idle_hours' => $this->toFloatOrNull($this->sectionValue($equipment, 'CumulativeIdleHours', 'Hour') ?? $this->findValue($equipment, ['IdleHours'])),
            // ISO's "Payload" here is a cumulative lifetime total, not an
            // instantaneous load -- kept in raw_data instead of the
            // load_weight column, which the rest of this app treats as
            // "what's on the machine right now".
            'load_weight' => null,
            'raw_data' => [
                'load_count' => $this->toFloatOrNull($this->sectionValue($equipment, 'CumulativeLoadCount', 'Count') ?? $this->findValue($equipment, ['LoadCount'])),
                'cumulative_payload' => $this->toFloatOrNull($this->sectionValue($equipment, 'CumulativePayloadTotals', 'Payload') ?? $this->findValue($equipment, ['Payload'])),
                'payload_units' => $this->sectionValue($equipment, 'CumulativePayloadTotals', 'PayloadUnits') ?? $this->findValue($equipment, ['PayloadUnits']),
                'def_percent' => $this->toFloatOrNull($this->sectionValue($equipment, 'DEFRemaining', 'Percent') ?? $this->findValue($equipment, ['DEFPercent'])),
                'odometer' => $this->toFloatOrNull($this->sectionValue($equipment, 'Distance', 'Odometer') ?? $this->findValue($equipment, ['Odometer'])),
                'odometer_units' => $this->sectionValue($equipment, 'Distance', 'OdometerUnits') ?? $this->findValue($equipment, ['OdometerUnits']),
                'fuel_consumed_cumulative' => $this->toFloatOrNull($this->sectionValue($equipment, 'FuelUsed', 'FuelConsumed') ?? $this->findValue($equipment, ['FuelConsumed'])),
                'fuel_units' => $this->sectionValue($equipment, 'FuelUsed', 'FuelUnits') ?? $this->findValue($equipment, ['FuelUnits']),
                'engine_running' => $engineRunning,
            ],
        ];
    }

    public function parseEngineRunning(SimpleXMLElement $equipment): ?bool
    {
        $raw = $this->sectionValue($equipment, 'EngineStatus', 'Running')
            ?? $this->findValue($equipment, ['EngineRunning']);

        return $raw === null ? null : filter_var($raw, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * @param  list<SimpleXMLElement>  $equipmentNodes
     * @return array<string, string>
     */
    public function buildPinMap(array $equipmentNodes): array
    {
        $map = [];

        foreach ($equipmentNodes as $node) {
            $header = $node->xpath(".//*[local-name()='EquipmentHeader']")[0] ?? $node;
            $id = trim((string) $this->findValue($header, ['EquipmentID']));
            $pin = trim((string) ($this->findValue($header, ['PIN']) ?? $this->findValue($header, ['SerialNumber'])));

            if ($id !== '' && $pin !== '') {
                $map[$id] = $pin;
            }
        }

        return $map;
    }

    /**
     * Reads a value nested one level inside a named section element
     * (live Bell shape: <FuelRemaining datetime="..."><Percent>63</Percent>
     * </FuelRemaining>). Returns null when the section or field is absent
     * so callers can fall back to the flattened-attribute spec shape.
     */
    public function sectionValue(SimpleXMLElement $equipment, string $section, string $field): ?string
    {
        $matches = $equipment->xpath(".//*[local-name()='{$section}']/*[local-name()='{$field}']");
        $value = isset($matches[0]) ? (string) $matches[0] : '';

        return $value !== '' ? $value : null;
    }

    /**
     * The live snapshot carries no TelemetryDate element -- each section
     * stamps its own reading time in a datetime attribute instead.
     */
    public function sectionDatetime(SimpleXMLElement $equipment, string $section): ?string
    {
        $matches = $equipment->xpath(".//*[local-name()='{$section}']");
        $value = isset($matches[0]) ? (string) ($matches[0]['datetime'] ?? '') : '';

        return $value !== '' ? $value : null;
    }

    /**
     * The newest datetime any telemetry section on this equipment node
     * reports -- the machine's honest "last said anything" moment.
     * Sections update independently on live Bell hardware, so no single
     * section's timestamp can stand in for the node's freshness.
     */
    public function newestSectionDatetime(SimpleXMLElement $equipment): ?string
    {
        $nodes = $equipment->xpath(".//*[@*[local-name()='datetime']]");

        $newestRaw = null;
        $newestParsed = null;

        foreach (is_array($nodes) ? $nodes : [] as $node) {
            $value = trim((string) ($node['datetime'] ?? ''));

            if ($value === '') {
                continue;
            }

            try {
                $parsed = Carbon::parse($value);
            } catch (\Throwable) {
                continue; // an unparseable stamp cannot win "newest"
            }

            if ($newestParsed === null || $parsed->greaterThan($newestParsed)) {
                $newestParsed = $parsed;
                $newestRaw = $value; // keep the provider's own string
            }
        }

        return $newestRaw;
    }

    /**
     * @return list<SimpleXMLElement>
     */
    public function extractEquipmentNodes(SimpleXMLElement $fleet): array
    {
        $nodes = $fleet->xpath("//*[local-name()='Equipment']");

        return $nodes === false || $nodes === null ? [] : array_values($nodes);
    }

    /**
     * Looks for each candidate name first as an attribute on $node, then
     * anywhere in $node's subtree as either an element's own text or a
     * Value/Reading/Amount attribute on it. Searching by local-name() (not
     * a fixed path) means this keeps working regardless of exactly how
     * Bell nests these fields or which XML namespace it uses.
     *
     * @param  list<string>  $candidateNames
     */
    public function findValue(SimpleXMLElement $node, array $candidateNames): ?string
    {
        foreach ($candidateNames as $name) {
            if (isset($node[$name]) && (string) $node[$name] !== '') {
                return (string) $node[$name];
            }
        }

        foreach ($candidateNames as $name) {
            $matches = $node->xpath(".//*[local-name()='{$name}']");

            if ($matches === false || $matches === null || $matches === []) {
                continue;
            }

            $match = $matches[0];

            foreach (['Value', 'Reading', 'Amount'] as $attr) {
                if (isset($match[$attr]) && (string) $match[$attr] !== '') {
                    return (string) $match[$attr];
                }
            }

            if ((string) $match !== '') {
                return (string) $match;
            }
        }

        return null;
    }

    public function isValidLatitude(?float $value): bool
    {
        return $value !== null && $value >= -90 && $value <= 90;
    }

    public function isValidLongitude(?float $value): bool
    {
        return $value !== null && $value >= -180 && $value <= 180;
    }

    public function isValidPercent(?float $value): bool
    {
        return $value !== null && $value >= 0 && $value <= 100;
    }

    public function isValidCoordinate(?float $latitude, ?float $longitude): bool
    {
        return $this->isValidLatitude($latitude) && $this->isValidLongitude($longitude);
    }

    public function toFloatOrNull(?string $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (float) $value : null;
    }
}
