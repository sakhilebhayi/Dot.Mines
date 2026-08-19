<?php

namespace Tests\Feature;

use App\Livewire\Dashboard;
use App\Models\Alert;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_away_from_dashboard()
    {
        $response = $this->get('/dashboard');

        $response->assertRedirect('/login');
    }

    public function test_authenticated_user_with_a_team_can_view_the_dashboard()
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->update(['current_team_id' => $team->id]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        $response->assertSee($team->name);
    }

    /**
     * Regression test: EnsureTeamContext lets a teamless user (e.g. removed
     * from their last team) reach /dashboard with current_team_id null, so
     * Auth::user()->currentTeam is genuinely null here. This used to crash
     * with "Attempt to read property 'id' on null" in loadDashboardData();
     * it must now redirect to team creation instead.
     */
    public function test_authenticated_user_with_no_team_is_redirected_to_team_creation()
    {
        $user = User::factory()->create(['current_team_id' => null]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertRedirect(route('teams.create'));
    }

    /**
     * Truth-loop: recent alert cards used to read $alert->message (a column
     * that does not exist -- every card rendered an empty line where the
     * headline belongs) and gated the Acknowledge button on status 'open'
     * (not a canonical status -- the button never rendered, and active
     * alerts wore a green checkmark as if already handled).
     */
    public function test_dashboard_shows_the_alert_headline_and_an_acknowledge_button_for_active_alerts(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->update(['current_team_id' => $team->id]);

        Alert::create([
            'team_id' => $team->id,
            'type' => 'sensor',
            'title' => 'Engine overheat on ADT-07',
            'description' => 'Coolant temperature exceeded threshold.',
            'priority' => 'high',
            'status' => 'active',
            'triggered_at' => now(),
        ]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        $response->assertSee('Engine overheat on ADT-07');
        $response->assertSee('Acknowledge');
    }

    public function test_acknowledging_an_alert_from_the_dashboard_uses_the_canonical_status(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->update(['current_team_id' => $team->id]);

        $alert = Alert::create([
            'team_id' => $team->id,
            'type' => 'sensor',
            'title' => 'Engine overheat on ADT-07',
            'description' => 'Coolant temperature exceeded threshold.',
            'priority' => 'high',
            'status' => 'active',
            'triggered_at' => now(),
        ]);

        Livewire::actingAs($user)
            ->test(Dashboard::class)
            ->call('acknowledgeAlert', $alert->id);

        $alert->refresh();
        $this->assertSame('acknowledged', $alert->status);
        $this->assertSame($user->id, $alert->acknowledged_by);
    }
}
