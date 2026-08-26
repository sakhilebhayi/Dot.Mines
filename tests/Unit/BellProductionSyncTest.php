<?php

namespace Tests\Unit;

use App\Models\Integration;
use App\Models\Machine;
use App\Models\MineArea;
use App\Models\ProductionRecord;
use App\Models\ProductionTarget;
use App\Models\Team;
use App\Services\Integration\BellService;
use App\Services\Integration\IntegrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * End-to-end coverage for the Bell production pipeline: the same
 * IntegrationService::syncMachines() path SyncIntegrationMachinesJob and
 * connect() use must turn Bell's CumulativeLoadCount / CumulativePayloadTotals
 * time-series into per-machine, per-day ProductionRecords the Production
 * page queries -- previously nothing anywhere wrote ProductionRecord from
 * any integration, so the Production page only ever showed manual entries.
 */
class BellProductionSyncTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN_URL = 'https://sso.bellequipment.com/connect/token';

    private const FLEET_URL = 'https://b-fleet03.bellequipment.com:8080/Fleet/1';

    private const LOAD_COUNT_URL = 'https://b-fleet03.bellequipment.com:8080/Fleet/Equipment/*/CumulativeLoadCount/*';

    private const PAYLOAD_URL = 'https://b-fleet03.bellequipment.com:8080/Fleet/Equipment/*/CumulativePayloadTotals/*';

    private const CAUTION_URL = 'https://b-fleet03.bellequipment.com:8080/Fleet/Equipment/*/CautionCodes/*';

    private function credentials(): array
    {
        return [
            'username' => 'katisot-fleetauth@bell.co.za',
            'password' => 'test-password',
            'client_secret' => 'test-client-secret',
        ];
    }

    private function fleetXml(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Fleet version="1" snapshotTime="2026-06-02T12:54:29Z">
  <Equipment Latitude="-26.0231000" Longitude="28.9387000" IdleHours="3808.96" LoadCount="13252" OperatingHours="8376.20" Payload="544671588" PayloadUnits="kilogram" FuelRemainingPercent="22" EngineRunning="true" TelemetryDate="2026-06-02T11:14:14Z">
    <EquipmentHeader OEMName="BELL" Model="B50E" EquipmentID="ASA B50E#9086" SerialNumber="AEBA850EC03509086" PIN="AEBA850EC03509086"/>
  </Equipment>
</Fleet>
XML;
    }

    private string $loadXml = '';

    private string $payloadXml = '';

    private bool $bellApiFaked = false;

    /**
     * Two days of cumulative counters. Day 1's baseline is its own first
     * reading (nothing earlier is in the window), so day 1 = last - first.
     * Day 2's baseline is day 1's closing value, so overnight production
     * still lands on day 2.
     *
     * The time-series stubs read $this->loadXml/$this->payloadXml through
     * closures so a test can change what Bell "reports" between syncs --
     * Laravel's Http factory is a singleton, so re-registering the same
     * URL pattern would never win over the first registration.
     */
    private function fakeBellApi(?string $loadXml = null, ?string $payloadXml = null): void
    {
        $dayOne = now()->subDays(2)->toDateString();
        $dayTwo = now()->subDay()->toDateString();

        // Element-style bodies copied from Bell's REAL responses (captured
        // live 2026-08-22): a lowercase `datetime` attribute with the value
        // in child elements, plus the pagination <Links> blocks. The old
        // fixtures here used an invented attribute-style <Reading .../>
        // shape Bell never sends -- the tests passed while production
        // parsed zero readings from every real response.
        $loadXml ??= <<<XML
<CumulativeLoadCountMessages xmlns="http://standards.iso.org/iso/15143/-3">
  <Links><rel>self</rel><href>/Fleet/Equipment/X/CumulativeLoadCount/a/b/1</href></Links>
  <Links><rel>first</rel><href>/Fleet/Equipment/X/CumulativeLoadCount/a/b/1</href></Links>
  <CumulativeLoadCount datetime="{$dayOne}T06:00:00Z"><Count>13000</Count></CumulativeLoadCount>
  <CumulativeLoadCount datetime="{$dayOne}T18:00:00Z"><Count>13050</Count></CumulativeLoadCount>
  <CumulativeLoadCount datetime="{$dayTwo}T06:00:00Z"><Count>13120</Count></CumulativeLoadCount>
  <CumulativeLoadCount datetime="{$dayTwo}T18:00:00Z"><Count>13200</Count></CumulativeLoadCount>
</CumulativeLoadCountMessages>
XML;

        $payloadXml ??= <<<XML
<CumulativePayloadTotalMessages xmlns="http://standards.iso.org/iso/15143/-3">
  <Links><rel>self</rel><href>/Fleet/Equipment/X/CumulativePayloadTotals/a/b/1</href></Links>
  <CumulativePayloadTotals datetime="{$dayOne}T06:00:00Z"><PayloadUnits>kilogram</PayloadUnits><Payload>540000000.00</Payload></CumulativePayloadTotals>
  <CumulativePayloadTotals datetime="{$dayOne}T18:00:00Z"><PayloadUnits>kilogram</PayloadUnits><Payload>540250000.00</Payload></CumulativePayloadTotals>
  <CumulativePayloadTotals datetime="{$dayTwo}T06:00:00Z"><PayloadUnits>kilogram</PayloadUnits><Payload>540600000.00</Payload></CumulativePayloadTotals>
  <CumulativePayloadTotals datetime="{$dayTwo}T18:00:00Z"><PayloadUnits>kilogram</PayloadUnits><Payload>541000000.00</Payload></CumulativePayloadTotals>
</CumulativePayloadTotalMessages>
XML;

        $this->loadXml = $loadXml;
        $this->payloadXml = $payloadXml;

        if ($this->bellApiFaked) {
            return;
        }

        $this->bellApiFaked = true;

        Http::fake([
            self::TOKEN_URL => Http::response([
                'access_token' => 'fake-access-token',
                'token_type' => 'Bearer',
                'expires_in' => 18000,
            ], 200),
            self::FLEET_URL => Http::response($this->fleetXml(), 200),
            self::CAUTION_URL => Http::response('<CautionCodesTimeSeries/>', 200),
            self::LOAD_COUNT_URL => fn () => Http::response($this->loadXml, 200),
            self::PAYLOAD_URL => fn () => Http::response($this->payloadXml, 200),
        ]);
    }

    private function connectedBellIntegration(Team $team): Integration
    {
        return Integration::factory()->forProvider('bell')->create([
            'team_id' => $team->id,
            'status' => 'connected',
            'credentials' => $this->credentials(),
        ]);
    }

    public function test_bell_service_fetches_production_time_series(): void
    {
        $this->fakeBellApi();

        $service = new BellService($this->credentials());

        $result = $service->fetchMachineProduction('ASA B50E#9086', now()->subDays(3), now());

        $this->assertTrue($result['success']);
        $this->assertCount(4, $result['load_count_readings']);
        $this->assertCount(4, $result['payload_readings']);
        $this->assertSame(13000.0, $result['load_count_readings'][0]['value']);
        $this->assertSame('kilogram', $result['payload_readings'][0]['units']);
    }

    public function test_attribute_style_readings_still_parse(): void
    {
        // Locations and caution codes really do use attribute-style
        // readings -- extending extraction to Bell's element-style
        // production series must not break the original shape.
        $dayOne = now()->subDay()->toDateString();
        $this->fakeBellApi(
            <<<XML
<CumulativeLoadCountTimeSeries>
  <Reading ReadingUTC="{$dayOne}T06:00:00Z" Value="13000"/>
  <Reading ReadingUTC="{$dayOne}T18:00:00Z" Value="13050"/>
</CumulativeLoadCountTimeSeries>
XML,
            '<CumulativePayloadTotalMessages xmlns="http://standards.iso.org/iso/15143/-3"/>'
        );

        $service = new BellService($this->credentials());
        $result = $service->fetchMachineProduction('ASA B50E#9086', now()->subDays(3), now());

        $this->assertTrue($result['success']);
        $this->assertCount(2, $result['load_count_readings']);
        $this->assertSame(13000.0, $result['load_count_readings'][0]['value']);
    }

    public function test_syncing_a_bell_integration_creates_daily_production_records(): void
    {
        $this->fakeBellApi();

        $team = Team::factory()->create();
        $integration = $this->connectedBellIntegration($team);

        $result = app(IntegrationService::class)->syncMachines($integration);
        $this->assertTrue($result['success']);

        $machine = Machine::where('team_id', $team->id)->where('manufacturer', 'bell')->first();
        $this->assertNotNull($machine);

        $records = ProductionRecord::where('team_id', $team->id)
            ->where('machine_id', $machine->id)
            ->orderBy('record_date')
            ->get();

        $this->assertCount(2, $records, 'One production record per day with readings should exist.');

        $dayOne = $records->first();
        // Day 1: baseline is its own first reading -- 13050 - 13000 loads,
        // (540250000 - 540000000) kg => 250 tonnes.
        $this->assertSame(now()->subDays(2)->toDateString(), $dayOne->record_date->toDateString());
        $this->assertSame(50, (int) data_get($dayOne->metadata, 'loads'));
        $this->assertSame(50, (int) data_get($dayOne->metadata, 'cycles'));
        $this->assertEqualsWithDelta(250.0, (float) $dayOne->quantity_produced, 0.01);
        $this->assertSame('tonnes', $dayOne->unit);
        $this->assertSame('completed', $dayOne->status);
        $this->assertSame('telemetry', data_get($dayOne->metadata, 'source'));
        $this->assertSame('bell', data_get($dayOne->metadata, 'provider'));

        $dayTwo = $records->last();
        // Day 2: baseline is day 1's close -- 13200 - 13050 loads,
        // (541000000 - 540250000) kg => 750 tonnes.
        $this->assertSame(150, (int) data_get($dayTwo->metadata, 'loads'));
        $this->assertEqualsWithDelta(750.0, (float) $dayTwo->quantity_produced, 0.01);
    }

    public function test_repeated_sync_updates_rather_than_duplicates_production_records(): void
    {
        $this->fakeBellApi();

        $team = Team::factory()->create();
        $integration = $this->connectedBellIntegration($team);

        app(IntegrationService::class)->syncMachines($integration);
        // The per-machine production series only refetch once the hourly
        // deep-sync window elapses (anti-throttle gate, Slice 7).
        $this->travel(3601)->seconds();
        app(IntegrationService::class)->syncMachines($integration->fresh());

        $machine = Machine::where('team_id', $team->id)->where('manufacturer', 'bell')->first();

        $this->assertSame(
            2,
            ProductionRecord::where('machine_id', $machine->id)->count(),
            'Re-syncing the same window must update existing records, not duplicate them.'
        );
    }

    public function test_updated_bell_values_update_the_existing_record(): void
    {
        $this->fakeBellApi();

        $team = Team::factory()->create();
        $integration = $this->connectedBellIntegration($team);

        app(IntegrationService::class)->syncMachines($integration);

        $machine = Machine::where('team_id', $team->id)->where('manufacturer', 'bell')->first();
        $dayTwoDate = now()->subDay()->toDateString();

        // Bell later reports more loads/payload for day 2 (late telemetry).
        $dayOne = now()->subDays(2)->toDateString();
        $this->fakeBellApi(
            <<<XML
<CumulativeLoadCountMessages xmlns="http://standards.iso.org/iso/15143/-3">
  <CumulativeLoadCount datetime="{$dayOne}T18:00:00Z"><Count>13050</Count></CumulativeLoadCount>
  <CumulativeLoadCount datetime="{$dayTwoDate}T06:00:00Z"><Count>13120</Count></CumulativeLoadCount>
  <CumulativeLoadCount datetime="{$dayTwoDate}T21:00:00Z"><Count>13260</Count></CumulativeLoadCount>
</CumulativeLoadCountMessages>
XML,
            <<<XML
<CumulativePayloadTotalMessages xmlns="http://standards.iso.org/iso/15143/-3">
  <CumulativePayloadTotals datetime="{$dayOne}T18:00:00Z"><PayloadUnits>kilogram</PayloadUnits><Payload>540250000.00</Payload></CumulativePayloadTotals>
  <CumulativePayloadTotals datetime="{$dayTwoDate}T21:00:00Z"><PayloadUnits>kilogram</PayloadUnits><Payload>541500000.00</Payload></CumulativePayloadTotals>
</CumulativePayloadTotalMessages>
XML
        );

        // Past the hourly deep-sync window so the series refetch runs.
        $this->travel(3601)->seconds();
        app(IntegrationService::class)->syncMachines($integration->fresh());

        $records = ProductionRecord::where('machine_id', $machine->id)
            ->whereDate('record_date', $dayTwoDate)
            ->get();

        $this->assertCount(1, $records);
        $this->assertSame(210, (int) data_get($records->first()->metadata, 'loads'));
        $this->assertEqualsWithDelta(1250.0, (float) $records->first()->quantity_produced, 0.01);
    }

    public function test_synced_machines_default_to_the_teams_first_active_mine_area(): void
    {
        $this->fakeBellApi();

        $team = Team::factory()->create();
        MineArea::create(['team_id' => $team->id, 'name' => 'Closed Pit', 'status' => 'inactive']);
        $active = MineArea::create(['team_id' => $team->id, 'name' => 'Pit A', 'status' => 'active']);
        $integration = $this->connectedBellIntegration($team);

        app(IntegrationService::class)->syncMachines($integration);

        $machine = Machine::where('team_id', $team->id)->where('manufacturer', 'bell')->first();

        // machines.mine_area_id is NOT NULL on MySQL/Postgres -- without
        // this default every synced machine insert died on the constraint
        // (silently, because syncMachine() logs and swallows), which killed
        // fleet AND production for fresh installs on those drivers.
        $this->assertSame($active->id, $machine->mine_area_id);

        $record = ProductionRecord::where('machine_id', $machine->id)->first();
        $this->assertSame($active->id, $record->mine_area_id);
    }

    public function test_machine_mine_area_and_daily_target_are_attached_when_available(): void
    {
        $this->fakeBellApi();

        $team = Team::factory()->create();
        $area = MineArea::create(['team_id' => $team->id, 'name' => 'Pit A', 'status' => 'active']);
        $integration = $this->connectedBellIntegration($team);

        ProductionTarget::create([
            'team_id' => $team->id,
            'mine_area_id' => null,
            'period_type' => 'daily',
            'start_date' => now()->subDays(30)->toDateString(),
            'end_date' => now()->addDays(30)->toDateString(),
            'target_quantity' => 500,
            'unit' => 'tonnes',
            'is_active' => true,
        ]);

        // First sync creates the machine; assign it to an area like a real
        // dispatcher would, then re-sync.
        app(IntegrationService::class)->syncMachines($integration);
        $machine = Machine::where('team_id', $team->id)->where('manufacturer', 'bell')->first();
        $machine->update(['mine_area_id' => $area->id]);

        ProductionRecord::where('machine_id', $machine->id)->forceDelete();
        // Past the hourly deep-sync window so the series refetch runs.
        $this->travel(3601)->seconds();
        app(IntegrationService::class)->syncMachines($integration->fresh());

        $record = ProductionRecord::where('machine_id', $machine->id)->orderBy('record_date')->first();

        $this->assertSame($area->id, $record->mine_area_id);
        $this->assertEqualsWithDelta(500.0, (float) $record->target_quantity, 0.01);
    }

    public function test_a_user_deleted_telemetry_record_is_not_resurrected(): void
    {
        $this->fakeBellApi();

        $team = Team::factory()->create();
        $integration = $this->connectedBellIntegration($team);

        app(IntegrationService::class)->syncMachines($integration);

        $machine = Machine::where('team_id', $team->id)->where('manufacturer', 'bell')->first();
        $record = ProductionRecord::where('machine_id', $machine->id)->orderBy('record_date')->first();
        $record->delete(); // soft delete, as the dashboard does

        app(IntegrationService::class)->syncMachines($integration->fresh());

        $this->assertSame(
            1,
            ProductionRecord::where('machine_id', $machine->id)
                ->whereDate('record_date', $record->record_date)
                ->withTrashed()->count(),
            'A record the user deliberately deleted must not be recreated by the next sync.'
        );
    }

    public function test_readings_are_bucketed_into_days_using_the_team_timezone(): void
    {
        // 23:30 UTC on day N is 01:30 on day N+1 in Johannesburg (UTC+2).
        $dayOne = now()->subDays(2)->toDateString();
        $dayTwo = now()->subDay()->toDateString();

        $this->fakeBellApi(
            <<<XML
<CumulativeLoadCountTimeSeries>
  <Reading ReadingUTC="{$dayOne}T10:00:00Z" Value="100"/>
  <Reading ReadingUTC="{$dayOne}T23:30:00Z" Value="160"/>
</CumulativeLoadCountTimeSeries>
XML,
            '<CumulativePayloadTotalsTimeSeries/>'
        );

        $team = Team::factory()->create(['timezone' => 'Africa/Johannesburg']);
        $integration = $this->connectedBellIntegration($team);

        app(IntegrationService::class)->syncMachines($integration);

        $machine = Machine::where('team_id', $team->id)->where('manufacturer', 'bell')->first();
        $records = ProductionRecord::where('machine_id', $machine->id)->orderBy('record_date')->get();

        // The 23:30Z reading belongs to the NEXT local day, so it becomes
        // that day's production (60 loads), not day one's.
        $this->assertCount(1, $records);
        $this->assertSame($dayTwo, $records->first()->record_date->toDateString());
        $this->assertSame(60, (int) data_get($records->first()->metadata, 'loads'));
    }

    public function test_no_production_records_are_created_when_bell_reports_no_production(): void
    {
        $this->fakeBellApi('<CumulativeLoadCountTimeSeries/>', '<CumulativePayloadTotalsTimeSeries/>');

        $team = Team::factory()->create();
        $integration = $this->connectedBellIntegration($team);

        app(IntegrationService::class)->syncMachines($integration);

        $this->assertSame(0, ProductionRecord::where('team_id', $team->id)->count());
    }

    public function test_counter_resets_never_produce_negative_production(): void
    {
        $dayOne = now()->subDay()->toDateString();

        $this->fakeBellApi(
            <<<XML
<CumulativeLoadCountTimeSeries>
  <Reading ReadingUTC="{$dayOne}T06:00:00Z" Value="9000"/>
  <Reading ReadingUTC="{$dayOne}T18:00:00Z" Value="20"/>
</CumulativeLoadCountTimeSeries>
XML,
            '<CumulativePayloadTotalsTimeSeries/>'
        );

        $team = Team::factory()->create();
        $integration = $this->connectedBellIntegration($team);

        app(IntegrationService::class)->syncMachines($integration);

        $machine = Machine::where('team_id', $team->id)->where('manufacturer', 'bell')->first();
        $records = ProductionRecord::where('machine_id', $machine->id)->get();

        // A counter reset (machine swap/ECU replacement) must clamp to zero,
        // and a zero-production day creates no record at all.
        $this->assertCount(0, $records);
    }

    public function test_duplicate_and_out_of_order_readings_do_not_change_the_result(): void
    {
        // Brief §19: the same API reading delivered twice, or readings
        // delivered out of order, must not create or inflate production.
        $dayOne = now()->subDays(2)->toDateString();
        $dayTwo = now()->subDay()->toDateString();
        $this->fakeBellApi(
            <<<XML
<CumulativeLoadCountMessages xmlns="http://standards.iso.org/iso/15143/-3">
  <CumulativeLoadCount datetime="{$dayTwo}T18:00:00Z"><Count>13200</Count></CumulativeLoadCount>
  <CumulativeLoadCount datetime="{$dayOne}T06:00:00Z"><Count>13000</Count></CumulativeLoadCount>
  <CumulativeLoadCount datetime="{$dayOne}T18:00:00Z"><Count>13050</Count></CumulativeLoadCount>
  <CumulativeLoadCount datetime="{$dayOne}T18:00:00Z"><Count>13050</Count></CumulativeLoadCount>
  <CumulativeLoadCount datetime="{$dayTwo}T06:00:00Z"><Count>13120</Count></CumulativeLoadCount>
</CumulativeLoadCountMessages>
XML,
            '<CumulativePayloadTotalMessages xmlns="http://standards.iso.org/iso/15143/-3"/>'
        );

        $team = Team::factory()->create();
        $integration = $this->connectedBellIntegration($team);

        app(IntegrationService::class)->syncMachines($integration);

        $machine = Machine::where('team_id', $team->id)->where('manufacturer', 'bell')->first();
        $records = ProductionRecord::where('machine_id', $machine->id)->orderBy('record_date')->get();

        $this->assertCount(2, $records);
        $this->assertSame(50, (int) data_get($records->first()->metadata, 'loads'));
        $this->assertSame(150, (int) data_get($records->last()->metadata, 'loads'));
    }

    public function test_partial_response_with_only_load_counts_still_records_loads(): void
    {
        // Brief §19: a partial response (payload series empty) records what
        // IS real -- the loads -- with zero tonnes, not a fabricated mass.
        $dayOne = now()->subDay()->toDateString();
        $this->fakeBellApi(
            <<<XML
<CumulativeLoadCountMessages xmlns="http://standards.iso.org/iso/15143/-3">
  <CumulativeLoadCount datetime="{$dayOne}T06:00:00Z"><Count>13000</Count></CumulativeLoadCount>
  <CumulativeLoadCount datetime="{$dayOne}T18:00:00Z"><Count>13040</Count></CumulativeLoadCount>
</CumulativeLoadCountMessages>
XML,
            '<CumulativePayloadTotalMessages xmlns="http://standards.iso.org/iso/15143/-3"/>'
        );

        $team = Team::factory()->create();
        $integration = $this->connectedBellIntegration($team);

        app(IntegrationService::class)->syncMachines($integration);

        $machine = Machine::where('team_id', $team->id)->where('manufacturer', 'bell')->first();
        $record = ProductionRecord::where('machine_id', $machine->id)->first();

        $this->assertNotNull($record);
        $this->assertSame(40, (int) data_get($record->metadata, 'loads'));
        $this->assertSame(0.0, (float) $record->quantity_produced);
    }

    public function test_sync_records_run_statistics_for_the_api_health_panel(): void
    {
        $this->fakeBellApi();

        $team = Team::factory()->create();
        $integration = $this->connectedBellIntegration($team);

        app(IntegrationService::class)->syncMachines($integration);

        $stats = $integration->fresh()->last_sync_stats;

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('duration_ms', $stats);
        $this->assertSame(1, $stats['machines_received']);
        $this->assertSame(1, $stats['machines_synced']);
        $this->assertTrue($stats['deep_sync']);
        $this->assertSame(2, $stats['production_records_total']);
        $this->assertSame(2, $stats['production_records_delta']);
    }

    public function test_a_rejected_fetch_records_failure_statistics(): void
    {
        Http::fake([
            self::TOKEN_URL => Http::response([
                'access_token' => 'fake-access-token',
                'token_type' => 'Bearer',
                'expires_in' => 18000,
            ], 200),
            self::FLEET_URL => Http::response('nope', 405),
        ]);

        $team = Team::factory()->create();
        $integration = $this->connectedBellIntegration($team);

        $result = app(IntegrationService::class)->syncMachines($integration);

        $this->assertFalse($result['success']);
        $stats = $integration->fresh()->last_sync_stats;
        $this->assertTrue($stats['failed']);
        $this->assertArrayHasKey('duration_ms', $stats);
        $this->assertNotSame('', $stats['reason']);
    }
}
