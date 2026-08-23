<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Refactor program R6: POST /api/user/team/{team_id} wrote the caller's
 * team id into users.current_team_id with no check of its own -- only the
 * EnsureTeamContext middleware's route-param validation stood between a
 * session and a foreign tenant's HasTeamFilters scope. The closure now
 * carries its own belongsToTeam() gate (defense in depth); these tests
 * freeze the property at the endpoint level so neither layer can be
 * dropped silently.
 *
 * Tokens are granted ['*'] so these assert TEAM-MEMBERSHIP authorization,
 * not token-ability scope (that is TokenAbilityEnforcementTest's job) --
 * a bare actingAs() token now has no abilities and would 403 on this POST
 * for the wrong reason.
 */
class ApiTeamSwitchAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_cannot_switch_to_a_team_they_do_not_belong_to(): void
    {
        $attacker = User::factory()->withPersonalTeam()->create();
        $victim = User::factory()->withPersonalTeam()->create();
        $originalTeamId = $attacker->current_team_id;

        Sanctum::actingAs($attacker, ['*']);

        $response = $this->postJson('/api/user/team/'.$victim->currentTeam->id);

        $response->assertForbidden();
        $this->assertSame($originalTeamId, $attacker->fresh()->current_team_id);
    }

    public function test_user_can_switch_to_a_team_they_belong_to(): void
    {
        $user = User::factory()->withPersonalTeam()->create();

        Sanctum::actingAs($user, ['*']);

        $response = $this->postJson('/api/user/team/'.$user->currentTeam->id);

        $response->assertOk();
        $this->assertSame($user->currentTeam->id, $user->fresh()->current_team_id);
    }

    public function test_switching_to_a_nonexistent_team_is_rejected_without_a_write(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $originalTeamId = $user->current_team_id;

        Sanctum::actingAs($user, ['*']);

        // EnsureTeamContext validates the route's team_id before the closure
        // runs, so an unknown team is a 403 from the middleware layer.
        $response = $this->postJson('/api/user/team/999999');

        $response->assertForbidden();
        $this->assertSame($originalTeamId, $user->fresh()->current_team_id);
    }
}
