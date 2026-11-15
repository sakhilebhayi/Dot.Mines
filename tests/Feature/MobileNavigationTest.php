<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The sidebar had zero responsive breakpoints at any viewport (it ate the full screen width at 375px), then a hand-written
 * Alpine/CustomEvent drawer with its own backdrop in each of two duplicated layouts. R-52 replaced all of it with the shared
 * dot-ui header's menu button in "adopt" mode: dot-nav.js opens the platform's own sidebar, draws the scrim on <body>, locks the
 * page, traps focus and closes on Escape, a scrim tap, the close button and a link tap.
 *
 * This cannot exercise the interaction (that needs a real browser: the r52 harness's mobile-nav check does, at nine widths); it
 * pins the markup and the CSS rules the behaviour depends on, on both layouts, so an edit cannot silently drop them.
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

    public function test_the_menu_button_opens_the_sidebar_in_adopt_mode(): void
    {
        $html = (string) $this->actingAs($this->actingUser())->get('/dashboard')->assertOk()->getContent();

        $this->assertStringContainsString('class="dh dh--nonav dh--adopt"', $html);
        $this->assertStringContainsString('data-dh-menu-button', $html);
        $this->assertStringContainsString('aria-controls="mines-sidebar"', $html);
        $this->assertStringContainsString('aria-expanded="false"', $html);
        $this->assertMatchesRegularExpression('#<aside [^>]*id="mines-sidebar"#', $html);
    }

    public function test_the_sidebar_has_a_close_button_and_no_second_drawer_or_backdrop(): void
    {
        foreach (['/dashboard', '/alerts'] as $path) {
            $html = (string) $this->actingAs($this->actingUser())->get($path)->assertOk()->getContent();

            $this->assertStringContainsString('data-dh-close', $html, $path);
            $this->assertStringContainsString('aria-label="Close navigation"', $html, $path);
            $this->assertStringNotContainsString('bg-black/60 z-[45]', $html, "$path: the hand-written backdrop is gone, dot-ui draws the scrim on body");
            $this->assertStringNotContainsString('mobile-nav-changed', $html, $path);
        }
    }

    /**
     * The three requirements of dot-ui's sidebar-shell recipe (HEADER.md): the open sidebar sits above the scrim, a closed one
     * is out of the tab order (a delayed visibility), and on a desktop it sits under the header instead of sliding under it.
     */
    public function test_the_stylesheet_carries_the_off_canvas_and_the_desktop_rules(): void
    {
        $css = (string) file_get_contents(public_path('dot-ui-host.css'));

        $this->assertStringContainsString('@media (max-width: 860px)', $css, "the header's own breakpoint");
        $this->assertMatchesRegularExpression('#\.mines-sidebar\s*\{[^}]*transform: translateX\(-100%\);[^}]*visibility: hidden;[^}]*transition: transform 0\.22s ease, visibility 0s linear 0\.22s#s', $css);
        $this->assertStringContainsString('.mines-sidebar[data-dh-open] { transform: none; visibility: visible; transition-delay: 0s; }', $css);
        $this->assertStringContainsString('top: var(--dot-header-offset, 56px);', $css, 'sticky under the header on a desktop');
        $this->assertStringContainsString('z-index: 125;', $css, 'above dot-ui\'s scrim (120)');
    }

    public function test_the_rail_choice_is_applied_before_first_paint_and_remembered(): void
    {
        $html = (string) $this->actingAs($this->actingUser())->get('/dashboard')->assertOk()->getContent();

        $this->assertStringContainsString("localStorage.getItem('sidebarOpen')", $html);
        $this->assertStringContainsString("setAttribute('data-rail', 'true')", $html);
        $this->assertStringContainsString('data-rail-toggle', $html);
        $this->assertStringContainsString('window.innerWidth < 1280', $html, 'a tablet starts as a rail');
    }

    /** the main column must be allowed to shrink below its content, or a wide table pushes the page sideways */
    public function test_the_main_column_has_min_width_zero_on_both_layouts(): void
    {
        $css = (string) file_get_contents(public_path('dot-ui-host.css'));
        $this->assertMatchesRegularExpression('#\.mines-main\s*\{[^}]*min-width: 0;#', $css);

        foreach (['/dashboard', '/alerts'] as $path) {
            $html = (string) $this->actingAs($this->actingUser())->get($path)->assertOk()->getContent();
            $this->assertStringContainsString('<main id="main-content" class="mines-main"', $html, $path);
        }
    }
}
