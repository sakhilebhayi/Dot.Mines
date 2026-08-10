<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The sidebar had zero responsive breakpoints at any viewport -- confirmed
 * live at 375px: it ate the full screen width at its desktop size (w-64/
 * w-20), leaving no room for page content, which wrapped one word per line.
 * There was no way to reach any other page on a phone.
 *
 * This can't exercise the actual Alpine/CustomEvent interaction (that needs
 * a real browser -- verified manually: hamburger opens the drawer, the X
 * button/backdrop-click/Escape all close it, desktop is pixel-identical to
 * before), but it locks in the structural markup the JS behavior depends on
 * so a future edit can't silently drop it.
 */
class MobileNavigationTest extends TestCase
{
    use RefreshDatabase;

    private function actingUser(): User
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->update(['current_team_id' => $team->id]);

        return $user;
    }

    public function test_sidebar_is_off_canvas_on_mobile_and_static_on_desktop(): void
    {
        $response = $this->actingAs($this->actingUser())->get('/dashboard');

        $response->assertOk();
        // Off-canvas by default (mobile), restored to normal flex flow and
        // always visible from md: up (desktop) -- the two states that used
        // to not exist at all.
        $response->assertSee('id="mobile-sidebar"', false);
        $response->assertSee('-translate-x-full', false);
        $response->assertSee('md:relative', false);
        $response->assertSee('md:translate-x-0', false);
    }

    public function test_navbar_has_a_mobile_only_hamburger_toggle(): void
    {
        $response = $this->actingAs($this->actingUser())->get('/dashboard');

        $response->assertOk();
        $response->assertSee('aria-controls="mobile-sidebar"', false);
        $response->assertSee('md:hidden', false);
        $response->assertSee('window.mobileNav.toggle()', false);
    }

    public function test_page_has_a_mobile_backdrop_that_closes_the_drawer(): void
    {
        $response = $this->actingAs($this->actingUser())->get('/dashboard');

        $response->assertOk();
        $response->assertSee('window.mobileNav.close()', false);
    }

    public function test_sidebar_has_a_mobile_only_close_button(): void
    {
        $response = $this->actingAs($this->actingUser())->get('/dashboard');

        $response->assertOk();
        $response->assertSee('Close navigation menu', false);
    }
}
