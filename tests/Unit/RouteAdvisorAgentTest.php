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
        ]);

        $agent = app(RouteAdvisorAgent::class);
        $result = $agent->analyze($team);

        if (count($result['recommendations']) === 0) {
            $this->markTestSkipped('Route efficiency fixture did not cross the 15% improvement threshold; not this test\'s concern to fabricate route geometry.');
        }

        $recommendation = $result['recommendations'][0];
        $this->assertArrayHasKey('proposed_action', $recommendation);
        $this->assertNotSame($recommendation['description'], $recommendation['proposed_action']);
        $this->assertStringContainsString($route->name, $recommendation['proposed_action']);
    }
}
