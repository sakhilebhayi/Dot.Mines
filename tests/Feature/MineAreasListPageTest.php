<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The /mine-areas list page had no feature test coverage before this file
 * (tests/Feature/MineAreaTenantScopingTest.php only covers the detail page,
 * /mine-areas/{id}). Added while re-theming
 * resources/views/livewire/mine-area-manager.blade.php -- including fixing a
 * dark-on-dark contrast risk where several form inputs paired bg-gray-700
 * with an explicit text-gray-900 class alongside a dark:text-[var(--stone)]
 * override of uncertain cascade precedence -- to confirm the page still
 * compiles and renders.
 */
class MineAreasListPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_away_from_mine_areas(): void
    {
        $response = $this->get('/mine-areas');

        $response->assertRedirect('/login');
    }

    public function test_authenticated_user_with_a_team_can_view_the_mine_areas_list(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->update(['current_team_id' => $team->id]);

        $response = $this->actingAs($user)->get('/mine-areas');

        $response->assertOk();
        $response->assertSee('Mine Areas');
    }
}
