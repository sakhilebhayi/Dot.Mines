<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * User::currentTeam() overrode HasTeams::currentTeam() to add a return type
 * and, in doing so, silently dropped the trait's lazy fallback to the
 * user's personal team when current_team_id is null. Any code path that
 * reaches a user before EnsureTeamContext middleware has run (or isn't
 * covered by it at all) got a hard null instead of their own team.
 */
class UserCurrentTeamTest extends TestCase
{
    use RefreshDatabase;

    public function test_accessing_current_team_falls_back_to_the_personal_team_and_persists_it(): void
    {
        $user = User::factory()->create(['current_team_id' => null]);
        $team = Team::factory()->create(['user_id' => $user->id, 'personal_team' => true]);

        $resolved = $user->currentTeam;

        $this->assertNotNull($resolved);
        $this->assertSame($team->id, $resolved->id);
        $this->assertSame($team->id, $user->fresh()->current_team_id);
    }

    public function test_a_user_with_no_team_at_all_resolves_to_null_without_erroring(): void
    {
        $user = User::factory()->create(['current_team_id' => null]);

        $this->assertNull($user->currentTeam);
    }
}
