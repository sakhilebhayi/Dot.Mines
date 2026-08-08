<?php

namespace Tests\Feature;

use App\Models\AIRecommendation;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AIRecommendationPolicyTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(Team $team, string $roleName): User
    {
        $user = User::factory()->create(['current_team_id' => $team->id]);
        $role = Role::factory()->create(['team_id' => $team->id, 'name' => $roleName]);
        $user->roles()->attach($role->id);

        return $user;
    }

    public function test_owner_can_update_a_recommendation_on_their_team(): void
    {
        $team = Team::factory()->create();
        $owner = $this->userWithRole($team, 'owner');
        $recommendation = AIRecommendation::factory()->create(['team_id' => $team->id]);

        $this->assertTrue($owner->can('update', $recommendation));
    }

    public function test_admin_can_update_a_recommendation_on_their_team(): void
    {
        $team = Team::factory()->create();
        $admin = $this->userWithRole($team, 'admin');
        $recommendation = AIRecommendation::factory()->create(['team_id' => $team->id]);

        $this->assertTrue($admin->can('update', $recommendation));
    }

    public function test_a_same_team_member_with_no_role_or_permission_cannot_update(): void
    {
        $team = Team::factory()->create();
        $member = User::factory()->create(['current_team_id' => $team->id]);
        $recommendation = AIRecommendation::factory()->create(['team_id' => $team->id]);

        $this->assertFalse($member->can('update', $recommendation));
    }
}
