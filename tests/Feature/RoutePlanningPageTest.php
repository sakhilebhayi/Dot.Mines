<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The route-planning page had no feature test coverage before this file, so
 * nothing verified the Blade view actually rendered. Added while fixing a
 * <script> tag that was nested inside the page's <style> block (browsers
 * parse that as inert CSS text and never execute it) and re-theming the
 * page's chrome colors to brand tokens, to confirm the page still compiles
 * -- including the @json($this->id) inline script -- and renders.
 */
class RoutePlanningPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_away_from_route_planning(): void
    {
        $response = $this->get('/fleet/route-planning');

        $response->assertRedirect('/login');
    }

    public function test_authenticated_user_with_a_team_can_view_route_planning(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->update(['current_team_id' => $team->id]);

        $response = $this->actingAs($user)->get('/fleet/route-planning');

        $response->assertOk();
        $response->assertSee('Optimal Route Planning');
    }
}
