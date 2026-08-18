<?php

namespace Tests\Unit;

use App\Models\Machine;
use App\Models\MachineMetric;
use App\Models\Team;
use App\Services\AI\FleetOptimizerAgent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression tests: analyzeUtilization() used to average operating_hours --
 * a cumulative lifetime counter (thousands of hours) -- and divide by 24,
 * so every machine with telemetry showed as "operating at 34976.666%
 * capacity", each with an identical hardcoded R50,000 "potential savings"
 * and 90% "confidence". The agent now grounds every figure in the daily
 * deltas MachinePerformanceService derives from real telemetry, skips
 * machines whose telemetry cannot support a utilisation figure, and no
 * longer emits fabricated savings or confidence numbers.
 */
class FleetOptimizerAgentTest extends TestCase
{
    use RefreshDatabase;

    private function metric(Team $team, Machine $machine, array $attributes): MachineMetric
    {
        return MachineMetric::create(array_merge([
            'team_id' => $team->id,
            'machine_id' => $machine->id,
        ], $attributes));
    }

    /**
     * Two readings today whose deltas give the requested engine and idle
     * hours -- the shape MachinePerformanceService needs for utilisation.
     * Readings span nearly the whole day so the accrued hours stay within
     * the service's wall-clock plausibility bound.
     */
    private function telemetryDay(Team $team, Machine $machine, float $operatingHours, float $idleHours, int $daysAgo = 0): void
    {
        $this->metric($team, $machine, [
            'recorded_at' => now()->subDays($daysAgo)->startOfDay()->addMinutes(30),
            'operating_hours' => 1000.0,
            'idle_hours' => 200.0,
        ]);
        $this->metric($team, $machine, [
            'recorded_at' => now()->subDays($daysAgo)->startOfDay()->addHours(23),
            'operating_hours' => 1000.0 + $operatingHours,
            'idle_hours' => 200.0 + $idleHours,
        ]);
    }

    public function test_cumulative_lifetime_counters_never_fabricate_overutilization(): void
    {
        $team = Team::factory()->create();
        $machine = Machine::factory()->create(['team_id' => $team->id, 'status' => 'active']);

        // The bug's trigger: a lifetime engine-hours counter reading. One
        // reading cannot support a daily delta, so no judgement is possible.
        $this->metric($team, $machine, [
            'recorded_at' => now(),
            'operating_hours' => 5246.5,
            'idle_hours' => 2101.4,
        ]);

        $result = app(FleetOptimizerAgent::class)->analyze($team);

        $this->assertSame([], $result['recommendations']);
        $this->assertSame([], $result['insights']);
    }

    public function test_low_utilisation_from_real_engine_time_yields_grounded_recommendation(): void
    {
        $team = Team::factory()->create();
        $machine = Machine::factory()->create(['team_id' => $team->id, 'name' => 'ADT 01', 'status' => 'active']);

        // 8 engine hours, 6 idle -> 25% of engine time spent working.
        $this->telemetryDay($team, $machine, 8.0, 6.0);

        $result = app(FleetOptimizerAgent::class)->analyze($team);

        $this->assertCount(1, $result['recommendations']);
        $recommendation = $result['recommendations'][0];

        $this->assertSame('Low Utilization: ADT 01', $recommendation['title']);
        $this->assertSame('high', $recommendation['priority']);
        $this->assertSame($machine->id, $recommendation['related_machine_id']);
        $this->assertEqualsWithDelta(25.0, $recommendation['data']['current_utilisation'], 0.1);
        $this->assertEqualsWithDelta(8.0, $recommendation['data']['operating_hours_today'], 0.01);
        $this->assertEqualsWithDelta(6.0, $recommendation['data']['idle_hours_today'], 0.01);

        // No fabricated numbers on any fleet recommendation.
        $this->assertArrayNotHasKey('confidence_score', $recommendation);
        $this->assertArrayNotHasKey('estimated_savings', $recommendation);
        $this->assertArrayNotHasKey('estimated_efficiency_gain', $recommendation);
    }

    public function test_moderately_low_utilisation_is_medium_priority(): void
    {
        $team = Team::factory()->create();
        $machine = Machine::factory()->create(['team_id' => $team->id, 'status' => 'active']);

        // 8 engine hours, 4.8 idle -> 40% working share.
        $this->telemetryDay($team, $machine, 8.0, 4.8);

        $result = app(FleetOptimizerAgent::class)->analyze($team);

        $this->assertCount(1, $result['recommendations']);
        $this->assertSame('medium', $result['recommendations'][0]['priority']);
    }

    public function test_healthy_utilisation_produces_no_recommendations(): void
    {
        $team = Team::factory()->create();
        $machine = Machine::factory()->create(['team_id' => $team->id, 'status' => 'active']);

        // 8 engine hours, 1.6 idle -> 80% working share.
        $this->telemetryDay($team, $machine, 8.0, 1.6);

        $result = app(FleetOptimizerAgent::class)->analyze($team);

        $this->assertSame([], $result['recommendations']);
        $this->assertSame([], $result['insights']);
    }

    public function test_a_sliver_of_engine_time_is_not_judged_for_utilisation(): void
    {
        $team = Team::factory()->create();
        $machine = Machine::factory()->create(['team_id' => $team->id, 'status' => 'active']);

        // Only 1 engine hour today (80% of it idle) -- far too little
        // runtime to conclude anything about the machine's allocation.
        $this->telemetryDay($team, $machine, 1.0, 0.8);

        $result = app(FleetOptimizerAgent::class)->analyze($team);

        $this->assertSame([], $result['recommendations']);
    }

    public function test_sustained_operation_triggers_stress_recommendation_and_insight(): void
    {
        $team = Team::factory()->create();
        $machine = Machine::factory()->create(['team_id' => $team->id, 'name' => 'EX 07', 'status' => 'active']);

        // 21 engine hours in the day with 1 idle -> no maintenance window.
        $this->telemetryDay($team, $machine, 21.0, 1.0);

        $result = app(FleetOptimizerAgent::class)->analyze($team);

        $this->assertCount(1, $result['recommendations']);
        $recommendation = $result['recommendations'][0];
        $this->assertSame('Sustained Operation: EX 07', $recommendation['title']);
        $this->assertSame('high', $recommendation['priority']);
        $this->assertEqualsWithDelta(21.0, $recommendation['data']['operating_hours_today'], 0.01);
        $this->assertArrayNotHasKey('confidence_score', $recommendation);
        $this->assertArrayNotHasKey('estimated_savings', $recommendation);

        $this->assertCount(1, $result['insights']);
        $insight = $result['insights'][0];
        $this->assertSame('High Machine Stress Detected', $insight['title']);
        $this->assertSame('warning', $insight['severity']);
        $this->assertEqualsWithDelta(21.0, $insight['data']['operating_hours_today'], 0.01);
    }

    public function test_declining_utilisation_trend_surfaces_an_insight(): void
    {
        $team = Team::factory()->create();
        $machine = Machine::factory()->create(['team_id' => $team->id, 'name' => 'ADT 02', 'status' => 'active']);

        // Two prior days at 80% working share, today at 25%.
        $this->telemetryDay($team, $machine, 8.0, 1.6, daysAgo: 2);
        $this->telemetryDay($team, $machine, 8.0, 1.6, daysAgo: 1);
        $this->telemetryDay($team, $machine, 8.0, 6.0);

        $result = app(FleetOptimizerAgent::class)->analyze($team);

        $declining = collect($result['insights'])
            ->first(fn (array $insight) => $insight['title'] === 'Utilisation Declining: ADT 02');

        $this->assertNotNull($declining, 'Expected a declining-utilisation insight.');
        $this->assertSame('warning', $declining['severity']);
        $this->assertSame($machine->id, $declining['data']['machine_id']);
    }

    public function test_idle_fleet_recommendation_reports_real_counts_without_invented_savings(): void
    {
        $team = Team::factory()->create();
        Machine::factory()->count(3)->create(['team_id' => $team->id, 'status' => 'active']);
        $idle = Machine::factory()->count(2)->create(['team_id' => $team->id, 'status' => 'idle']);

        $result = app(FleetOptimizerAgent::class)->analyze($team);

        $this->assertCount(1, $result['recommendations']);
        $recommendation = $result['recommendations'][0];

        $this->assertSame('High Idle Fleet Percentage', $recommendation['title']);
        $this->assertSame(2, $recommendation['data']['idle_machines']);
        $this->assertSame(5, $recommendation['data']['total_machines']);
        $this->assertEqualsWithDelta(40.0, $recommendation['data']['idle_percentage'], 0.01);
        $this->assertSame($idle->pluck('id')->all(), $recommendation['data']['machine_ids']);
        $this->assertArrayNotHasKey('confidence_score', $recommendation);
        $this->assertArrayNotHasKey('estimated_savings', $recommendation);
    }

    public function test_idle_fleet_below_threshold_is_not_flagged(): void
    {
        $team = Team::factory()->create();
        Machine::factory()->count(9)->create(['team_id' => $team->id, 'status' => 'active']);
        Machine::factory()->create(['team_id' => $team->id, 'status' => 'idle']);

        $result = app(FleetOptimizerAgent::class)->analyze($team);

        $this->assertSame([], $result['recommendations']);
    }
}
