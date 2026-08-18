<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The /alerts page had no feature test coverage at all before this file, so
 * nothing verified the Blade view actually rendered. Added while re-theming
 * resources/views/livewire/alerts.blade.php to confirm the page still
 * compiles and renders for the empty state and with real alert data across
 * every priority/status branch the view switches on.
 */
class AlertsListTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_away_from_alerts(): void
    {
        $response = $this->get('/alerts');

        $response->assertRedirect('/login');
    }

    public function test_authenticated_user_with_a_team_can_view_the_empty_alerts_page(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->update(['current_team_id' => $team->id]);

        $response = $this->actingAs($user)->get('/alerts');

        $response->assertOk();
        $response->assertSee('No Alerts Found');
    }

    public function test_alerts_page_renders_every_priority_and_status_badge_branch(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->update(['current_team_id' => $team->id]);

        foreach (['critical', 'high', 'medium', 'low'] as $priority) {
            Alert::factory()->create(['team_id' => $team->id, 'priority' => $priority]);
        }

        foreach (Alert::STATUSES as $status) {
            Alert::factory()->create(['team_id' => $team->id, 'status' => $status]);
        }

        $response = $this->actingAs($user)->get('/alerts');

        $response->assertOk();
        $response->assertSee('Alerts');
        $response->assertSee('Active');
        $response->assertSee('Acknowledged');
        $response->assertSee('Resolved');
        $response->assertSee('Dismissed - Unresolved');
    }

    public function test_open_alert_count_includes_active_and_acknowledged_alerts(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->update(['current_team_id' => $team->id]);

        Alert::factory()->count(3)->create(['team_id' => $team->id, 'status' => 'active', 'priority' => 'low']);
        Alert::factory()->create(['team_id' => $team->id, 'status' => 'acknowledged', 'priority' => 'low']);
        Alert::factory()->create(['team_id' => $team->id, 'status' => 'resolved', 'priority' => 'low']);

        $response = $this->actingAs($user)->get('/alerts');

        $response->assertOk();
        $response->assertSeeInOrder(['Open Alerts', '4', 'Total Alerts', '5']);
    }
}
