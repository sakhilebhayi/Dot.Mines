<?php

namespace Tests\Feature;

use App\Livewire\AIOptimizationDashboard;
use App\Models\AIRecommendation;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AIOptimizationDashboardApprovalTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The real team owner: Team::user_id, per TeamPolicy and every other
     * ownership check in this app -- there is no 'owner' row in the custom
     * roles table (TeamRoleProvisioner only ever provisions
     * admin/fleet_manager/operator/viewer), so a user can never actually
     * reach this state via an assigned Role in production.
     */
    private function ownerUser(Team $team): User
    {
        $owner = User::factory()->create(['current_team_id' => $team->id]);
        // user_id isn't mass-assignable on Team (by design), so update() would
        // silently no-op here -- set and save it directly.
        $team->user_id = $owner->id;
        $team->save();

        return $owner;
    }

    public function test_implementing_a_recommendation_writes_a_decision_log_row(): void
    {
        $team = Team::factory()->create();
        $owner = $this->ownerUser($team);
        $recommendation = AIRecommendation::factory()->create(['team_id' => $team->id]);

        Livewire::actingAs($owner)
            ->test(AIOptimizationDashboard::class)
            ->call('implementRecommendation', $recommendation->id);

        $this->assertDatabaseHas('ai_recommendation_actions', [
            'ai_recommendation_id' => $recommendation->id,
            'team_id' => $team->id,
            'status' => 'implemented',
            'actioned_by' => $owner->id,
        ]);
    }

    public function test_rejecting_without_a_reason_is_blocked_and_writes_no_decision_log_row(): void
    {
        $team = Team::factory()->create();
        $owner = $this->ownerUser($team);
        $recommendation = AIRecommendation::factory()->create(['team_id' => $team->id]);

        Livewire::actingAs($owner)
            ->test(AIOptimizationDashboard::class)
            ->call('rejectRecommendation', $recommendation->id, '');

        $this->assertDatabaseMissing('ai_recommendation_actions', [
            'ai_recommendation_id' => $recommendation->id,
        ]);
        $this->assertSame('pending', $recommendation->fresh()->status);
    }

    public function test_rejecting_with_a_reason_writes_a_decision_log_row_with_that_reason(): void
    {
        $team = Team::factory()->create();
        $owner = $this->ownerUser($team);
        $recommendation = AIRecommendation::factory()->create(['team_id' => $team->id]);

        Livewire::actingAs($owner)
            ->test(AIOptimizationDashboard::class)
            ->call('rejectRecommendation', $recommendation->id, 'Not applicable to our current fleet configuration.');

        $this->assertDatabaseHas('ai_recommendation_actions', [
            'ai_recommendation_id' => $recommendation->id,
            'status' => 'rejected',
            'reject_reason' => 'Not applicable to our current fleet configuration.',
            'actioned_by' => $owner->id,
        ]);
        $this->assertSame('rejected', $recommendation->fresh()->status);
    }

    /**
     * Regression test: proposed_action, data (Context), and impact_analysis
     * (Evidence) previously existed only in the database -- nothing in the
     * recommendation card rendered any of them. A user had to click Implement
     * or Reject, opening the confirm dialog, before ever seeing what the
     * proposed action even was. This asserts all three are visible on the
     * card itself, before any decision button is clicked.
     */
    public function test_recommendation_card_shows_proposed_action_context_and_evidence_before_any_decision(): void
    {
        $team = Team::factory()->create();
        $owner = $this->ownerUser($team);
        AIRecommendation::factory()->create([
            'team_id' => $team->id,
            'proposed_action' => 'Reroute Truck 7 via the optimized path to capture the savings above.',
            'data' => ['current_utilization' => 42.5, 'wasted_hours_per_day' => 8],
            'impact_analysis' => ['recommended_action' => 'Reassign or consider selling/renting out'],
        ]);

        $component = Livewire::actingAs($owner)
            ->test(AIOptimizationDashboard::class)
            ->set('activeTab', 'recommendations');

        $component->assertSee('Reroute Truck 7 via the optimized path to capture the savings above.');
        $component->assertSee('Current utilization');
        $component->assertSee('42.5');
        $component->assertSee('Wasted hours per day');
        $component->assertSee('Recommended action');
        $component->assertSee('Reassign or consider selling/renting out');
    }

    public function test_recommendation_card_omits_the_context_evidence_disclosure_when_neither_is_present(): void
    {
        $team = Team::factory()->create();
        $owner = $this->ownerUser($team);
        AIRecommendation::factory()->create([
            'team_id' => $team->id,
            'data' => [],
            'impact_analysis' => [],
        ]);

        $component = Livewire::actingAs($owner)
            ->test(AIOptimizationDashboard::class)
            ->set('activeTab', 'recommendations');

        $component->assertDontSee('View context & evidence');
    }
}
