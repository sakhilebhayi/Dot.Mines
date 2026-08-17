<?php

namespace Tests\Unit;

use App\Models\AIAgent;
use App\Models\AIRecommendation;
use App\Models\Geofence;
use App\Models\GeofenceEntry;
use App\Models\MineArea;
use App\Models\Team;
use App\Services\AI\AIOptimizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * DispatchAdvisorAgent (app/Services/AI/DispatchAdvisorAgent.php) has its own
 * unit coverage in DispatchAdvisorAgentTest, but nothing exercised it through
 * the actual AIOptimizationService wiring -- the $agents constructor map,
 * getAgentTypeForCategory('dispatch'), and the AIRecommendation persistence
 * path every other agent already goes through. This confirms the 'dispatch'
 * category resolves the real, container-injected agent end to end, not just
 * that the class compiles and its own isolated unit tests pass.
 */
class AIOptimizationServiceDispatchAdvisorTest extends TestCase
{
    use RefreshDatabase;

    public function test_dispatch_category_runs_the_real_agent_and_persists_a_recommendation(): void
    {
        $team = Team::factory()->create();
        $mineArea = MineArea::create(['team_id' => $team->id, 'name' => 'Test Area', 'status' => 'active']);

        $busyPit = Geofence::factory()->active()->create([
            'team_id' => $team->id,
            'mine_area_id' => $mineArea->id,
            'name' => 'Busy Pit',
            'type' => 'pit',
        ]);
        Geofence::factory()->active()->create([
            'team_id' => $team->id,
            'mine_area_id' => $mineArea->id,
            'name' => 'Quiet Pit',
            'type' => 'pit',
        ]);

        GeofenceEntry::factory()->active()->count(3)->create([
            'team_id' => $team->id,
            'geofence_id' => $busyPit->id,
            'entry_time' => now()->subMinutes(15),
        ]);

        $recommendations = app(AIOptimizationService::class)
            ->getRecommendationsForCategory($team, 'dispatch');

        $this->assertCount(1, $recommendations);

        $persisted = AIRecommendation::first();
        $this->assertNotNull($persisted, 'Expected the dispatch recommendation to actually be persisted, not just returned in-memory.');
        $this->assertSame('dispatch', $persisted->category);
        $this->assertSame($team->id, $persisted->team_id);
        $this->assertStringContainsString('Quiet Pit', $persisted->proposed_action);
    }

    public function test_dispatch_agent_is_provisioned_with_its_registered_name_and_capabilities(): void
    {
        $team = Team::factory()->create();

        // No geofences at all -> zero recommendations, but
        // getRecommendationsForCategory() still calls getOrCreateAgent()
        // unconditionally, the same way the real dashboard flow provisions
        // every other agent's AIAgent row on first use.
        app(AIOptimizationService::class)->getRecommendationsForCategory($team, 'dispatch');

        $agent = AIAgent::where('type', AIAgent::TYPE_DISPATCH_ADVISOR)->first();

        $this->assertNotNull($agent, 'Expected requesting the dispatch category to provision an AIAgent row for it.');
        $this->assertSame('Dispatch Advisor', $agent->name);
        $this->assertSame(
            ['queue_monitoring', 'live_reroute_recommendation', 'dwell_time_analysis'],
            $agent->capabilities
        );
    }
}
