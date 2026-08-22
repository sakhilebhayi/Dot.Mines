<?php

namespace Tests\Unit;

use App\Models\Route;
use App\Models\Team;
use App\Services\AI\RouteAdvisorAgent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RouteAdvisorAgentTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_recommendation_with_improvement_possible_includes_a_distinct_proposed_action(): void
    {
        $team = Team::factory()->create();
        $route = Route::factory()->create([
            'team_id' => $team->id,
            'status' => 'active',
            'start_latitude' => -26.2041,
            'start_longitude' => 28.0473,
            'end_latitude' => -26.1052,
            'end_longitude' => 28.0567,
            // The direct distance for these coordinates is ~11 km; the agent
            // recommends only when the detour exceeds 25% of direct (>15%
            // improvement after the 10% grace). The factory randomises
            // total_distance 5-100 km, which made this test skip whenever
            // the draw landed under ~13.8 km -- pin a distance that always
            // crosses the threshold instead.
            'total_distance' => 60.0,
        ]);

        $agent = app(RouteAdvisorAgent::class);
        $result = $agent->analyze($team);

        $this->assertNotEmpty(
            $result['recommendations'],
            'A 60 km route over an 11 km direct path must always cross the 15% improvement threshold.',
        );

        $recommendation = $result['recommendations'][0];
        $this->assertArrayHasKey('proposed_action', $recommendation);
        $this->assertNotSame($recommendation['description'], $recommendation['proposed_action']);
        $this->assertStringContainsString($route->name, $recommendation['proposed_action']);
    }
}
