<?php

namespace Tests\Unit;

use App\Models\Alert;
use App\Models\Integration;
use App\Models\Machine;
use App\Models\MachineMetric;
use App\Models\Team;
use App\Services\Integration\BellService;
use App\Services\Integration\IntegrationService;
use App\Support\ApiPayload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Regression coverage for the real Bell Equipment ISO 15143-3 integration,
 * which replaced an earlier version that guessed at endpoints
 * (/fleetmatic/v1/vehicles) that never matched any real Bell system and had
 * never been exercised by a single test. Built directly against Bell's
 * published "BELL_ISO15143-3 SSO" Postman collection.
 */
class BellServiceTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN_URL = 'https://sso.bellequipment.com/connect/token';

    // ISO 15143-3 paginates the fleet snapshot: the real endpoint is
    // /Fleet/{page}. Bare /Fleet answers 405 to every verb (confirmed
    // against the live b-fleet03 gateway, 2026-08-26).
    private const FLEET_URL = 'https://b-fleet03.bellequipment.com:8080/Fleet/1';

    private const FLEET_PAGE_2_URL = 'https://b-fleet03.bellequipment.com:8080/Fleet/2';

    private const FLEET_BARE_URL = 'https://b-fleet03.bellequipment.com:8080/Fleet';

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function credentials(array $overrides = []): array
    {
        return array_merge([
            'username' => 'katisot-fleetauth@bell.co.za',
            'password' => 'test-password',
            'client_secret' => 'test-client-secret',
        ], $overrides);
    }

    private function fakeToken(): void
    {
        Http::fake([
            self::TOKEN_URL => Http::response([
                'access_token' => 'fake-access-token',
                'token_type' => 'Bearer',
                'expires_in' => 18000,
            ], 200),
        ]);
    }

    private function fleetXml(array $overrides = []): string
    {
        $fields = array_merge([
            'Latitude' => '-26.0231000',
            'Longitude' => '28.9387000',
            'IdleHours' => '3808.96',
            'LoadCount' => '13252',
            'OperatingHours' => '8376.20',
            'Payload' => '544671588',
            'PayloadUnits' => 'kilogram',
            'DEFPercent' => '0',
            'Odometer' => '94114',
            'OdometerUnits' => 'kilometre',
            'FuelConsumed' => '170285',
            'FuelUnits' => 'litre',
            'FuelRemainingPercent' => '22',
            'EngineRunning' => 'true',
            'TelemetryDate' => '2026-06-02T11:14:14Z',
        ], $overrides);

        $attrs = collect($fields)->map(fn ($v, $k) => "{$k}=\"{$v}\"")->implode(' ');

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<Fleet version="1" snapshotTime="2026-06-02T12:54:29Z">
  <Equipment {$attrs}>
    <EquipmentHeader OEMName="BELL" Model="B50E" EquipmentID="ASA B50E#9086" SerialNumber="AEBA850EC03509086" PIN="AEBA850EC03509086" UnitInstallDateTime="2020-01-01T00:00:00Z"/>
  </Equipment>
</Fleet>
XML;
    }

    public function test_fleet_requests_target_the_paginated_page_path(): void
    {
        // Bare /Fleet answers 405 for GET, POST and OPTIONS alike on the
        // live gateway; /Fleet/1 returns the real snapshot. The mock server
        // used for earlier verification served bare /Fleet, mirroring this
        // client's own wrong assumption, so no test ever caught it.
        $this->fakeToken();
        Http::fake([self::FLEET_URL => Http::response($this->fleetXml(), 200)]);

        (new BellService($this->credentials()))->fetchMachines();

        Http::assertSent(fn (Request $request): bool => ! str_starts_with($request->url(), self::FLEET_URL)
            || $request->url() === self::FLEET_URL);
        Http::assertNotSent(fn (Request $request): bool => $request->url() === self::FLEET_BARE_URL);
    }

    public function test_fleet_pagination_merges_pages_when_enabled(): void
    {
        config(['integrations.manufacturers.bell.max_fleet_pages' => 2]);

        $this->fakeToken();
        Http::fake([
            self::FLEET_URL => Http::response($this->fleetXml(), 200),
            self::FLEET_PAGE_2_URL => Http::response(str_replace('ASA B50E#9086', 'ASA B45E#7712', $this->fleetXml()), 200),
        ]);

        $result = (new BellService($this->credentials()))->fetchMachines();

        $this->assertTrue($result['success']);
        $this->assertCount(2, $result['machines']);
        $this->assertSame(
            ['ASA B50E#9086', 'ASA B45E#7712'],
            array_column($result['machines'], 'external_id'),
        );
    }

    public function test_pagination_stops_cleanly_when_a_later_page_is_rejected(): void
    {
        // The live gateway answers 400 for a page beyond the fleet's end --
        // that is the end of the fleet, not a sync failure.
        config(['integrations.manufacturers.bell.max_fleet_pages' => 3]);

        $this->fakeToken();
        Http::fake([
            self::FLEET_URL => Http::response($this->fleetXml(), 200),
            self::FLEET_PAGE_2_URL => Http::response('', 400),
        ]);

        $result = (new BellService($this->credentials()))->fetchMachines();

        $this->assertTrue($result['success']);
        $this->assertCount(1, $result['machines']);
    }

    public function test_fleet_requests_use_the_get_method(): void
    {
        // The real b-fleet03 gateway answers 405 to POST /Fleet after auth
        // (production, 2026-08-26); ISO 15143-3 data endpoints are GET.
        // The URL-keyed fakes below never asserted the verb, which is how
        // a POST shipped and passed every test while production failed.
        $this->fakeToken();
        Http::fake([self::FLEET_URL => Http::response($this->fleetXml(), 200)]);

        (new BellService($this->credentials()))->fetchMachines();

        Http::assertSent(function (Request $request): bool {
            return str_starts_with($request->url(), self::FLEET_URL)
                ? $request->method() === 'GET'
                : true;
        });
        Http::assertNotSent(fn (Request $request): bool => str_starts_with($request->url(), self::FLEET_URL)
            && $request->method() !== 'GET');
    }

    public function test_test_connection_succeeds_with_a_valid_token_and_fleet_response(): void
    {
        $this->fakeToken();
        Http::fake([self::FLEET_URL => Http::response($this->fleetXml(), 200)]);

        $service = new BellService($this->credentials());

        $this->assertTrue($service->testConnection());
    }

    public function test_test_connection_fails_honestly_when_credentials_are_missing(): void
    {
        $service = new BellService(['username' => 'someone@bell.co.za']); // no password/client_secret

        $this->assertFalse($service->testConnection());
        $this->assertStringContainsString('missing', $service->getLastError());
    }

    public function test_test_connection_fails_when_bell_sso_rejects_the_credentials(): void
    {
        Http::fake([self::TOKEN_URL => Http::response(['error' => 'invalid_grant'], 400)]);

        $service = new BellService($this->credentials());

        $this->assertFalse($service->testConnection());
        $this->assertStringContainsString('400', $service->getLastError());
    }

    public function test_fetch_machines_parses_the_fleet_snapshot_into_the_standard_sync_shape(): void
    {
        $this->fakeToken();
        Http::fake([self::FLEET_URL => Http::response($this->fleetXml(), 200)]);

        $result = (new BellService($this->credentials()))->fetchMachines();

        $this->assertTrue($result['success']);
        $this->assertCount(1, $result['machines']);

        $machine = $result['machines'][0];
        $this->assertSame('ASA B50E#9086', $machine['external_id']);
        $this->assertSame('B50E', $machine['model']);
        $this->assertSame('Bell', $machine['manufacturer']);
        $this->assertSame('AEBA850EC03509086', $machine['serial_number']);
        $this->assertSame('active', $machine['status']); // EngineRunning="true"
        $this->assertEqualsWithDelta(-26.0231, $machine['last_location']['latitude'], 0.0001);
        $this->assertEqualsWithDelta(28.9387, $machine['last_location']['longitude'], 0.0001);

        // machineData['metrics'] must be a single flat array of MachineMetric
        // fillable columns -- IntegrationService::syncMachineMetrics() feeds
        // it directly into `new MachineMetric($metrics)`.
        $this->assertSame(22.0, $machine['metrics']['fuel_level']);
        $this->assertSame(8376.20, $machine['metrics']['operating_hours']);
        $this->assertSame(3808.96, $machine['metrics']['idle_hours']);
        $this->assertSame(94114.0, $machine['metrics']['raw_data']['odometer']);
        $this->assertTrue($machine['metrics']['raw_data']['engine_running']);
    }

    public function test_engine_running_false_maps_to_idle_status(): void
    {
        $this->fakeToken();
        Http::fake([self::FLEET_URL => Http::response($this->fleetXml(['EngineRunning' => 'false']), 200)]);

        $result = (new BellService($this->credentials()))->fetchMachines();

        $this->assertSame('idle', $result['machines'][0]['status']);
    }

    /**
     * Data-quality guardrails from the AfriCoal integration spec: latitude
     * must be within -90..90, fuel remaining within 0..100. Out-of-range
     * values are dropped to null rather than trusted and stored.
     */
    public function test_out_of_range_coordinates_and_fuel_are_rejected_not_stored(): void
    {
        $this->fakeToken();
        Http::fake([self::FLEET_URL => Http::response($this->fleetXml([
            'Latitude' => '999',
            'FuelRemainingPercent' => '150',
        ]), 200)]);

        $result = (new BellService($this->credentials()))->fetchMachines();
        $machine = $result['machines'][0];

        $this->assertNull($machine['last_location']);
        $this->assertNull($machine['metrics']['latitude']);
        $this->assertNull($machine['metrics']['fuel_level']);
        // Fields that were valid are still kept -- one bad field doesn't
        // blank the whole reading.
        $this->assertSame(8376.20, $machine['metrics']['operating_hours']);
    }

    public function test_fetch_machines_fails_honestly_on_unparseable_xml_instead_of_throwing(): void
    {
        $this->fakeToken();
        Http::fake([self::FLEET_URL => Http::response('not xml at all <<<', 200)]);

        $result = (new BellService($this->credentials()))->fetchMachines();

        $this->assertFalse($result['success']);
        $this->assertNotEmpty($result['error']);
    }

    /**
     * An empty self-closing root element (a fleet with zero equipment, a
     * time series with no readings) is valid XML, but SimpleXMLElement
     * casts it to boolean false -- it must be treated as a successful
     * empty response, not reported as a parse failure.
     */
    public function test_test_connection_succeeds_when_the_fleet_has_zero_equipment(): void
    {
        $this->fakeToken();
        Http::fake([self::FLEET_URL => Http::response('<Fleet/>', 200)]);

        $service = new BellService($this->credentials());

        $this->assertTrue($service->testConnection());
        $this->assertNull($service->getLastError());
    }

    public function test_fetch_machines_returns_success_with_zero_machines_for_an_empty_fleet(): void
    {
        $this->fakeToken();
        Http::fake([self::FLEET_URL => Http::response('<Fleet/>', 200)]);

        $result = (new BellService($this->credentials()))->fetchMachines();

        $this->assertTrue($result['success']);
        $this->assertSame([], $result['machines']);
        $this->assertSame(0, $result['count']);
    }

    public function test_fetch_machine_alerts_treats_an_empty_time_series_as_valid_not_an_error(): void
    {
        $this->fakeToken();
        Http::fake([
            self::FLEET_URL => Http::response($this->fleetXml(), 200),
            'https://b-fleet03.bellequipment.com:8080/Fleet/Equipment/*/CautionCodes/*' => Http::response('<CautionCodesTimeSeries/>', 200),
        ]);

        $service = new BellService($this->credentials());

        $this->assertSame([], $service->fetchMachineAlerts('ASA B50E#9086'));
        $this->assertNull($service->getLastError());
    }

    public function test_access_token_is_cached_and_reused_across_calls(): void
    {
        $this->fakeToken();
        Http::fake([self::FLEET_URL => Http::response($this->fleetXml(), 200)]);

        $service = new BellService($this->credentials());
        $service->fetchMachines();
        $service->fetchMachines();

        // 1 token request + 1 Fleet request: the token is cached across
        // calls AND the second fetchMachines() is served by the 60s
        // snapshot micro-cache (location + status consumers share one
        // provider call -- the anti-throttle batching from Slice 4).
        Http::assertSentCount(2);
    }

    public function test_snapshot_cache_holds_for_bells_own_data_cadence(): void
    {
        $this->fakeToken();
        Http::fake([self::FLEET_URL => Http::response($this->fleetXml(), 200)]);

        $service = new BellService($this->credentials());
        $service->fetchMachines();

        // Bell rejects a second call barely 30 seconds after a successful
        // one, so the window is its 15-minute publication cadence -- not a
        // few minutes. Inside it, consumers share the one snapshot.
        $this->travel(14)->minutes();
        $service->fetchMachines();

        Http::assertSentCount(2); // 1 token + 1 Fleet fetch: still cached.
    }

    public function test_snapshot_cache_expires_past_the_configured_window(): void
    {
        $this->fakeToken();
        Http::fake([self::FLEET_URL => Http::response($this->fleetXml(), 200)]);

        $service = new BellService($this->credentials());
        $service->fetchMachines();

        $this->travel(ApiPayload::int(config('integrations.manufacturers.bell.fleet_cache_seconds'), 900) + 1)->seconds();
        $service->fetchMachines();

        Http::assertSentCount(3); // 1 token + 2 Fleet fetches across the cache boundary.
    }

    public function test_per_machine_reads_reuse_one_cached_fleet_call(): void
    {
        // The sync asks for each machine's metrics in turn. Before this was
        // cached at the fetch layer, a 26-machine fleet meant 26 live Bell
        // calls in seconds -- and Bell rejects the second call outright, so
        // every sync died partway through.
        $this->fakeToken();
        Http::fake([self::FLEET_URL => Http::response($this->fleetXml(), 200)]);

        $service = new BellService($this->credentials());
        $service->fetchMachines();
        $metrics = $service->fetchMachineMetrics('ASA B50E#9086');
        $service->fetchMachineMetrics('ASA B50E#9086');

        $this->assertSame(22.0, $metrics['fuel_level']);
        Http::assertSentCount(2); // 1 token + exactly 1 Fleet call, shared.
    }

    public function test_a_failed_snapshot_is_never_cached(): void
    {
        $this->fakeToken();
        Http::fake([self::FLEET_URL => Http::response('', 405)]);

        $service = new BellService($this->credentials());
        $this->assertFalse($service->fetchMachines()['success']);
        $this->assertFalse($service->fetchMachines()['success']);

        // A rejected snapshot must never be replayed from cache: both
        // attempts reach Bell (1 token + 2 Fleet).
        Http::assertSentCount(3);
    }

    public function test_a_401_forces_exactly_one_token_refresh_and_retry(): void
    {
        Http::fake([
            self::TOKEN_URL => Http::sequence()
                ->push(['access_token' => 'stale-token', 'expires_in' => 18000], 200)
                ->push(['access_token' => 'fresh-token', 'expires_in' => 18000], 200),
            self::FLEET_URL => Http::sequence()
                ->push('Unauthorized', 401)
                ->push($this->fleetXml(), 200),
        ]);

        $result = (new BellService($this->credentials()))->fetchMachines();

        $this->assertTrue($result['success']);
        $this->assertCount(1, $result['machines']);
    }

    public function test_fetch_machine_location_parses_the_latest_locations_reading(): void
    {
        $this->fakeToken();
        Http::fake([
            self::FLEET_URL => Http::response($this->fleetXml(), 200),
            'https://b-fleet03.bellequipment.com:8080/Fleet/Equipment/*/Locations/*' => Http::response(<<<'XML'
<LocationTimeSeries>
  <Reading ReadingUTC="2026-06-02T10:00:00Z" Latitude="-26.0200" Longitude="28.9300" Heading="180" Speed="12"/>
  <Reading ReadingUTC="2026-06-02T11:00:00Z" Latitude="-26.0300" Longitude="28.9400" Heading="190" Speed="15"/>
</LocationTimeSeries>
XML, 200),
        ]);

        $location = (new BellService($this->credentials()))->fetchMachineLocation('ASA B50E#9086');

        $this->assertNotNull($location);
        $this->assertEqualsWithDelta(-26.03, $location['latitude'], 0.001);
        $this->assertEqualsWithDelta(28.94, $location['longitude'], 0.001);
        $this->assertSame('2026-06-02T11:00:00Z', $location['timestamp']);
    }

    public function test_fetch_machine_alerts_maps_caution_codes_to_the_alert_shape_sync_expects(): void
    {
        $this->fakeToken();
        Http::fake([
            self::FLEET_URL => Http::response($this->fleetXml(), 200),
            'https://b-fleet03.bellequipment.com:8080/Fleet/Equipment/*/CautionCodes/*' => Http::response(<<<'XML'
<CautionCodesTimeSeries>
  <Reading ReadingUTC="2026-06-02T09:00:00Z" Value="E204"/>
</CautionCodesTimeSeries>
XML, 200),
        ]);

        $alerts = (new BellService($this->credentials()))->fetchMachineAlerts('ASA B50E#9086');

        $this->assertCount(1, $alerts);
        $this->assertStringContainsString('E204', $alerts[0]['title']);
        $this->assertSame('sensor', $alerts[0]['type']);
        $this->assertSame('medium', $alerts[0]['priority']);
        // 'active', not the legacy 'new' -- the alerts table's
        // chk_alert_status_values constraint rejects 'new' on Postgres.
        $this->assertSame('active', $alerts[0]['status']);
        $this->assertNotEmpty($alerts[0]['external_id']);
    }

    /**
     * End-to-end: a real Integration row wired through IntegrationService,
     * the same path SyncIntegrationMachinesJob uses, actually creates a
     * Machine and a MachineMetric with the parsed Bell data.
     */
    public function test_syncing_a_bell_integration_creates_a_real_machine_and_metric(): void
    {
        $this->fakeToken();
        Http::fake([
            self::FLEET_URL => Http::response($this->fleetXml(), 200),
            // The full sync path also fetches caution codes and the two
            // production time series per machine -- stub them empty so the
            // test never attempts real network calls (each unfaked call
            // burns the full retry cycle against Bell's real host).
            'https://b-fleet03.bellequipment.com:8080/Fleet/Equipment/*' => Http::response('<TimeSeries/>', 200),
        ]);

        $team = Team::factory()->create();
        $integration = Integration::factory()->forProvider('bell')->create([
            'team_id' => $team->id,
            'credentials' => $this->credentials(),
        ]);

        $result = app(IntegrationService::class)->syncMachines($integration);

        $this->assertTrue($result['success']);
        $this->assertSame(1, $result['count']);

        $machine = Machine::where('team_id', $team->id)->where('manufacturer', 'bell')->first();
        $this->assertNotNull($machine);
        $this->assertSame('ASA B50E#9086', $machine->manufacturer_id);
        $this->assertEqualsWithDelta(-26.0231, $machine->last_location_latitude, 0.0001);

        $metric = MachineMetric::where('machine_id', $machine->id)->first();
        $this->assertNotNull($metric);
        $this->assertSame(22.0, $metric->fuel_level);
        $this->assertSame(8376.20, $metric->operating_hours);
    }

    /**
     * fetchMachineAlerts() (caution codes) existed on every manufacturer
     * service since the interface was written, but IntegrationService::
     * syncMachines() never called it -- fetchMachines()'s own inline alerts
     * are always empty for Bell by design (caution codes are a separate
     * per-machine time-series call). This proves a real Alert row now
     * actually reaches the database through the same path SyncIntegration-
     * MachinesJob uses, not just that fetchMachineAlerts() parses correctly
     * in isolation (already covered above).
     */
    public function test_syncing_a_bell_integration_also_creates_real_alerts_from_caution_codes(): void
    {
        $this->fakeToken();
        Http::fake([
            self::FLEET_URL => Http::response($this->fleetXml(), 200),
            'https://b-fleet03.bellequipment.com:8080/Fleet/Equipment/*/CautionCodes/*' => Http::response(<<<'XML'
<CautionCodesTimeSeries>
  <Reading ReadingUTC="2026-06-02T09:00:00Z" Value="E204"/>
</CautionCodesTimeSeries>
XML, 200),
            // Production time series fetched by the same sync path -- stub
            // empty so no real network calls are attempted.
            'https://b-fleet03.bellequipment.com:8080/Fleet/Equipment/*' => Http::response('<TimeSeries/>', 200),
        ]);

        $team = Team::factory()->create();
        $integration = Integration::factory()->forProvider('bell')->create([
            'team_id' => $team->id,
            'credentials' => $this->credentials(),
        ]);

        app(IntegrationService::class)->syncMachines($integration);

        $machine = Machine::where('team_id', $team->id)->where('manufacturer', 'bell')->first();
        $this->assertNotNull($machine);

        $alert = Alert::where('machine_id', $machine->id)->first();
        $this->assertNotNull($alert, 'A real caution-code alert should have been synced into the alerts table.');
        $this->assertStringContainsString('E204', $alert->title);
        $this->assertSame('active', $alert->status, "Synced alerts must use a status the chk_alert_status_values constraint allows, not the legacy 'new'.");

        // Syncing again must not duplicate the same caution code.
        app(IntegrationService::class)->syncMachines($integration->fresh());
        $this->assertSame(1, Alert::where('machine_id', $machine->id)->count());
    }

    /**
     * The EXACT structure Bell's live server returned on 2026-08-21
     * (element names verified against a real /Fleet response): values are
     * nested inside per-section elements carrying a datetime attribute,
     * not flattened onto <Equipment> as the spec-derived fixture above
     * guessed. Both shapes must parse.
     */
    private function liveNestedFleetXml(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Fleet version="1" snapshotTime="2026-08-21T16:30:00Z">
  <Equipment>
    <EquipmentHeader>
      <UnitInstallDateTime>2020-01-01T00:00:00Z</UnitInstallDateTime>
      <OEMName>BELL</OEMName>
      <Model>B50E</Model>
      <EquipmentID>ASA B50E#9823</EquipmentID>
      <SerialNumber>AEBA850EC03509823</SerialNumber>
      <PIN>AEBA850EC03509823</PIN>
    </EquipmentHeader>
    <Location datetime="2026-08-21T16:12:44Z">
      <Latitude>-26.0234</Latitude>
      <Longitude>28.9384</Longitude>
    </Location>
    <CumulativeIdleHours datetime="2026-08-21T16:12:44Z">
      <Hour>3808.96</Hour>
    </CumulativeIdleHours>
    <CumulativeLoadCount datetime="2026-08-21T16:12:44Z">
      <Count>13252</Count>
    </CumulativeLoadCount>
    <CumulativeOperatingHours datetime="2026-08-21T16:12:44Z">
      <Hour>8376.20</Hour>
    </CumulativeOperatingHours>
    <CumulativePayloadTotals datetime="2026-08-21T16:12:44Z">
      <PayloadUnits>kilogram</PayloadUnits>
      <Payload>596361594</Payload>
    </CumulativePayloadTotals>
    <DEFRemaining datetime="2026-08-21T16:12:44Z">
      <Percent>47</Percent>
      <DEFTankCapacityUnits>litre</DEFTankCapacityUnits>
    </DEFRemaining>
    <Distance datetime="2026-08-21T16:12:44Z">
      <OdometerUnits>kilometre</OdometerUnits>
      <Odometer>70439</Odometer>
    </Distance>
    <EngineStatus datetime="2026-08-21T16:12:44Z">
      <EngineNumber>1</EngineNumber>
      <Running>true</Running>
    </EngineStatus>
    <FuelUsed datetime="2026-08-21T16:12:44Z">
      <FuelUnits>litre</FuelUnits>
      <FuelConsumed>149529</FuelConsumed>
    </FuelUsed>
    <FuelRemaining datetime="2026-08-21T16:12:44Z">
      <Percent>63</Percent>
    </FuelRemaining>
  </Equipment>
</Fleet>
XML;
    }

    public function test_fetch_machines_parses_the_live_nested_section_snapshot(): void
    {
        $this->fakeToken();
        Http::fake([self::FLEET_URL => Http::response($this->liveNestedFleetXml(), 200)]);

        $result = (new BellService($this->credentials()))->fetchMachines();

        $this->assertTrue($result['success']);
        $machine = $result['machines'][0];

        $this->assertSame('ASA B50E#9823', $machine['external_id']);
        $this->assertSame('active', $machine['status'], 'EngineStatus/Running=true must map to active, not unknown.');
        $this->assertEqualsWithDelta(-26.0234, $machine['last_location']['latitude'], 0.0001);
        $this->assertEqualsWithDelta(28.9384, $machine['last_location']['longitude'], 0.0001);

        $metrics = $machine['metrics'];
        $this->assertSame(63.0, $metrics['fuel_level'], 'fuel_level must come from FuelRemaining/Percent, not DEFRemaining/Percent (which is 47).');
        $this->assertSame(8376.20, $metrics['operating_hours'], 'operating_hours must come from CumulativeOperatingHours/Hour, not CumulativeIdleHours/Hour.');
        $this->assertSame(3808.96, $metrics['idle_hours']);
        $this->assertSame(
            '2026-08-21T16:12:44Z',
            $metrics['recorded_at'],
            'recorded_at must be Bell\'s own telemetry datetime (Location@datetime), not the moment we happened to sync.'
        );
        $this->assertSame(13252.0, $metrics['raw_data']['load_count']);
        $this->assertSame(47.0, $metrics['raw_data']['def_percent']);
        $this->assertSame(70439.0, $metrics['raw_data']['odometer']);
        $this->assertSame(596361594.0, $metrics['raw_data']['cumulative_payload']);
        $this->assertTrue($metrics['raw_data']['engine_running']);
    }

    public function test_time_series_urls_use_zulu_timestamps_with_no_plus_sign(): void
    {
        $this->fakeToken();
        Http::fake([
            self::FLEET_URL => Http::response($this->fleetXml(), 200),
            'https://b-fleet03.bellequipment.com:8080/Fleet/Equipment/*' => Http::response('<LocationTimeSeries/>', 200),
        ]);

        (new BellService($this->credentials()))->fetchMachineLocation('ASA B50E#9823');

        Http::assertSent(function ($request) {
            $url = $request->url();
            if (! str_contains($url, '/Locations/')) {
                return false;
            }

            // Bell's IIS rejects the literal '+' that toIso8601String()'s
            // '+00:00' offset puts in the path (observed live: every
            // time-series call 400'd). Zulu format is what the AEMP 2.0
            // convention and Bell's own Postman collection use.
            return ! str_contains($url, '+')
                && preg_match('#/Locations/\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z/\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$#', $url) === 1;
        });
    }

    public function test_time_series_calls_are_addressed_by_pin_not_display_equipment_id(): void
    {
        $this->fakeToken();
        Http::fake([
            self::FLEET_URL => Http::response($this->liveNestedFleetXml(), 200),
            'https://b-fleet03.bellequipment.com:8080/Fleet/Equipment/*' => Http::response('<LocationTimeSeries/>', 200),
        ]);

        (new BellService($this->credentials()))->fetchMachineLocation('ASA B50E#9823');

        // Confirmed live 2026-08-21: Bell's per-equipment endpoints are
        // routed by PIN/serial -- the display EquipmentID's '#' (%23) is
        // rejected 400 by ASP.NET path validation before routing, so every
        // time-series call for this fleet failed until IDs were resolved
        // through the snapshot's EquipmentID->PIN map.
        Http::assertSent(function ($request) {
            $url = $request->url();
            if (! str_contains($url, '/Locations/')) {
                return false;
            }

            return str_contains($url, 'AEBA850EC03509823') && ! str_contains($url, '%23');
        });
    }

    public function test_client_errors_are_not_retried(): void
    {
        $this->fakeToken();
        Http::fake([self::FLEET_URL => Http::response('Bad Request', 400)]);

        (new BellService($this->credentials()))->fetchMachines();

        // One token request + exactly ONE /Fleet attempt: a 400/404/405 is
        // deterministic, and retrying it tripled the call volume during the
        // live sync on 2026-08-21 -- enough to get the server IP throttled
        // by Bell. Only 5xx/connection failures are worth retrying (401
        // gets its single token-refresh retry separately).
        Http::assertSentCount(2);
    }

    public function test_server_errors_are_still_retried(): void
    {
        $this->fakeToken();
        Http::fake([self::FLEET_URL => Http::response('Server Error', 503)]);

        $service = new BellService($this->credentials());
        $service->setRetryDelay(0);

        $service->fetchMachines();

        // One token request + all three /Fleet attempts.
        Http::assertSentCount(4);
    }
}
