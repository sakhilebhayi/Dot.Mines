<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The /production page had no feature test coverage before this file, so
 * nothing verified the Blade view actually rendered. Added while re-theming
 * resources/views/livewire/production-dashboard.blade.php -- which was
 * almost entirely built on light-mode-base + dark:-override pairs that
 * collapse to dead CSS since the app always forces <html class="dark"> with
 * no toggle -- to confirm the page still compiles and renders, including the
 * empty states for the daily chart, material breakdown, fatigue table, and
 * area performance table.
 */
class ProductionDashboardPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_away_from_production_dashboard(): void
    {
        $response = $this->get('/production');

        $response->assertRedirect('/login');
    }

    public function test_authenticated_user_with_a_team_can_view_the_production_dashboard(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->update(['current_team_id' => $team->id]);

        $response = $this->actingAs($user)->get('/production');

        $response->assertOk();
        $response->assertSee('Production Dashboard');
    }
}
