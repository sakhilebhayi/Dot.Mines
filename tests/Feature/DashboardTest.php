<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_away_from_dashboard()
    {
        $response = $this->get('/dashboard');

        $response->assertRedirect('/login');
    }

    public function test_authenticated_user_with_a_team_can_view_the_dashboard()
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->update(['current_team_id' => $team->id]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        $response->assertSee($team->name);
    }

    /**
     * Regression test: EnsureTeamContext lets a teamless user (e.g. removed
     * from their last team) reach /dashboard with current_team_id null, so
     * Auth::user()->currentTeam is genuinely null here. This used to crash
     * with "Attempt to read property 'id' on null" in loadDashboardData();
     * it must now redirect to team creation instead.
     */
    public function test_authenticated_user_with_no_team_is_redirected_to_team_creation()
    {
        $user = User::factory()->create(['current_team_id' => null]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertRedirect(route('teams.create'));
    }
}
