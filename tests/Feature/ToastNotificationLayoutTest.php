<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression test: components/layouts/app.blade.php (used by <x-app-layout>
 * on 18 pages including Dashboard, Alerts, Fleet, Settings, Integrations,
 * and Reports) had no listener for the 'notify' browser event that every
 * component's dispatchBrowserEvent('notify', ...) call fires. Every
 * success/error confirmation on those pages was therefore invisible -- the
 * underlying action worked, but nothing ever displayed it. Added the same
 * toast handler resources/views/layouts/app.blade.php already had. This
 * test asserts the listener markup is actually present in the rendered
 * page, not just in the source file.
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

    public function test_mine_areas_page_using_the_other_layout_still_has_its_notify_listener(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->update(['current_team_id' => $team->id]);

        $response = $this->actingAs($user)->get('/mine-areas');

        $response->assertOk();
        $response->assertSee('@notify.window', false);
    }
}
