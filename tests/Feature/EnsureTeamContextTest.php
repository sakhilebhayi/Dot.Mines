<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression test for EnsureTeamContext: a user who belongs to no team at all
 * (e.g. removed from their last team) used to reach every team-scoped page
 * and API endpoint with a null current_team_id and crash downstream with
 * "Attempt to read property ... on null" the moment that page touched
 * Auth::user()->currentTeam -- Dashboard::mount() and ReportController::
 * view2() each already guarded against this individually, but ~20 other
 * team-scoped routes (LiveMap, Alerts, ProductionDashboard, FuelManagement,
 * MaintenanceDashboard, the fleet/geofences/mine-areas/settings pages, every
 * /api/* endpoint, etc.) did not. Fixed centrally in the middleware instead
 * of patching each call site.
 */
class EnsureTeamContextTest extends TestCase
{
    use RefreshDatabase;

    public function test_teamless_user_is_redirected_to_team_creation_on_a_web_route(): void
    {
        $user = User::factory()->create(['current_team_id' => null]);

        $response = $this->actingAs($user)->get('/fleet');

        $response->assertRedirect(route('teams.create'));
    }

    public function test_teamless_user_gets_a_json_error_on_an_api_route(): void
    {
        $user = User::factory()->create(['current_team_id' => null]);

        $response = $this->actingAs($user)->getJson('/api/v1/machines');

        $response->assertStatus(409);
        $response->assertJson(['message' => 'No team context available. Please create or join a team.']);
    }

    public function test_user_with_a_team_can_still_reach_a_team_scoped_route(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->update(['current_team_id' => $team->id]);

        $response = $this->actingAs($user)->get('/fleet');

        $response->assertOk();
    }

    /**
     * The real bug: CreateNewUser::createTeam() attaches a new team via
     * teams.user_id (ownership) and never inserts a team_user pivot row, so
     * $user->teams() -- the fallback this middleware used to rely on
     * exclusively -- is empty for every freshly registered user. Their very
     * first authenticated request used to bounce them to teams.create
     * despite already owning a team from registration.
     */
    public function test_a_user_who_owns_a_team_but_has_no_pivot_membership_still_resolves_it(): void
    {
        $user = User::factory()->create(['current_team_id' => null]);
        $team = Team::factory()->create(['user_id' => $user->id, 'personal_team' => true]);

        $this->assertTrue($user->teams()->count() === 0, 'Precondition: no team_user pivot row exists.');

        $response = $this->actingAs($user)->get('/fleet');

        $response->assertOk();
        $this->assertSame($team->id, $user->fresh()->current_team_id);
    }
}
