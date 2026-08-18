<?php

namespace Tests\Unit;

use App\Models\Alert;
use App\Models\Integration;
use App\Models\Machine;
use App\Models\MachineMetric;
use App\Models\Team;
use App\Services\Integration\BellService;
use App\Services\Integration\IntegrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    private const FLEET_URL = 'https://b-fleet03.bellequipment.com:8080/Fleet';

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

    public function test_access_token_is_cached_and_reused_across_calls(): void
    {
        $this->fakeToken();
        Http::fake([self::FLEET_URL => Http::response($this->fleetXml(), 200)]);

        $service = new BellService($this->credentials());
        $service->fetchMachines();
        $service->fetchMachines();

        Http::assertSentCount(3); // 1 token request + 2 Fleet requests, not 2 token requests.
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
        Http::fake([self::FLEET_URL => Http::response($this->fleetXml(), 200)]);

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
}
