<?php

namespace Tests\Unit;

use App\Models\ProductionRecord;
use App\Models\Team;
use App\Services\ProductionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Production page's "Total Loads" / "Total Cycles" tiles used to be a
 * proxy (record count / completed-record count) because every record was a
 * manual entry. Telemetry-derived records aggregate a whole day of loads
 * into one row, so the statistics must read the real loads/cycles out of
 * record metadata and only fall back to the per-record proxy for manual
 * records that don't carry them.
 */
class ProductionTelemetryStatsTest extends TestCase
{
    use RefreshDatabase;

    private function makeRecords(Team $team): void
    {
        // Telemetry-derived record: one row, 150 real loads.
        ProductionRecord::create([
            'team_id' => $team->id,
            'record_date' => now()->subDay(),
            'shift' => 'continuous',
            'quantity_produced' => 750,
            'unit' => 'tonnes',
            'status' => 'completed',
            'metadata' => ['source' => 'telemetry', 'provider' => 'bell', 'loads' => 150, 'cycles' => 150],
        ]);

        // Manual record without metadata: counts as one load / one cycle.
        ProductionRecord::create([
            'team_id' => $team->id,
            'record_date' => now()->subDay(),
            'shift' => 'day',
            'quantity_produced' => 300,
            'unit' => 'tonnes',
            'status' => 'completed',
        ]);
    }

    public function test_statistics_report_real_loads_and_cycles_from_metadata(): void
    {
        $team = Team::factory()->create();
        $this->makeRecords($team);

        $stats = app(ProductionService::class)->getProductionStatistics($team->id);

        $this->assertSame(151, $stats['total_loads']);
        $this->assertSame(151, $stats['total_cycles']);
        $this->assertEqualsWithDelta(1050.0, (float) $stats['total_produced'], 0.01);
    }

    public function test_trend_days_carry_real_loads(): void
    {
        $team = Team::factory()->create();
        $this->makeRecords($team);

        $trend = app(ProductionService::class)->getProductionTrend($team->id, 7);

        $this->assertCount(1, $trend);
        $this->assertSame(151, $trend->first()['loads']);
    }
}
