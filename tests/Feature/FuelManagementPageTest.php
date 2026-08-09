<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The /fuel page had no feature test coverage before this file, so nothing
 * verified the Blade view actually rendered. Added while re-theming
 * resources/views/livewire/fuel-management.blade.php (and the shared daisyui
 * "mines" theme in tailwind.config.js it relies on for card/stat/badge/alert
 * styling) to confirm the page still compiles and renders.
 */
class FuelManagementPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_away_from_fuel_management(): void
    {
        $response = $this->get('/fuel');

        $response->assertRedirect('/login');
    }

    public function test_authenticated_user_with_a_team_can_view_the_fuel_management_page(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->update(['current_team_id' => $team->id]);

        $response = $this->actingAs($user)->get('/fuel');

        $response->assertOk();
        $response->assertSee('Fuel Management');
    }
}
