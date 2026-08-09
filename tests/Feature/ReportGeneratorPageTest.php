<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The /reports/generate page had no feature test coverage before this file,
 * so nothing verified the Blade view actually rendered. Added while
 * re-theming resources/views/livewire/report-generator.blade.php off its
 * isolated slate-* palette.
 */
class ReportGeneratorPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_away_from_report_generator(): void
    {
        $response = $this->get('/reports/generate');

        $response->assertRedirect('/login');
    }

    public function test_authenticated_user_with_a_team_can_view_report_generator(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->update(['current_team_id' => $team->id]);

        $response = $this->actingAs($user)->get('/reports/generate');

        $response->assertOk();
        $response->assertSee('Report Details');
    }
}
