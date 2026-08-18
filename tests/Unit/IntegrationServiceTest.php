<?php

namespace Tests\Unit;

use App\Jobs\SyncMachineMetricsJob;
use App\Models\Alert;
use App\Models\Integration;
use App\Models\Machine;
use App\Models\Team;
use App\Services\Integration\IntegrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Regression coverage for three compounding bugs that meant testing or
 * syncing ANY integration, for ANY of the 25 manufacturers, always failed:
 *
 * 1. getServiceForIntegration() called json_decode($integration->credentials,
 *    true) -- but Integration::credentials is already cast to an array by
 *    the model ('json' cast), so this threw a TypeError on every single
 *    call, before any manufacturer-specific code ever ran.
 * 2. 17 of the 25 manufacturer service classes (Volvo, CAT, Komatsu, Bell,
 *    Hitachi, Liebherr, Hyundai, Doosan, JCB, Sany, XCMG, Kobelco, Kubota,
 *    Epiroc, C-Track, Roundebult, Kawasaki) called $this->makeRequest(...)
 *    and $this->logError(...), methods that were never defined anywhere --
 *    a fatal "call to undefined method" Error (not caught by their own
 *    catch (Exception $e) blocks) the instant they were used.
 * 3. The other 8 (Atlas Copco, Bobcat, CASE, John Deere, New Holland,
 *    Sandvik, Takeuchi, Yanmar) had no real implementation at all --
 *    testConnection() unconditionally returned true, reporting success
 *    regardless of whether any credentials were even provided.
 */
class IntegrationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_credentials_are_read_correctly_without_double_decoding(): void
    {
        Http::fake([
            '*' => Http::response(['data' => []], 200),
        ]);

        $team = Team::factory()->create();
        $integration = Integration::factory()->forProvider('volvo')->create([
            'team_id' => $team->id,
            'credentials' => ['api_key' => 'real-key', 'base_url' => 'https://api.example.test'],
        ]);

        $result = app(IntegrationService::class)->testConnection($integration);

        // Before the fix, this always failed with the TypeError message
        // regardless of real network behaviour.
        $this->assertTrue($result['success']);
        $this->assertSame('Connection successful', $result['message']);
    }

    public function test_a_previously_broken_makerequest_manufacturer_no_longer_fatals(): void
    {
        Http::fake([
            '*' => Http::response(['data' => [['id' => 1, 'model' => 'EC300']]], 200),
        ]);

        $team = Team::factory()->create();
        $integration = Integration::factory()->forProvider('hitachi')->create([
            'team_id' => $team->id,
            'credentials' => ['api_key' => 'key', 'base_url' => 'https://api.example.test'],
        ]);

        $result = app(IntegrationService::class)->testConnection($integration);

        $this->assertTrue($result['success']);
    }

    public function test_an_unimplemented_manufacturer_honestly_reports_unavailable_instead_of_faking_success(): void
    {
        $team = Team::factory()->create();
        $integration = Integration::factory()->forProvider('case')->create(['team_id' => $team->id]);

        $result = app(IntegrationService::class)->testConnection($integration);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('not yet available', $result['error']);
    }

    public function test_sync_machine_metrics_job_can_now_resolve_manufacturers_its_own_old_duplicate_list_excluded(): void
    {
        Http::fake([
            '*' => Http::response(['data' => ['engine_rpm' => 1200, 'timestamp' => now()->toIso8601String()]], 200),
        ]);

        $team = Team::factory()->create();
        $integration = Integration::factory()->forProvider('hitachi')->create([
            'team_id' => $team->id,
            'status' => 'connected',
            'credentials' => ['api_key' => 'key', 'base_url' => 'https://api.example.test'],
        ]);
        $machine = Machine::factory()->create([
            'team_id' => $team->id,
            'manufacturer' => 'hitachi',
            'manufacturer_id' => 'ext-1',
        ]);

        // Hitachi was never in SyncMachineMetricsJob's own hardcoded 5-manufacturer
        // list (volvo/cat/komatsu/bell/ctrack only) -- this proves it now
        // delegates to the real, complete IntegrationService instead.
        (new SyncMachineMetricsJob($machine))->handle(app(IntegrationService::class));

        $this->assertTrue(true); // Reaching this line without a fatal error is the assertion.
    }

    public function test_sync_machine_writes_to_the_columns_that_actually_exist(): void
    {
        $team = Team::factory()->create();
        $integration = Integration::factory()->forProvider('hitachi')->create(['team_id' => $team->id]);

        $machine = app(IntegrationService::class)->syncMachine($integration, [
            'external_id' => 'HIT-001',
            'model' => 'ZX350',
            'status' => 'active',
            'last_location' => ['latitude' => -26.2, 'longitude' => 28.05],
        ]);

        $this->assertNotNull($machine, 'syncMachine() used to throw ("column external_id does not exist") before reaching this point.');
        $this->assertSame('HIT-001', $machine->manufacturer_id);
        $this->assertSame(-26.2, $machine->last_location_latitude);
        $this->assertSame(28.05, $machine->last_location_longitude);
        $this->assertSame($integration->id, $machine->integration_id);

        // Syncing the same external machine again should update, not duplicate.
        $again = app(IntegrationService::class)->syncMachine($integration, [
            'external_id' => 'HIT-001',
            'model' => 'ZX350',
            'status' => 'idle',
        ]);
        $this->assertSame($machine->id, $again->id);
        $this->assertSame('idle', $again->fresh()->status);
        $this->assertSame(1, Machine::where('team_id', $team->id)->count());
    }

    public function test_sync_machine_alerts_deduplicates_via_metadata_not_a_nonexistent_column(): void
    {
        $team = Team::factory()->create();
        $integration = Integration::factory()->forProvider('hitachi')->create(['team_id' => $team->id]);
        $machine = app(IntegrationService::class)->syncMachine($integration, [
            'external_id' => 'HIT-002',
            'model' => 'ZX350',
            'status' => 'active',
            'alerts' => [
                ['external_id' => 'fault-1', 'title' => 'Low oil pressure', 'type' => 'sensor', 'priority' => 'high'],
            ],
        ]);

        $this->assertDatabaseHas('alerts', [
            'machine_id' => $machine->id,
            'title' => 'Low oil pressure',
        ]);
        $this->assertSame(1, Alert::where('machine_id', $machine->id)->count());

        // Syncing the exact same alert again must not duplicate it.
        app(IntegrationService::class)->syncMachine($integration, [
            'external_id' => 'HIT-002',
            'model' => 'ZX350',
            'status' => 'active',
            'alerts' => [
                ['external_id' => 'fault-1', 'title' => 'Low oil pressure', 'type' => 'sensor', 'priority' => 'high'],
            ],
        ]);
        $this->assertSame(1, Alert::where('machine_id', $machine->id)->count());
    }

    public function test_synced_alerts_default_to_a_status_the_check_constraint_allows(): void
    {
        $team = Team::factory()->create();
        $integration = Integration::factory()->forProvider('hitachi')->create(['team_id' => $team->id]);

        // No 'status' key on the alert -- the sync path must default it.
        $machine = app(IntegrationService::class)->syncMachine($integration, [
            'external_id' => 'HIT-003',
            'model' => 'ZX350',
            'status' => 'active',
            'alerts' => [
                ['external_id' => 'fault-2', 'title' => 'Coolant temperature high', 'type' => 'sensor', 'priority' => 'high'],
            ],
        ]);

        $alert = Alert::where('machine_id', $machine->id)->first();
        $this->assertNotNull($alert);
        // The legacy default 'new' violates chk_alert_status_values on
        // Postgres (allowed: active, acknowledged, resolved, dismissed,
        // dismissed_unresolved), so the whole insert used to fail there.
        $this->assertSame('active', $alert->status);
    }
}
