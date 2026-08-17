<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression test: components/layouts/app.blade.php had no listener for the
 * 'notify' browser event that every component's dispatchBrowserEvent(
 * 'notify', ...) call fires. Every success/error confirmation on the pages
 * it serves was therefore invisible -- the underlying action worked, but
 * nothing ever displayed it. Added the same toast handler
 * resources/views/layouts/app.blade.php already had. These tests assert the
 * listener markup is actually present in the rendered page, not just in
 * the source file.
 *
 * Correction: this file used to claim components/layouts/app.blade.php was
 * reached via <x-app-layout> on pages including Dashboard, Fleet, Settings,
 * Integrations, and Reports, and "tested the other layout" by hitting
 * /mine-areas. Both claims were wrong, and every test below was silently
 * exercising the same one file twice. <x-app-layout> resolves through
 * App\View\Components\AppLayout to layouts/app.blade.php (confirmed
 * empirically) -- that's what Dashboard, Fleet, Settings, Integrations,
 * Reports, and /mine-areas (via @extends('layouts.app')) all actually
 * render. What actually reaches components/layouts/app.blade.php is
 * config('livewire.layout') = 'components.layouts.app' (config/livewire.
 * php), the default layout for a Livewire component bound directly as a
 * route target with no #[Layout(...)] override -- e.g. Alerts, Fuel,
 * Production, Maintenance, Operator Fatigue, Mine Area detail/assignments,
 * AI Optimization/Analytics, Documentation, Billing, Fleet Replay/Route
 * Planning. The dashboard-routed tests below cover layouts/app.blade.php;
 * the alerts-routed tests were added/retargeted to actually cover
 * components/layouts/app.blade.php, which previously had none.
 */
class ToastNotificationLayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_page_using_x_app_layout_renders_a_notify_listener(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->update(['current_team_id' => $team->id]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        $response->assertSee('@notify.window', false);
    }

    public function test_alerts_page_using_the_livewire_default_layout_also_has_its_notify_listener(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->update(['current_team_id' => $team->id]);

        $response = $this->actingAs($user)->get('/alerts');

        $response->assertOk();
        $response->assertSee('@notify.window', false);
    }

    /**
     * Both layouts positioned the toast container at top-4 right-4, well
     * inside the 81px sticky navbar, so every toast rendered on top of the
     * page title and header action button instead of below them (confirmed
     * with a real dispatched event in the browser). Both must clear the
     * navbar now.
     */
    public function test_dashboard_toast_container_clears_the_navbar(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->update(['current_team_id' => $team->id]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        $response->assertDontSee('fixed top-4 right-4', false);
        $response->assertSee('fixed top-24 right-4', false);
    }

    public function test_alerts_toast_container_clears_the_navbar(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->update(['current_team_id' => $team->id]);

        $response = $this->actingAs($user)->get('/alerts');

        $response->assertOk();
        $response->assertDontSee('fixed top-4 right-4', false);
        $response->assertSee('fixed top-24 right-4', false);
    }

    /**
     * layouts/app.blade.php was missing the min-w-0 fix already applied to
     * components/layouts/app.blade.php: an unconstrained-width child
     * collapses the flex-shrink:1 sidebar to a sliver instead of scrolling
     * internally.
     */
    public function test_dashboard_main_content_column_has_min_width_zero(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->update(['current_team_id' => $team->id]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        $response->assertSee('flex-1 flex flex-col min-w-0', false);
    }

    /**
     * components/layouts/app.blade.php had this fix already, and is the
     * layout for the majority of the app's pages (every Livewire component
     * bound directly as a route target -- see the class docblock). This
     * previously had zero automated coverage despite being the more
     * widely-used of the two files.
     */
    public function test_alerts_main_content_column_has_min_width_zero(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->update(['current_team_id' => $team->id]);

        $response = $this->actingAs($user)->get('/alerts');

        $response->assertOk();
        $response->assertSee('flex-1 flex flex-col min-w-0', false);
    }

    /**
     * Neither toast layout had any ARIA attributes at all -- no live region,
     * no role, no accessible name on the icon-only dismiss button, and no
     * prefers-reduced-motion handling. A screen reader user got no
     * indication a toast had even appeared.
     */
    public function test_dashboard_toasts_have_live_region_and_accessible_dismiss_button(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->update(['current_team_id' => $team->id]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        $response->assertSee('aria-live', false);
        $response->assertSee('aria-label="Dismiss notification"', false);
        $response->assertSee('motion-reduce:duration-0', false);
    }

    public function test_alerts_toasts_have_live_region_and_accessible_dismiss_button(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->update(['current_team_id' => $team->id]);

        $response = $this->actingAs($user)->get('/alerts');

        $response->assertOk();
        $response->assertSee('aria-live', false);
        $response->assertSee('aria-label="Dismiss notification"', false);
        $response->assertSee('motion-reduce:duration-0', false);
    }

    /**
     * layouts/app.blade.php used text-[var(--stone)] (#f4efe4, near-white)
     * unconditionally, including on the info toast's bg-[var(--gold)]
     * (#d99e2b, a mid-tone amber) -- a real contrast failure.
     * components/layouts/app.blade.php already special-cased info to dark
     * text; this file didn't, until now.
     */
    public function test_dashboard_info_toast_uses_dark_text_for_contrast_on_gold_background(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->update(['current_team_id' => $team->id]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        $response->assertSee("notification.type === 'info' ? 'text-[var(--ink)]'", false);
    }

    /**
     * components/layouts/app.blade.php already had this special case (see
     * above) -- this locks it in as a regression guard, since it previously
     * had no coverage of its own.
     */
    public function test_alerts_info_toast_uses_dark_text_for_contrast_on_gold_background(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->update(['current_team_id' => $team->id]);

        $response = $this->actingAs($user)->get('/alerts');

        $response->assertOk();
        $response->assertSee("notification.type === 'info' ? 'text-[var(--ink)]'", false);
    }

    /**
     * layouts/app.blade.php generated a toast's id with a bare Date.now() --
     * millisecond precision. Confirmed live in the browser: dispatching 3
     * notify events in the same synchronous script gave all three the exact
     * same id (e.g. 1786340622591 x3). Alpine's x-for :key="notification.id"
     * then treats them as one tracked element, and only the last one
     * actually rendered -- the other two were silently dropped, not shown
     * and not visible in any log. components/layouts/app.blade.php already
     * avoided this with Date.now() + Math.random().
     */
    public function test_dashboard_toast_id_generation_avoids_millisecond_collisions(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->update(['current_team_id' => $team->id]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        $response->assertSee('Date.now() + Math.random()', false);
        $response->assertDontSee('const id = Date.now();', false);
    }

    /**
     * components/layouts/app.blade.php already had this fix -- locking it
     * in as a regression guard, previously uncovered.
     */
    public function test_alerts_toast_id_generation_avoids_millisecond_collisions(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->update(['current_team_id' => $team->id]);

        $response = $this->actingAs($user)->get('/alerts');

        $response->assertOk();
        $response->assertSee('Date.now() + Math.random()', false);
        $response->assertDontSee('const id = Date.now();', false);
    }
}
