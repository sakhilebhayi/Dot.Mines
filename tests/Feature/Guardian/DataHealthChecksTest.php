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
        MachineMetric::factory()->create([
            'team_id' => $team->id,
            'recorded_at' => now()->subMinutes(10),
            'created_at' => now()->subMinutes(10),
        ]);

        $this->assertSame('healthy', app(TelemetryIngestionCheck::class)->run()->status());
    }

    public function test_telemetry_check_goes_critical_when_ingestion_stopped(): void
    {
        $team = Team::factory()->create();
        Integration::factory()->connected()->create(['team_id' => $team->id, 'provider' => 'bell']);
        MachineMetric::factory()->create([
            'team_id' => $team->id,
            'recorded_at' => now()->subHours(3),
            'created_at' => now()->subHours(3),
        ]);

        $result = app(TelemetryIngestionCheck::class)->run();

        $this->assertSame('critical', $result->status());
        $this->assertGreaterThan(0, $result->toArray()['metrics']['newest_metric_age_seconds']);
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

    private function insertProductionRecord(Team $team, Carbon $updatedAt): void
    {
        $machine = Machine::factory()->create(['team_id' => $team->id]);

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
