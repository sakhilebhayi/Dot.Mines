<?php

namespace Tests\Feature\Guardian;

use App\Models\Integration;
use App\Models\Machine;
use App\Models\MachineMetric;
use App\Models\Team;
use App\Services\Guardian\Checks\IntegrationSyncCheck;
use App\Services\Guardian\Checks\ProductionFreshnessCheck;
use App\Services\Guardian\Checks\TelemetryIngestionCheck;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DataHealthChecksTest extends TestCase
{
    use RefreshDatabase;

    public function test_integration_sync_check_is_unknown_without_connected_integrations(): void
    {
        Integration::factory()->disconnected()->create();

        $this->assertSame('unknown', app(IntegrationSyncCheck::class)->run()->status());
    }

    public function test_integration_sync_check_is_healthy_when_syncs_are_recent(): void
    {
        Integration::factory()->connected()->create([
            'provider' => 'bell',
            'last_sync_at' => now()->subMinutes(5),
        ]);

        $result = app(IntegrationSyncCheck::class)->run();

        $this->assertSame('healthy', $result->status());
        $this->assertCount(1, $result->toArray()['metrics']['integrations']);
    }

    public function test_integration_sync_check_warns_when_a_sync_is_overdue(): void
    {
        // Bell's interval is 900s; 2x-4x overdue is a warning.
        Integration::factory()->connected()->create([
            'provider' => 'bell',
            'last_sync_at' => now()->subSeconds(2000),
        ]);

        $this->assertSame('warning', app(IntegrationSyncCheck::class)->run()->status());
    }

    public function test_integration_sync_check_goes_critical_when_a_sync_has_stopped(): void
    {
        Integration::factory()->connected()->create([
            'provider' => 'bell',
            'last_sync_at' => now()->subSeconds(4000),
        ]);

        $result = app(IntegrationSyncCheck::class)->run();

        $this->assertSame('critical', $result->status());
        $this->assertSame('critical', $result->toArray()['metrics']['integrations'][0]['status']);
    }

    public function test_integration_sync_check_flags_failed_streams(): void
    {
        Integration::factory()->connected()->create([
            'provider' => 'bell',
            'last_sync_at' => now()->subMinutes(2),
            'sync_streams' => [
                'fleet' => ['status' => 'success', 'last_synced_at' => now()->toISOString(), 'records' => 4],
                'production' => ['status' => 'failed', 'last_synced_at' => now()->subHour()->toISOString(), 'records' => 0],
            ],
        ]);

        $result = app(IntegrationSyncCheck::class)->run();

        $this->assertSame('warning', $result->status());
        $this->assertContains('production', $result->toArray()['metrics']['integrations'][0]['failed_streams']);
    }

    public function test_telemetry_check_is_unknown_without_connected_integrations(): void
    {
        $this->assertSame('unknown', app(TelemetryIngestionCheck::class)->run()->status());
    }

    public function test_telemetry_check_is_healthy_with_recent_metrics(): void
    {
        $team = Team::factory()->create();
        Integration::factory()->connected()->create(['team_id' => $team->id, 'provider' => 'bell']);
        $machine = Machine::factory()->create(['team_id' => $team->id, 'status' => 'active']);
        MachineMetric::factory()->create([
            'team_id' => $team->id,
            'machine_id' => $machine->id,
            'recorded_at' => now()->subMinutes(10),
            'created_at' => now()->subMinutes(10),
        ]);

        $this->assertSame('healthy', app(TelemetryIngestionCheck::class)->run()->status());
    }

    public function test_telemetry_check_measures_ingestion_not_how_busy_the_fleet_is(): void
    {
        // A machine parked overnight legitimately has an old reading. That
        // is fleet activity, not platform health -- and judging ingestion
        // by the provider's own reading timestamp reported CRITICAL on
        // production (2026-08-26) one minute after 26 rows landed cleanly.
        $team = Team::factory()->create();
        Integration::factory()->connected()->create(['team_id' => $team->id, 'provider' => 'bell']);
        $machine = Machine::factory()->create(['team_id' => $team->id, 'status' => 'active']);
        MachineMetric::factory()->create([
            'team_id' => $team->id,
            'machine_id' => $machine->id,
            'recorded_at' => now()->subHours(3),   // machine last reported 3h ago
            'created_at' => now()->subMinute(),     // but we ingested it a minute ago
        ]);

        $result = app(TelemetryIngestionCheck::class)->run();

        $this->assertSame('healthy', $result->status());
        $this->assertLessThan(120, $result->toArray()['metrics']['newest_ingest_age_seconds']);
        $this->assertGreaterThan(3000, $result->toArray()['metrics']['newest_reading_age_seconds']);
    }

    public function test_telemetry_check_goes_critical_when_ingestion_stopped(): void
    {
        $team = Team::factory()->create();
        Integration::factory()->connected()->create(['team_id' => $team->id, 'provider' => 'bell']);
        // Machines ARE working -- which is what makes silence a fault.
        $machine = Machine::factory()->create(['team_id' => $team->id, 'status' => 'active']);
        MachineMetric::factory()->create([
            'team_id' => $team->id,
            'machine_id' => $machine->id,
            'recorded_at' => now()->subHours(3),
            'created_at' => now()->subHours(3),
        ]);

        $result = app(TelemetryIngestionCheck::class)->run();

        $this->assertSame('critical', $result->status());
        $this->assertGreaterThan(0, $result->toArray()['metrics']['newest_metric_age_seconds']);
    }

    public function test_telemetry_is_not_faulted_while_the_whole_fleet_is_idle(): void
    {
        // A parked fleet produces no readings. That is the machines resting,
        // not the platform failing -- and reporting it as a fault every
        // night trains people to ignore the one night it matters.
        $team = Team::factory()->create();
        Integration::factory()->connected()->create([
            'team_id' => $team->id, 'provider' => 'bell',
            'last_sync_at' => now()->subMinutes(3), 'last_sync_status' => 'success',
        ]);
        $idle = Machine::factory()->create(['team_id' => $team->id, 'status' => 'idle']);
        Machine::factory()->create(['team_id' => $team->id, 'status' => 'offline']);
        MachineMetric::factory()->create([
            'team_id' => $team->id,
            'machine_id' => $idle->id, // MachineFactory picks a RANDOM status; pin it.
            'recorded_at' => now()->subHours(3),
            'created_at' => now()->subHours(3),
        ]);

        $result = app(TelemetryIngestionCheck::class)->run();

        $this->assertSame('healthy', $result->status());
        $this->assertStringContainsString('idle', $result->toArray()['message']);
        $this->assertTrue($result->toArray()['metrics']['fleet_quiet']);
    }

    public function test_telemetry_is_still_faulted_when_a_machine_is_running(): void
    {
        // One machine working means readings are expected. Silence now is a
        // real signal, and the quiet-hours allowance must not swallow it.
        $team = Team::factory()->create();
        Integration::factory()->connected()->create([
            'team_id' => $team->id, 'provider' => 'bell',
            'last_sync_at' => now()->subMinutes(3), 'last_sync_status' => 'success',
        ]);
        $active = Machine::factory()->create(['team_id' => $team->id, 'status' => 'active']);
        MachineMetric::factory()->create([
            'team_id' => $team->id,
            'machine_id' => $active->id,
            'recorded_at' => now()->subHours(3),
            'created_at' => now()->subHours(3),
        ]);

        $this->assertSame('critical', app(TelemetryIngestionCheck::class)->run()->status());
    }

    public function test_an_idle_fleet_does_not_excuse_a_failing_sync(): void
    {
        // The dangerous case: a broken sync leaves machines looking idle
        // because nothing is updating them. Quiet hours must never be able
        // to explain away a sync that is actually failing.
        $team = Team::factory()->create();
        Integration::factory()->connected()->create([
            'team_id' => $team->id, 'provider' => 'bell',
            'last_sync_at' => now()->subHours(2), 'last_sync_status' => 'failed',
        ]);
        $idle = Machine::factory()->create(['team_id' => $team->id, 'status' => 'idle']);
        MachineMetric::factory()->create([
            'team_id' => $team->id,
            'machine_id' => $idle->id,
            'recorded_at' => now()->subHours(3),
            'created_at' => now()->subHours(3),
        ]);

        $this->assertSame('critical', app(TelemetryIngestionCheck::class)->run()->status());
    }

    public function test_production_freshness_is_not_faulted_while_the_fleet_is_idle(): void
    {
        $team = Team::factory()->create();
        Integration::factory()->connected()->create([
            'team_id' => $team->id,
            'capabilities' => ['fleet', 'production'],
            'last_sync_at' => now()->subMinutes(3), 'last_sync_status' => 'success',
        ]);
        $this->insertProductionRecord($team, updatedAt: now()->subHours(8), machineStatus: 'idle');

        $result = app(ProductionFreshnessCheck::class)->run();

        $this->assertSame('healthy', $result->status());
        $this->assertTrue($result->toArray()['metrics']['fleet_quiet']);
    }

    public function test_production_check_is_unknown_without_production_capable_integrations(): void
    {
        Integration::factory()->connected()->create(['capabilities' => ['fleet']]);

        $this->assertSame('unknown', app(ProductionFreshnessCheck::class)->run()->status());
    }

    public function test_production_check_is_healthy_with_recent_production_rows(): void
    {
        $team = Team::factory()->create();
        Integration::factory()->connected()->create([
            'team_id' => $team->id,
            'capabilities' => ['fleet', 'production'],
        ]);
        $this->insertProductionRecord($team, updatedAt: now()->subMinutes(30));

        $this->assertSame('healthy', app(ProductionFreshnessCheck::class)->run()->status());
    }

    public function test_production_check_goes_critical_when_production_data_froze(): void
    {
        $team = Team::factory()->create();
        Integration::factory()->connected()->create([
            'team_id' => $team->id,
            'capabilities' => ['fleet', 'production'],
        ]);
        $this->insertProductionRecord($team, updatedAt: now()->subHours(8));

        $this->assertSame('critical', app(ProductionFreshnessCheck::class)->run()->status());
    }

    private function insertProductionRecord(Team $team, Carbon $updatedAt, string $machineStatus = 'active'): void
    {
        // Stated, not left to chance: MachineFactory randomises status, and
        // whether a machine is running now decides whether frozen
        // production is a fault or just a quiet shift.
        $machine = Machine::factory()->create(['team_id' => $team->id, 'status' => $machineStatus]);

        DB::table('production_records')->insert([
            'team_id' => $team->id,
            'machine_id' => $machine->id,
            'record_date' => now()->toDateString(),
            'shift' => 'day',
            'quantity_produced' => 100,
            'unit' => 'tonnes',
            'status' => 'completed',
            'created_at' => $updatedAt,
            'updated_at' => $updatedAt,
        ]);
    }
}
