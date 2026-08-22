<?php

namespace Tests\Feature;

use App\Models\Machine;
use App\Models\MachineMetric;
use App\Models\ProductionRecord;
use App\Models\Team;
use App\Services\ProductionReconciliationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Operational data program P5 (brief §18): machine-level, fleet-level and
 * stored numbers must reconcile, and when they don't the discrepancy is
 * reported with its size and likely cause -- never hidden.
 */
class ProductionReconciliationTest extends TestCase
{
    use RefreshDatabase;

    private Team $team;

    private Machine $machine;

    protected function setUp(): void
    {
        parent::setUp();

        $this->team = Team::factory()->create(['timezone' => 'Africa/Johannesburg']);
        $this->machine = Machine::factory()->create(['team_id' => $this->team->id]);
    }

    private function service(): ProductionReconciliationService
    {
        return app(ProductionReconciliationService::class);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function telemetryRecord(array $overrides = []): ProductionRecord
    {
        return ProductionRecord::create(array_merge([
            'team_id' => $this->team->id,
            'machine_id' => $this->machine->id,
            'record_date' => now('Africa/Johannesburg')->toDateString(),
            'shift' => 'continuous',
            'quantity_produced' => 958.7,
            'unit' => 'tonnes',
            'status' => 'completed',
            'metadata' => [
                'source' => 'telemetry',
                'loads' => 39,
                'payload_delta' => 958700,
                'payload_units' => 'kilogram',
                'cumulative_load_count_end' => 16500,
                'cumulative_payload_end' => 649000000,
            ],
        ], $overrides));
    }

    public function test_consistent_records_reconcile_cleanly(): void
    {
        $this->telemetryRecord();

        // Live counter equal to the stored close: zero skew.
        MachineMetric::factory()->create([
            'team_id' => $this->team->id,
            'machine_id' => $this->machine->id,
            'recorded_at' => now(),
            'raw_data' => ['load_count' => 16500, 'cumulative_payload' => 649000000, 'payload_units' => 'kilogram'],
        ]);
        ProductionRecord::create([
            'team_id' => $this->team->id,
            'machine_id' => $this->machine->id,
            'record_date' => now('Africa/Johannesburg')->subDay()->toDateString(),
            'shift' => 'continuous',
            'quantity_produced' => 500,
            'unit' => 'tonnes',
            'status' => 'completed',
            'metadata' => [
                'source' => 'telemetry',
                'cumulative_load_count_end' => 16461,
                'cumulative_payload_end' => 648041300,
                'payload_units' => 'kilogram',
            ],
        ]);

        $result = $this->service()->forDay($this->team);

        $states = collect($result['checks'])->pluck('state', 'label');
        $this->assertSame('healthy', $states['Record consistency']);
        $this->assertSame('healthy', $states['Machine linkage']);
        $this->assertSame('healthy', $states['Live vs stored']);
        $this->assertSame(39, $result['totals']['loads']);
        $this->assertEqualsWithDelta(958.7, $result['totals']['tonnes'], 0.01);
    }

    public function test_quantity_drifted_from_its_payload_delta_is_an_error(): void
    {
        // Someone rewrote quantity_produced without touching the delta it
        // was derived from.
        $this->telemetryRecord(['quantity_produced' => 1200.0]);

        $result = $this->service()->forDay($this->team);

        $consistency = collect($result['checks'])->firstWhere('label', 'Record consistency');
        $this->assertSame('error', $consistency['state']);
        $this->assertStringContainsString('disagree', $consistency['detail']);
    }

    public function test_large_live_versus_stored_skew_is_flagged_with_cause(): void
    {
        $this->telemetryRecord();
        // Yesterday close baseline.
        ProductionRecord::create([
            'team_id' => $this->team->id,
            'machine_id' => $this->machine->id,
            'record_date' => now('Africa/Johannesburg')->subDay()->toDateString(),
            'shift' => 'continuous',
            'quantity_produced' => 500,
            'unit' => 'tonnes',
            'status' => 'completed',
            'metadata' => [
                'source' => 'telemetry',
                'cumulative_load_count_end' => 16461,
                'cumulative_payload_end' => 648041300,
                'payload_units' => 'kilogram',
            ],
        ]);
        // Live counter far ahead of the stored record: 16560 - 16461 = 99
        // live loads vs 39 stored.
        MachineMetric::factory()->create([
            'team_id' => $this->team->id,
            'machine_id' => $this->machine->id,
            'recorded_at' => now(),
            'raw_data' => ['load_count' => 16560, 'cumulative_payload' => 650000000, 'payload_units' => 'kilogram'],
        ]);

        $result = $this->service()->forDay($this->team);

        $liveCheck = collect($result['checks'])->firstWhere('label', 'Live vs stored');
        $this->assertSame('warning', $liveCheck['state']);
        $this->assertStringContainsString('99 loads', $liveCheck['detail']);
        $this->assertStringContainsString('deep sync', $liveCheck['detail']);
    }

    public function test_records_pointing_at_another_teams_machine_are_reported(): void
    {
        // The FK stops true orphans, but nothing in the schema stops a
        // record whose machine belongs to a DIFFERENT team -- exactly the
        // cross-tenant drift the linkage check exists to surface.
        $foreign = Machine::factory()->create();
        $this->telemetryRecord(['machine_id' => $foreign->id]);

        $result = $this->service()->forDay($this->team);

        $linkage = collect($result['checks'])->firstWhere('label', 'Machine linkage');
        $this->assertSame('warning', $linkage['state']);
        $this->assertStringContainsString('no longer exist', $linkage['detail']);
    }
}
