<?php

namespace Tests\Feature;

use App\Models\Integration;
use App\Models\MachineMetric;
use App\Models\Team;
use App\Services\Integration\BellFleetParser;
use App\Services\Integration\IntegrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use SimpleXMLElement;
use Tests\TestCase;

/**
 * Telemetry freshness vs the §19 dedupe.
 *
 * Bell's ISO 15143-3 snapshot stamps EVERY section with its own datetime
 * (Location, CumulativeOperatingHours, CumulativePayloadTotals, ...).
 * The parser used to stamp recorded_at with only the LOCATION section's
 * datetime -- so when a stationary machine's cumulative counters advanced
 * (engine hours, payload) while its GPS timestamp stood still, the §19
 * dedupe threw the whole row away and the Fleet page froze on stale
 * hours/tonnage even though the API had newer values.
 */
class TelemetryFreshnessTest extends TestCase
{
    use RefreshDatabase;

    private function equipmentXml(
        string $locationDatetime,
        string $hoursDatetime,
        string $hours,
        string $payloadDatetime,
        string $payload,
    ): SimpleXMLElement {
        return new SimpleXMLElement(<<<XML
<Equipment>
  <EquipmentHeader OEMName="Bell" Model="B50E" EquipmentID="ASA B50E#9086" SerialNumber="9086"/>
  <Location datetime="{$locationDatetime}">
    <Latitude>-26.1</Latitude>
    <Longitude>28.0</Longitude>
  </Location>
  <CumulativeOperatingHours datetime="{$hoursDatetime}">
    <Hour>{$hours}</Hour>
  </CumulativeOperatingHours>
  <CumulativePayloadTotals datetime="{$payloadDatetime}">
    <Payload>{$payload}</Payload>
    <PayloadUnits>kilograms</PayloadUnits>
  </CumulativePayloadTotals>
</Equipment>
XML);
    }

    public function test_recorded_at_is_the_newest_reporting_section_not_just_location(): void
    {
        $metric = (new BellFleetParser)->buildCurrentMetric($this->equipmentXml(
            locationDatetime: '2026-08-27T06:00:00Z',
            hoursDatetime: '2026-08-27T08:30:00Z',
            hours: '1200.5',
            payloadDatetime: '2026-08-27T08:15:00Z',
            payload: '50000',
        ));

        $this->assertSame('2026-08-27T08:30:00Z', $metric['recorded_at']);
        $this->assertSame(1200.5, $metric['operating_hours']);
    }

    public function test_position_timestamp_stays_the_location_sections_own_datetime(): void
    {
        // The live map's "position reported X ago" must reflect when the
        // POSITION was reported -- not when engine hours last ticked.
        $machine = (new BellFleetParser)->parseEquipmentNode($this->equipmentXml(
            locationDatetime: '2026-08-27T06:00:00Z',
            hoursDatetime: '2026-08-27T08:30:00Z',
            hours: '1200.5',
            payloadDatetime: '2026-08-27T08:15:00Z',
            payload: '50000',
        ));

        $this->assertSame('2026-08-27T06:00:00Z', $machine['last_location']['timestamp']);
    }

    public function test_advancing_hours_with_a_stationary_location_writes_a_new_metric_row(): void
    {
        $team = Team::factory()->create();
        $integration = Integration::factory()->connected()->create(['team_id' => $team->id, 'provider' => 'bell']);
        $parser = new BellFleetParser;
        $service = app(IntegrationService::class);

        $service->syncMachine($integration, $parser->parseEquipmentNode($this->equipmentXml(
            locationDatetime: '2026-08-27T06:00:00Z',
            hoursDatetime: '2026-08-27T07:00:00Z',
            hours: '1200.0',
            payloadDatetime: '2026-08-27T07:00:00Z',
            payload: '48000',
        )));

        // Machine parked in place; engine hours and payload advanced.
        $service->syncMachine($integration, $parser->parseEquipmentNode($this->equipmentXml(
            locationDatetime: '2026-08-27T06:00:00Z',
            hoursDatetime: '2026-08-27T08:30:00Z',
            hours: '1201.5',
            payloadDatetime: '2026-08-27T08:30:00Z',
            payload: '52000',
        )));

        $this->assertSame(2, MachineMetric::query()->count());

        $latest = MachineMetric::query()->orderByDesc('recorded_at')->first();
        $this->assertSame(1201.5, (float) $latest->operating_hours);
        $this->assertSame(52000.0, (float) data_get($latest->raw_data, 'cumulative_payload'));
    }

    public function test_an_unchanged_snapshot_is_still_deduped(): void
    {
        $team = Team::factory()->create();
        $integration = Integration::factory()->connected()->create(['team_id' => $team->id, 'provider' => 'bell']);
        $parser = new BellFleetParser;
        $service = app(IntegrationService::class);

        foreach ([1, 2] as $ignored) {
            $service->syncMachine($integration, $parser->parseEquipmentNode($this->equipmentXml(
                locationDatetime: '2026-08-27T06:00:00Z',
                hoursDatetime: '2026-08-27T07:00:00Z',
                hours: '1200.0',
                payloadDatetime: '2026-08-27T07:00:00Z',
                payload: '48000',
            )));
        }

        $this->assertSame(1, MachineMetric::query()->count());
    }
}
