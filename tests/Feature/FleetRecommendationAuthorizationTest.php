<?php

namespace Tests\Feature;

use App\Livewire\Fleet;
use App\Models\Machine;
use App\Models\MachineMetric;
use App\Models\Team;
use App\Models\User;
use App\Services\TeamRoleProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Regression test: Fleet::implementRecommendation()/confirmRejectRecommendation()
 * never checked authorization at all, unlike the equivalent actions on
 * AIOptimizationDashboard.php. These recommendations are computed fresh by
 * FleetOptimizerAgent (plain arrays, no persisted id), so they can't be
 * authorized through AIRecommendationPolicy the same way -- the fix checks
 * the same 'update_recommendations' permission (or team ownership) directly.
 */
class FleetRecommendationAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private array $fakeRecommendation = [
        'category' => 'fleet',
        'priority' => 'high',
        'title' => 'Low Utilization: Test Machine',
        'description' => 'Test.',
        'confidence_score' => 0.85,
        'related_machine_id' => null,
    ];

    public function test_viewer_cannot_implement_a_recommendation(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);
        $viewer = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($viewer->id);
        TeamRoleProvisioner::assignRole($viewer, $team, 'viewer');

        Livewire::actingAs($viewer)
            ->test(Fleet::class)
            ->set('lastAiRecommendations', [$this->fakeRecommendation])
            ->call('implementRecommendation', 0)
            ->assertStatus(403);

        $this->assertDatabaseCount('ai_recommendation_actions', 0);
    }

    public function test_fleet_manager_can_implement_a_recommendation(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);
        $manager = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($manager->id);
        TeamRoleProvisioner::assignRole($manager, $team, 'fleet_manager');

        // Give FleetOptimizerAgent a real, low-utilization machine so it
        // computes a genuine "Low Utilization" recommendation at render time
        // -- Fleet::render() recomputes lastAiRecommendations on every
        // request, so a directly ->set() value would just be overwritten.
        $machine = Machine::factory()->create(['team_id' => $team->id]);
        MachineMetric::factory()->create([
            'team_id' => $team->id,
            'machine_id' => $machine->id,
            'operating_hours' => 2,
            'recorded_at' => now()->subDay(),
        ]);

        Livewire::actingAs($manager)
            ->test(Fleet::class)
            ->call('implementRecommendation', 0);

        $this->assertDatabaseHas('ai_recommendation_actions', [
            'team_id' => $team->id,
            'status' => 'implemented',
        ]);
    }

    public function test_viewer_cannot_reject_a_recommendation(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);
        $viewer = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($viewer->id);
        TeamRoleProvisioner::assignRole($viewer, $team, 'viewer');

        Livewire::actingAs($viewer)
            ->test(Fleet::class)
            ->set('lastAiRecommendations', [$this->fakeRecommendation])
            ->set('pendingRecommendationIndex', 0)
            ->set('rejectReason', 'Not applicable right now')
            ->call('confirmRejectRecommendation')
            ->assertStatus(403);

        $this->assertDatabaseCount('ai_recommendation_actions', 0);
    }
}
