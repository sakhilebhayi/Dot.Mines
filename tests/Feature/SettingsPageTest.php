<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The /settings page had no feature test coverage before this file, so
 * nothing verified the Blade view actually rendered through the real route
 * (tests/Feature/SettingsTeamAuthorizationTest.php exercises the Livewire
 * component in isolation, not the /settings HTTP route). Added while
 * re-theming resources/views/livewire/settings.blade.php off its isolated
 * slate-* palette.
 */
class SettingsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_away_from_settings(): void
    {
        $response = $this->get('/settings');

        $response->assertRedirect('/login');
    }

    public function test_authenticated_user_with_a_team_can_view_settings(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->update(['current_team_id' => $team->id]);

        $response = $this->actingAs($user)->get('/settings');

        $response->assertOk();
        $response->assertSee('General Settings');
    }
}
