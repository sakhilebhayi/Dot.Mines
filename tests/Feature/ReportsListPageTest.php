<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The /reports list page had no feature test coverage through the real HTTP
 * route before this file (ReportsAuthorizationTest exercises the Livewire
 * component in isolation). Added while re-theming
 * resources/views/livewire/reports.blade.php off its isolated slate-*
 * palette -- a regex mistake mid-edit briefly introduced a Blade syntax
 * error (`\$sortBy` instead of `$sortBy`), which this test would have
 * caught immediately.
 */
class ReportsListPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_away_from_reports(): void
    {
        $response = $this->get('/reports');

        $response->assertRedirect('/login');
    }

    public function test_authenticated_user_with_a_team_can_view_the_reports_list(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->update(['current_team_id' => $team->id]);

        $response = $this->actingAs($user)->get('/reports');

        $response->assertOk();
        $response->assertSee('No Reports Found');
    }
}
