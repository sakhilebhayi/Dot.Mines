<?php

namespace Tests\Feature\Guardian;

use App\Models\Integration;
use App\Models\Machine;
use App\Models\MachineMetric;
use App\Models\Team;
use App\Services\Guardian\Checks\ProductionFreshnessCheck;
use App\Services\Guardian\Checks\ProviderDataFreshnessCheck;
use App\Services\Guardian\Checks\TelemetryIngestionCheck;
use App\Services\Guardian\MetricIngestProbe;
use App\Services\Integration\IntegrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Provider staleness vs pipeline failure.
 *
 * Since the §19 dedupe (a metric row is only written when the provider's
 * own recorded_at advances), max(created_at) measures how often the
 * PROVIDER publishes, not whether OUR pipeline works. On 2026-08-27 the
 * guardian reported "Telemetry ingestion has stopped" (sev2, runbook:
 * redeploy) about a pipeline that was provably alive and deduping
 * correctly while Bell's feed stalled for an hour. These tests pin the
 * distinction.
 */
class ProviderStalenessTest extends TestCase
{
    use RefreshDatabase;

    // ---- the ingest probe: proof the metric path ran ----

    public function test_syncing_a_new_metric_records_the_ingest_probe(): void
    {
        $team = Team::factory()->create();
        $integration = Integration::factory()->connected()->create(['team_id' => $team->id, 'provider' => 'bell']);

        $this->assertNull(MetricIngestProbe::ageSeconds());

        app(IntegrationService::class)->syncMachine($integration, [
            'external_id' => 'BELL-1',
            'metrics' => ['recorded_at' => now()->subMinutes(5)->toISOString()],
        ]);

        $this->assertNotNull(MetricIngestProbe::ageSeconds());
        $this->assertLessThan(60, MetricIngestProbe::ageSeconds());
    }

    public function test_a_deduped_metric_still_records_the_ingest_probe(): void
    {
        $team = Team::factory()->create();
        $integration = Integration::factory()->connected()->create(['team_id' => $team->id, 'provider' => 'bell']);
        $recordedAt = now()->subMinutes(5)->toISOString();
        $data = ['external_id' => 'BELL-1', 'metrics' => ['recorded_at' => $recordedAt]];

        $service = app(IntegrationService::class);
        $service->syncMachine($integration, $data);
        Cache::forget(MetricIngestProbe::CACHE_KEY);

        // Second sync: same provider timestamp, §19 skips the write --
        // but the pipeline still processed metrics, and must say so.
        $service->syncMachine($integration, $data);

        $this->assertSame(1, MachineMetric::query()->count());
        $this->assertNotNull(MetricIngestProbe::ageSeconds());
    }

    // ---- telemetry ingestion check: probe distinguishes the causes ----

    public function test_stale_ingest_with_a_live_pipeline_is_not_an_ingestion_fault(): void
    {
        $team = Team::factory()->create();
        Integration::factory()->connected()->create([
            'team_id' => $team->id, 'provider' => 'bell',
            'last_sync_at' => now()->subMinutes(3), 'last_sync_status' => 'success',
        ]);
        $machine = Machine::factory()->create(['team_id' => $team->id, 'status' => 'active']);
        MachineMetric::factory()->create([
            'team_id' => $team->id,
            'machine_id' => $machine->id,
            'recorded_at' => now()->subHours(2),
            'created_at' => now()->subHours(2), // stale: past 4x Bell's 900s interval
        ]);
        MetricIngestProbe::record(); // pipeline just processed a (deduped) snapshot

        $result = app(TelemetryIngestionCheck::class)->run();

        $this->assertSame('healthy', $result->status());
        $this->assertArrayHasKey('pipeline_probe_age_seconds', $result->toArray()['metrics']);
    }

    public function test_stale_ingest_with_a_stale_probe_is_still_critical(): void
    {
        $team = Team::factory()->create();
        Integration::factory()->connected()->create([
            'team_id' => $team->id, 'provider' => 'bell',
            'last_sync_at' => now()->subMinutes(3), 'last_sync_status' => 'success',
        ]);
        $machine = Machine::factory()->create(['team_id' => $team->id, 'status' => 'active']);
        MachineMetric::factory()->create([
            'team_id' => $team->id,
            'machine_id' => $machine->id,
            'recorded_at' => now()->subHours(2),
            'created_at' => now()->subHours(2),
        ]);
        Cache::put(MetricIngestProbe::CACHE_KEY, now()->subHours(2)->toISOString(), 3600);

        $this->assertSame('critical', app(TelemetryIngestionCheck::class)->run()->status());
    }

    // ---- provider data freshness: the new, correctly-named signal ----

    public function test_provider_freshness_is_unknown_without_connected_integrations(): void
    {
        $this->assertSame('unknown', app(ProviderDataFreshnessCheck::class)->run()->status());
    }

    public function test_provider_freshness_is_healthy_while_the_fleet_is_idle(): void
    {
        $team = Team::factory()->create();
        Integration::factory()->connected()->create([
            'team_id' => $team->id, 'provider' => 'bell',
            'last_sync_at' => now()->subMinutes(3), 'last_sync_status' => 'success',
        ]);
        $idle = Machine::factory()->create(['team_id' => $team->id, 'status' => 'idle']);
        MachineMetric::factory()->create([
            'team_id' => $team->id,
            'machine_id' => $idle->id,
            'recorded_at' => now()->subHours(3), // parked machines legitimately stop reporting
            'created_at' => now()->subHours(3),
        ]);

        $result = app(ProviderDataFreshnessCheck::class)->run();

        $this->assertSame('healthy', $result->status());
        $this->assertTrue($result->toArray()['metrics']['fleet_quiet']);
    }

    public function test_provider_freshness_is_healthy_when_readings_advance(): void
    {
        $team = Team::factory()->create();
        Integration::factory()->connected()->create([
            'team_id' => $team->id, 'provider' => 'bell',
            'last_sync_at' => now()->subMinutes(3), 'last_sync_status' => 'success',
        ]);
        $machine = Machine::factory()->create(['team_id' => $team->id, 'status' => 'active']);
        MachineMetric::factory()->create([
            'team_id' => $team->id,
            'machine_id' => $machine->id,
            'recorded_at' => now()->subMinutes(10),
            'created_at' => now()->subMinutes(10),
        ]);

        $this->assertSame('healthy', app(ProviderDataFreshnessCheck::class)->run()->status());
    }

    public function test_provider_freshness_warns_when_readings_stall_while_the_fleet_works(): void
    {
        $team = Team::factory()->create();
        Integration::factory()->connected()->create([
            'team_id' => $team->id, 'provider' => 'bell',
            'last_sync_at' => now()->subMinutes(3), 'last_sync_status' => 'success',
        ]);
        $machine = Machine::factory()->create(['team_id' => $team->id, 'status' => 'active']);
        MachineMetric::factory()->create([
            'team_id' => $team->id,
            'machine_id' => $machine->id,
            'recorded_at' => now()->subHours(2), // past 4x Bell's 900s interval
            'created_at' => now()->subHours(2),
        ]);

        $result = app(ProviderDataFreshnessCheck::class)->run();

        $this->assertSame('warning', $result->status());
        $this->assertStringContainsString('provider', strtolower($result->toArray()['message']));
        $this->assertGreaterThan(3600, $result->toArray()['metrics']['newest_reading_age_seconds']);
    }

    // ---- production freshness: starved input is not a production fault ----

    public function test_production_freshness_is_unknown_while_provider_telemetry_starves_it(): void
    {
        $team = Team::factory()->create();
        Integration::factory()->connected()->create([
            'team_id' => $team->id, 'provider' => 'bell',
            'capabilities' => ['fleet', 'production'],
            'last_sync_at' => now()->subMinutes(3), 'last_sync_status' => 'success',
        ]);
        $machine = Machine::factory()->create(['team_id' => $team->id, 'status' => 'active']);
        MachineMetric::factory()->create([
            'team_id' => $team->id,
            'machine_id' => $machine->id,
            'recorded_at' => now()->subHours(2), // provider stalled
            'created_at' => now()->subHours(2),
        ]);
        DB::table('production_records')->insert([
            'team_id' => $team->id,
            'machine_id' => $machine->id,
            'record_date' => now()->toDateString(),
            'shift' => 'day',
            'quantity_produced' => 100,
            'unit' => 'tonnes',
            'status' => 'completed',
            'created_at' => now()->subHours(3), // stale enough to warn on its own
            'updated_at' => now()->subHours(3),
        ]);

        $result = app(ProductionFreshnessCheck::class)->run();

        // Production cannot be judged while its input starves; the cause
        // is already surfaced by provider_data_freshness.
        $this->assertSame('unknown', $result->status());
    }

    // ---- the endpoint serves the new check ----

    public function test_health_endpoint_reports_provider_data_freshness(): void
    {
        config(['guardian.token' => 'test-guardian-token']);

        $response = $this->getJson('/guardian/health', [
            'Authorization' => 'Bearer test-guardian-token',
        ]);

        $response->assertOk();
        $this->assertArrayHasKey('provider_data_freshness', $response->json('checks'));
    }
}
