<?php

namespace Tests\Feature;

use App\Livewire\AIOptimizationDashboard;
use App\Models\AIRecommendation;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AIOptimizationDashboardApprovalTest extends TestCase
{
    use RefreshDatabase;

    private function ownerUser(Team $team): User
    {
        $user = User::factory()->create(['current_team_id' => $team->id]);
        $role = Role::factory()->create(['team_id' => $team->id, 'name' => 'owner']);
        $user->roles()->attach($role->id);

        return $user;
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
}
