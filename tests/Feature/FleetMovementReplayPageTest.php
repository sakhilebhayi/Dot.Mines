<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The /fleet/replay page had no feature test coverage before this file, so
 * nothing verified the Blade view actually rendered. Added while re-theming
 * resources/views/livewire/fleet-movement-replay.blade.php.
 */
class FleetMovementReplayPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_away_from_fleet_replay(): void
    {
        $response = $this->get('/fleet/replay');

        $response->assertRedirect('/login');
    }

    public function test_authenticated_user_with_a_team_can_view_fleet_replay(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->update(['current_team_id' => $team->id]);

        $response = $this->actingAs($user)->get('/fleet/replay');

        $response->assertOk();
        $response->assertSee('Fleet Movement Replay');
    }
}
