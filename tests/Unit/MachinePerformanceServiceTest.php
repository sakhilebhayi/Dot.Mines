<?php

namespace Tests\Unit;

use App\Models\Machine;
use App\Models\MachineMetric;
use App\Models\ProductionRecord;
use App\Models\Team;
use App\Services\MachinePerformanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Fleet page's Machine Performance section used to average columns no
 * integration ever writes (total_hours, fuel_consumption_rate,
 * payload_capacity_used) and fall back to a hardcoded neutral 50 for fuel
 * efficiency -- every machine scored an identical, meaningless "15%".
 * This service derives real daily metrics from the telemetry and
 * production data the Bell sync actually stores.
 */
class MachinePerformanceServiceTest extends TestCase
{
    use RefreshDatabase;

    private function metric(Team $team, Machine $machine, array $attributes): MachineMetric
    {
        return MachineMetric::create(array_merge([
            'team_id' => $team->id,
            'machine_id' => $machine->id,
        ], $attributes));
    }

    public function test_daily_deltas_utilisation_and_production_are_derived_from_real_data(): void
    {
        $team = Team::factory()->create();
        $machine = Machine::factory()->create(['team_id' => $team->id, 'name' => 'ADT 01']);

        // Two cumulative-counter readings today: engine 100 -> 108 hrs,
        // idle 20 -> 21.6 hrs, fuel consumed 1000 -> 1096 litres.
        $this->metric($team, $machine, [
            'recorded_at' => now()->startOfDay()->addHours(6),
            'operating_hours' => 100.0,
            'idle_hours' => 20.0,
            'fuel_level' => 80,
            'raw_data' => ['fuel_consumed_cumulative' => 1000.0, 'engine_running' => true],
        ]);
        $this->metric($team, $machine, [
            'recorded_at' => now()->startOfDay()->addHours(16),
            'operating_hours' => 108.0,
            'idle_hours' => 21.6,
            'fuel_level' => 62,
            'raw_data' => ['fuel_consumed_cumulative' => 1096.0, 'engine_running' => false],
        ]);

        ProductionRecord::create([
            'team_id' => $team->id,
            'machine_id' => $machine->id,
            'record_date' => now()->toDateString(),
            'shift' => 'continuous',
            'quantity_produced' => 750.0,
            'unit' => 'tonnes',
            'status' => 'completed',
            'metadata' => ['source' => 'telemetry', 'provider' => 'bell', 'loads' => 150, 'cycles' => 150],
        ]);

        $performance = app(MachinePerformanceService::class)->dailyPerformanceForTeam($team->id);

        $this->assertCount(1, $performance);
        $row = $performance[0];

        $this->assertSame($machine->id, $row['machine_id']);
        $this->assertEqualsWithDelta(108.0, $row['engine_hours_lifetime'], 0.01);
        $this->assertEqualsWithDelta(8.0, $row['operating_hours_today'], 0.01);
        $this->assertEqualsWithDelta(1.6, $row['idle_hours_today'], 0.01);
        // Utilisation = (8.0 - 1.6) / 8.0 = 80% of engine time working.
        $this->assertEqualsWithDelta(80.0, $row['utilisation_today'], 0.1);
        // 96 litres over 8 engine hours.
        $this->assertEqualsWithDelta(12.0, $row['fuel_lph_today'], 0.01);
        $this->assertSame(62.0, $row['fuel_level']);
        $this->assertFalse($row['engine_running']);

        $this->assertSame(150, $row['loads_today']);
        $this->assertSame(150, $row['cycles_today']);
        $this->assertEqualsWithDelta(750.0, $row['tonnes_today'], 0.01);
        $this->assertEqualsWithDelta(5.0, $row['avg_payload_tonnes'], 0.01);
        // Working time 6.4 hrs across 150 cycles = 153.6 s per cycle.
        $this->assertEqualsWithDelta(153.6, $row['avg_cycle_seconds'], 0.5);
    }

    public function test_a_single_reading_today_yields_insufficient_data_not_zeroes(): void
    {
        $team = Team::factory()->create();
        $machine = Machine::factory()->create(['team_id' => $team->id]);

        $this->metric($team, $machine, [
            'recorded_at' => now(),
            'operating_hours' => 5210.75,
            'idle_hours' => 2101.4,
            'fuel_level' => 38,
        ]);

        $row = app(MachinePerformanceService::class)->dailyPerformanceForTeam($team->id)[0];

        // Lifetime and latest-state values are real...
        $this->assertEqualsWithDelta(5210.75, $row['engine_hours_lifetime'], 0.01);
        $this->assertSame(38.0, $row['fuel_level']);
        // ...but one reading cannot support a delta or a rate.
        $this->assertNull($row['operating_hours_today']);
        $this->assertNull($row['idle_hours_today']);
        $this->assertNull($row['utilisation_today']);
        $this->assertNull($row['fuel_lph_today']);
        $this->assertNull($row['avg_cycle_seconds']);
    }

    public function test_counter_resets_never_produce_negative_or_fake_utilisation(): void
    {
        $team = Team::factory()->create();
        $machine = Machine::factory()->create(['team_id' => $team->id]);

        $this->metric($team, $machine, [
            'recorded_at' => now()->startOfDay()->addHours(6),
            'operating_hours' => 9000.0,
            'idle_hours' => 3000.0,
        ]);
        // ECU swap mid-day: counters reset below the morning reading.
        $this->metric($team, $machine, [
            'recorded_at' => now()->startOfDay()->addHours(16),
            'operating_hours' => 20.0,
            'idle_hours' => 4.0,
        ]);

        $row = app(MachinePerformanceService::class)->dailyPerformanceForTeam($team->id)[0];

        $this->assertNull($row['utilisation_today']);
        $this->assertNull($row['fuel_lph_today']);
    }

    public function test_machines_without_any_window_data_are_excluded(): void
    {
        $team = Team::factory()->create();
        Machine::factory()->create(['team_id' => $team->id, 'name' => 'Silent Machine']);

        $this->assertSame([], app(MachinePerformanceService::class)->dailyPerformanceForTeam($team->id));
    }

    public function test_loads_trend_compares_today_against_prior_days(): void
    {
        $team = Team::factory()->create();
        $machine = Machine::factory()->create(['team_id' => $team->id]);

        foreach ([2 => 100, 1 => 110] as $daysAgo => $loads) {
            ProductionRecord::create([
                'team_id' => $team->id,
                'machine_id' => $machine->id,
                'record_date' => now()->subDays($daysAgo)->toDateString(),
                'shift' => 'continuous',
                'quantity_produced' => $loads * 5,
                'unit' => 'tonnes',
                'status' => 'completed',
                'metadata' => ['source' => 'telemetry', 'loads' => $loads, 'cycles' => $loads],
            ]);
        }
        ProductionRecord::create([
            'team_id' => $team->id,
            'machine_id' => $machine->id,
            'record_date' => now()->toDateString(),
            'shift' => 'continuous',
            'quantity_produced' => 150,
            'unit' => 'tonnes',
            'status' => 'completed',
            'metadata' => ['source' => 'telemetry', 'loads' => 30, 'cycles' => 30],
        ]);

        $row = app(MachinePerformanceService::class)->dailyPerformanceForTeam($team->id)[0];

        // 30 loads today vs a 105-load prior-day average.
        $this->assertSame('declining', $row['loads_trend']);
    }

    public function test_trend_is_null_without_enough_history(): void
    {
        $team = Team::factory()->create();
        $machine = Machine::factory()->create(['team_id' => $team->id]);

        ProductionRecord::create([
            'team_id' => $team->id,
            'machine_id' => $machine->id,
            'record_date' => now()->toDateString(),
            'shift' => 'continuous',
            'quantity_produced' => 150,
            'unit' => 'tonnes',
            'status' => 'completed',
            'metadata' => ['source' => 'telemetry', 'loads' => 30, 'cycles' => 30],
        ]);

        $row = app(MachinePerformanceService::class)->dailyPerformanceForTeam($team->id)[0];

        $this->assertNull($row['loads_trend']);
        $this->assertNull($row['utilisation_trend']);
    }

    public function test_results_are_scoped_to_the_requested_team(): void
    {
        $team = Team::factory()->create();
        $otherTeam = Team::factory()->create();
        $foreign = Machine::factory()->create(['team_id' => $otherTeam->id]);

        $this->metric($otherTeam, $foreign, [
            'recorded_at' => now(),
            'operating_hours' => 100.0,
        ]);

        $this->assertSame([], app(MachinePerformanceService::class)->dailyPerformanceForTeam($team->id));
    }
}
