<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The /integrations page had no feature test coverage before this file, so
 * nothing verified the Blade view actually rendered. Added while re-theming
 * resources/views/livewire/integration-manager.blade.php (which also fixed
 * a broken header layout: an info banner was crammed inside the
 * title/button flex row instead of below it).
 */
class IntegrationsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_away_from_integrations(): void
    {
        $response = $this->get('/integrations');

        $response->assertRedirect('/login');
    }

    public function test_authenticated_user_with_a_team_can_view_integrations(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->update(['current_team_id' => $team->id]);

        $response = $this->actingAs($user)->get('/integrations');

        $response->assertOk();
        $response->assertSee('Integration Setup Guide');
    }
}
