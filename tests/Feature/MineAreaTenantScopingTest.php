<?php

namespace Tests\Feature;

use App\Models\MineArea;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * MineArea does not use the HasTeamFilters global scope that most other
 * team-owned models (Machine, Geofence, Report, Route) use, so cross-team
 * access is enforced explicitly in App\Livewire\MineAreaDetail::mount().
 * This test guards that explicit check.
 */
class MineAreaTenantScopingTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_cannot_view_another_teams_mine_area()
    {
        $owner = User::factory()->create();
        $ownerTeam = Team::factory()->create(['user_id' => $owner->id]);
        $owner->update(['current_team_id' => $ownerTeam->id]);

        $otherUser = User::factory()->create();
        $otherTeam = Team::factory()->create(['user_id' => $otherUser->id]);
        $otherUser->update(['current_team_id' => $otherTeam->id]);

        $mineArea = MineArea::create([
            'team_id' => $ownerTeam->id,
            'name' => 'Owner Only Pit',
            'status' => 'active',
        ]);

        $response = $this->actingAs($otherUser)->get("/mine-areas/{$mineArea->id}");

        $response->assertForbidden();
    }

    public function test_user_can_view_their_own_teams_mine_area()
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->update(['current_team_id' => $team->id]);

        $mineArea = MineArea::create([
            'team_id' => $team->id,
            'name' => 'Home Pit',
            'status' => 'active',
        ]);

        $response = $this->actingAs($user)->get("/mine-areas/{$mineArea->id}");

        $response->assertOk();
        $response->assertSee('Home Pit');
    }
}
