<?php

namespace Tests\Unit;

use App\Models\Machine;
use App\Models\MachineHealthStatus;
use App\Models\Team;
use App\Services\AI\MaintenancePredictorAgent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression test: analyzeHealthTrend() computed its "degradation rate" via
 * rand(3, 8) -- a literal random number, regenerated on every call -- with a
 * comment admitting "In production, calculate from historical data". That
 * fabricated rate then fed a specific-sounding "declining X% per week,
 * critical by [date]" recommendation. There is no historical time series of
 * overall_health_score in the schema (machine_health_status has a unique
 * constraint on machine_id -- only the current score is ever stored), so a
 * truthful trend genuinely can't be computed yet. The feature was removed
 * rather than left fabricating a number.
 */
class MaintenancePredictorAgentTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_low_health_machine_never_produces_a_fabricated_degrading_health_pattern_recommendation(): void
    {
        $team = Team::factory()->create();
        $machine = Machine::factory()->create(['team_id' => $team->id]);

        MachineHealthStatus::create([
            'team_id' => $team->id,
            'machine_id' => $machine->id,
            'overall_health_score' => 50, // below the old is_degrading < 75 threshold
            'health_status' => 'poor',
        ]);

        $agent = app(MaintenancePredictorAgent::class);
        $result = $agent->analyze($team);

        $titles = collect($result['recommendations'])->pluck('title');

        $this->assertFalse(
            $titles->contains(fn ($title) => str_starts_with($title, 'Degrading Health Pattern')),
            'Expected no fabricated degradation-rate recommendation.'
        );
    }
}
