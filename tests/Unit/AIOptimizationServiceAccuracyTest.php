<?php

namespace Tests\Unit;

use App\Models\AIAgent;
use App\Models\Team;
use App\Services\AI\AIOptimizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression test: getOrCreateAgent() seeded every newly-created AIAgent
 * with a hardcoded 'accuracy_score' => 0.75 ("Initial score"). Nothing in
 * the app ever calls AIAgent::updateAccuracy() outside of
 * AIPredictiveAlert::recordAccuracy(), which itself is never called from
 * anywhere -- so that 0.75 was permanently displayed on the AI Analytics
 * page as "75.0% Average Accuracy" for every team, forever, regardless of
 * how the agent actually performed. Removed the seed value so it uses the
 * schema's honest default of 0, and the accuracy display now says "No
 * accuracy data yet" until predictions_made > 0 for real.
 */
class AIOptimizationServiceAccuracyTest extends TestCase
{
    use RefreshDatabase;

    public function test_newly_created_agents_do_not_get_a_fabricated_accuracy_score(): void
    {
        $team = Team::factory()->create();

        app(AIOptimizationService::class)->runComprehensiveAnalysis($team);

        $this->assertGreaterThan(0, AIAgent::count());
        $this->assertTrue(
            AIAgent::all()->every(fn (AIAgent $agent) => (float) $agent->accuracy_score === 0.0),
            'Expected every freshly-provisioned agent to start at the honest schema default of 0, not a fabricated seed value.'
        );
        $this->assertTrue(
            AIAgent::all()->every(fn (AIAgent $agent) => $agent->predictions_made === 0),
            'A freshly-provisioned agent should have no recorded prediction outcomes yet.'
        );
    }
}
