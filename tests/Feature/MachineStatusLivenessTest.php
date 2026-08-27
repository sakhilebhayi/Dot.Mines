<?php

namespace Tests\Feature;

use App\Events\MachineOffline;
use App\Jobs\MachineStatusMonitoringJob;
use App\Livewire\Fleet;
use App\Models\Integration;
use App\Models\Machine;
use App\Models\MachineMetric;
use App\Models\Team;
use App\Models\User;
use App\Services\Integration\BellFleetParser;
use App\Services\Integration\IntegrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use SimpleXMLElement;
use Tests\TestCase;

/**
 * Machine liveness must be judged against the provider's own cadence,
 * on ANY telemetry heard -- not a hard-coded 5 minutes against GPS only.
 *
 * Bell publishes every 900s and a stationary machine's Location
 * timestamp freezes for hours while its counters keep reporting. The
 * old 5-minute rule therefore declared the whole fleet offline within
 * minutes of every sync, which is why the Active/Idle/Maintenance
 * cards read 0/0/0 permanently (all 26 production machines sat at
 * 'offline' with minutes-fresh telemetry, 2026-08-27 audit).
 */
class MachineStatusLivenessTest extends TestCase
{
    use RefreshDatabase;

    private const FLEET_URL = 'https://b-fleet03.bellequipment.com:8080/Fleet/1';

    private const TOKEN_URL = 'https://sso.bellequipment.com/connect/token';

    private function fakeBell(): void
    {
        Http::fake([
            self::TOKEN_URL => Http::response([
                'access_token' => 'fake-access-token', 'token_type' => 'Bearer', 'expires_in' => 18000,
            ], 200),
            self::FLEET_URL => Http::response(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Fleet version="1" snapshotTime="2026-06-02T12:54:29Z">
  <Equipment Latitude="-26.0231" Longitude="28.9387" EngineRunning="true" TelemetryDate="2026-06-02T11:14:14Z">
    <EquipmentHeader OEMName="BELL" Model="B50E" EquipmentID="ASA B50E#9086" SerialNumber="9086"/>
  </Equipment>
</Fleet>
XML, 200),
        ]);
    }

    /** @return array{Team, Integration} */
    private function connectedBell(): array
    {
        $team = Team::factory()->create();
        $integration = Integration::factory()->forProvider('bell')->connected()->create([
            'team_id' => $team->id,
            'credentials' => ['username' => 'u', 'password' => 'p', 'client_secret' => 's'],
        ]);

        return [$team, $integration];
    }

    private function machine(Team $team, Integration $integration, array $attributes = []): Machine
    {
        return Machine::factory()->create(array_merge([
            'team_id' => $team->id,
            'integration_id' => $integration->id,
            'manufacturer' => 'bell', // syncMachine matches on this too
            'manufacturer_id' => 'ASA B50E#9086',
            'status' => 'active',
        ], $attributes));
    }

    private function runJob(Integration $integration): void
    {
        (new MachineStatusMonitoringJob($integration))->handle(app(IntegrationService::class));
    }

    public function test_a_stationary_machine_with_fresh_counters_is_not_declared_offline(): void
    {
        Event::fake([MachineOffline::class]);
        $this->fakeBell();
        [$team, $integration] = $this->connectedBell();
        // GPS timestamp frozen two hours ago -- but counters reported
        // ten minutes ago. The machine is alive.
        $machine = $this->machine($team, $integration, ['last_location_update' => now()->subHours(2)]);
        MachineMetric::factory()->create([
            'team_id' => $team->id, 'machine_id' => $machine->id,
            'recorded_at' => now()->subMinutes(10), 'created_at' => now()->subMinutes(10),
        ]);

        $this->runJob($integration);

        $this->assertSame('active', $machine->fresh()->status);
        Event::assertNotDispatched(MachineOffline::class);
    }

    public function test_a_machine_silent_beyond_twice_the_provider_cadence_goes_offline(): void
    {
        Event::fake([MachineOffline::class]);
        $this->fakeBell();
        [$team, $integration] = $this->connectedBell();
        // Bell's cadence is 900s; nothing heard for 40 minutes > 2x900.
        $machine = $this->machine($team, $integration, ['last_location_update' => now()->subMinutes(40)]);

        $this->runJob($integration);

        $this->assertSame('offline', $machine->fresh()->status);
        Event::assertDispatched(MachineOffline::class);
    }

    public function test_a_machine_heard_twenty_minutes_ago_is_within_bells_cadence(): void
    {
        // 20 minutes exceeds the old 5-minute rule but is well inside
        // 2x Bell's 900s interval -- this machine must stay active.
        Event::fake([MachineOffline::class]);
        $this->fakeBell();
        [$team, $integration] = $this->connectedBell();
        $machine = $this->machine($team, $integration, ['last_location_update' => now()->subMinutes(20)]);

        $this->runJob($integration);

        $this->assertSame('active', $machine->fresh()->status);
    }

    public function test_a_revived_machine_returns_as_idle_until_the_sync_restores_engine_truth(): void
    {
        // The snapshot's presence signal means "connected", not
        // "working" -- a machine coming back must not be invented as
        // active; the next sync writes the real engine state.
        Event::fake([MachineOffline::class]);
        $this->fakeBell();
        [$team, $integration] = $this->connectedBell();
        $machine = $this->machine($team, $integration, ['status' => 'offline', 'last_location_update' => now()->subHours(3)]);
        MachineMetric::factory()->create([
            'team_id' => $team->id, 'machine_id' => $machine->id,
            'recorded_at' => now()->subMinutes(5), 'created_at' => now()->subMinutes(5),
        ]);

        $this->runJob($integration);

        $this->assertSame('idle', $machine->fresh()->status);
    }

    public function test_the_timeout_sweep_spares_machines_whose_counters_still_report(): void
    {
        Event::fake([MachineOffline::class]);
        $this->fakeBell();
        [$team, $integration] = $this->connectedBell();
        // A snapshot-matched sibling keeps the job's status loop busy so
        // the sweep genuinely runs; the machine under test is not in the
        // snapshot at all -- only the sweep judges it.
        $this->machine($team, $integration, ['last_location_update' => now()->subMinutes(2)]);
        $machine = $this->machine($team, $integration, [
            'manufacturer_id' => 'ASA B45E#7712',
            'last_location_update' => now()->subHours(2),
        ]);
        MachineMetric::factory()->create([
            'team_id' => $team->id, 'machine_id' => $machine->id,
            'recorded_at' => now()->subMinutes(10), 'created_at' => now()->subMinutes(10),
        ]);

        $this->runJob($integration);

        $this->assertSame('active', $machine->fresh()->status);
    }

    public function test_the_timeout_sweep_still_catches_machines_gone_fully_silent(): void
    {
        Event::fake([MachineOffline::class]);
        $this->fakeBell();
        [$team, $integration] = $this->connectedBell();
        $machine = $this->machine($team, $integration, [
            'manufacturer_id' => 'ASA B45E#7712',
            'last_location_update' => now()->subHours(2),
        ]);

        $this->runJob($integration);

        $this->assertSame('offline', $machine->fresh()->status);
        Event::assertDispatched(MachineOffline::class);
    }

    // ---- the sync must not write Bell's 'unknown' over a real status ----

    public function test_sync_preserves_the_previous_status_when_bell_omits_engine_state(): void
    {
        [$team, $integration] = $this->connectedBell();
        $machine = $this->machine($team, $integration, ['status' => 'active']);

        // No EngineStatus section and no EngineRunning attribute.
        $node = new SimpleXMLElement(<<<'XML'
<Equipment>
  <EquipmentHeader OEMName="BELL" Model="B50E" EquipmentID="ASA B50E#9086" SerialNumber="9086"/>
  <Location datetime="2026-08-27T10:00:00Z"><Latitude>-26.1</Latitude><Longitude>28.0</Longitude></Location>
</Equipment>
XML);
        $data = (new BellFleetParser)->parseEquipmentNode($node);
        $this->assertSame('unknown', $data['status']);

        app(IntegrationService::class)->syncMachine($integration, $data);

        $this->assertSame('active', $machine->fresh()->status);
    }

    public function test_a_machine_first_seen_without_engine_state_is_created_idle_not_unknown(): void
    {
        [, $integration] = $this->connectedBell();

        $node = new SimpleXMLElement(<<<'XML'
<Equipment>
  <EquipmentHeader OEMName="BELL" Model="B50E" EquipmentID="ASA NEW#0001" SerialNumber="0001"/>
  <Location datetime="2026-08-27T10:00:00Z"><Latitude>-26.1</Latitude><Longitude>28.0</Longitude></Location>
</Equipment>
XML);
        app(IntegrationService::class)->syncMachine($integration, (new BellFleetParser)->parseEquipmentNode($node));

        $this->assertSame('idle', Machine::where('manufacturer_id', 'ASA NEW#0001')->first()->status);
    }

    // ---- the Fleet cards must account for every machine ----

    public function test_fleet_cards_show_where_an_offline_fleet_went(): void
    {
        // 26 offline machines used to render as an unexplained 0/0/0 --
        // the summary counted three buckets and hid the fourth.
        [$team, $integration] = $this->connectedBell();
        $this->machine($team, $integration, ['manufacturer_id' => 'M1', 'status' => 'active']);
        $this->machine($team, $integration, ['manufacturer_id' => 'M2', 'status' => 'offline']);
        $this->machine($team, $integration, ['manufacturer_id' => 'M3', 'status' => 'offline']);
        $user = User::factory()->create(['current_team_id' => $team->id]);

        $html = Livewire::actingAs($user)->test(Fleet::class)->html();

        $this->assertStringContainsString('Offline', $html);
        $this->assertStringContainsString('Not reporting', $html);
    }
}
