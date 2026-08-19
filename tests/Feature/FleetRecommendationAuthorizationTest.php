<?php

namespace Tests\Feature;

use App\Livewire\Fleet;
use App\Models\AIAgent;
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
        // The agent judges the day's deltas of the cumulative counters, so
        // two readings today: 8 engine hours, 6 of them idle (25% working).
        $machine = Machine::factory()->create(['team_id' => $team->id, 'status' => 'active']);
        MachineMetric::factory()->create([
            'team_id' => $team->id,
            'machine_id' => $machine->id,
            'operating_hours' => 100.0,
            'idle_hours' => 20.0,
            'recorded_at' => now()->startOfDay()->addHours(6),
        ]);
        MachineMetric::factory()->create([
            'team_id' => $team->id,
            'machine_id' => $machine->id,
            'operating_hours' => 108.0,
            'idle_hours' => 26.0,
            'recorded_at' => now()->startOfDay()->addHours(16),
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

    /**
     * Fixture for the outcome-tracking tests below: implement/reject is the
     * human verdict on the Fleet Optimizer's prediction and must feed the
     * agent accuracy metrics the AI Analytics page displays --
     * AIAgent::updateAccuracy() had no caller before, so
     * accuracy_score/predictions_made could never move.
     *
     * @return array{0: User, 1: Team}
     */
    private function managerWithRealRecommendation(): array
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);
        $manager = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($manager->id);
        TeamRoleProvisioner::assignRole($manager, $team, 'fleet_manager');

        // Same low-utilization fixture as the implement test above:
        // Fleet::render() recomputes lastAiRecommendations every request, so
        // the agent must genuinely produce one (25% working time today).
        $machine = Machine::factory()->create(['team_id' => $team->id, 'status' => 'active']);
        MachineMetric::factory()->create([
            'team_id' => $team->id,
            'machine_id' => $machine->id,
            'operating_hours' => 100.0,
            'idle_hours' => 20.0,
            'recorded_at' => now()->startOfDay()->addHours(6),
        ]);
        MachineMetric::factory()->create([
            'team_id' => $team->id,
            'machine_id' => $machine->id,
            'operating_hours' => 108.0,
            'idle_hours' => 26.0,
            'recorded_at' => now()->startOfDay()->addHours(16),
        ]);

        return [$manager, $team];
    }

    public function test_implementing_a_recommendation_records_a_successful_prediction(): void
    {
        [$manager] = $this->managerWithRealRecommendation();

        $agent = AIAgent::factory()->create([
            'type' => AIAgent::TYPE_FLEET_OPTIMIZER,
            'predictions_made' => 0,
            'successful_predictions' => 0,
            'accuracy_score' => 0,
        ]);

        Livewire::actingAs($manager)
            ->test(Fleet::class)
            ->call('implementRecommendation', 0);

        $agent->refresh();
        $this->assertSame(1, $agent->predictions_made);
        $this->assertSame(1, $agent->successful_predictions);
        $this->assertEqualsWithDelta(1.0, $agent->accuracy_score, 0.001);
    }

    public function test_rejecting_a_recommendation_records_an_unsuccessful_prediction(): void
    {
        [$manager] = $this->managerWithRealRecommendation();

        $agent = AIAgent::factory()->create([
            'type' => AIAgent::TYPE_FLEET_OPTIMIZER,
            'predictions_made' => 3,
            'successful_predictions' => 3,
            'accuracy_score' => 1.0,
        ]);

        Livewire::actingAs($manager)
            ->test(Fleet::class)
            ->set('pendingRecommendationIndex', 0)
            ->set('rejectReason', 'Machine already scheduled for service')
            ->call('confirmRejectRecommendation');

        $agent->refresh();
        $this->assertSame(4, $agent->predictions_made);
        $this->assertSame(3, $agent->successful_predictions);
        $this->assertEqualsWithDelta(0.75, $agent->accuracy_score, 0.001);
    }
}
