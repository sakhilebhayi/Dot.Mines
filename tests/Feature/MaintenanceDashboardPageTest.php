<?php

namespace Tests\Feature;

use App\Models\Machine;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The /maintenance page had no feature test coverage before this file, so
 * nothing verified the Blade view actually rendered. Added while re-theming
 * resources/views/livewire/maintenance-dashboard.blade.php (and the shared
 * daisyui "mines" theme in tailwind.config.js it relies on for
 * card/stat/badge/alert styling) to confirm the page still compiles and
 * renders.
 */
class MaintenanceDashboardPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_away_from_maintenance_dashboard(): void
    {
        $response = $this->get('/maintenance');

        $response->assertRedirect('/login');
    }

    public function test_authenticated_user_with_a_team_can_view_the_maintenance_dashboard(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->update(['current_team_id' => $team->id]);

        $response = $this->actingAs($user)->get('/maintenance');

        $response->assertOk();
        $response->assertSee('Book Maintenance');
    }

    /**
     * With machines but no health assessments, the page must say so instead
     * of rendering a fabricated "0%" fleet-average health score.
     */
    public function test_health_score_shows_honest_empty_state_when_nothing_is_assessed(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->update(['current_team_id' => $team->id]);
        Machine::factory()->count(2)->create(['team_id' => $team->id]);

        $response = $this->actingAs($user)->get('/maintenance');

        $response->assertOk();
        $response->assertSee('No health assessments yet');
        $response->assertDontSee('0%');
    }
}
