<?php

namespace Tests\Feature;

use App\Livewire\AINotifications;
use App\Models\AIPredictiveAlert;
use App\Models\Alert;
use App\Models\FuelAlert;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The notification bell used to query only AIPredictiveAlert, so a user
 * could have a machine genuinely offline right now (a real Alert row) or a
 * fuel budget genuinely exceeded (a real FuelAlert row) and the bell would
 * still say "no unread alerts". Now merges all three real, live sources.
 */
class AINotificationsUnifiedTest extends TestCase
{
    use RefreshDatabase;

    public function test_bell_shows_operational_and_fuel_alerts_alongside_ai_alerts(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->update(['current_team_id' => $team->id]);

        $aiAlert = AIPredictiveAlert::factory()->create([
            'team_id' => $team->id,
            'title' => 'AI predicted breakdown',
            'is_acknowledged' => false,
        ]);

        $operationalAlert = Alert::factory()->create([
            'team_id' => $team->id,
            'title' => 'Machine offline',
            'status' => 'active',
        ]);

        $fuelAlert = FuelAlert::create([
            'team_id' => $team->id,
            'alert_type' => 'tank_critical',
            'title' => 'Fuel budget exceeded',
            'message' => 'Monthly fuel budget has been exceeded.',
            'severity' => 'critical',
            'status' => 'active',
            'triggered_at' => now(),
        ]);

        $component = Livewire::actingAs($user)->test(AINotifications::class);

        $titles = $component->get('notifications')->pluck('title');

        $this->assertTrue($titles->contains('AI predicted breakdown'));
        $this->assertTrue($titles->contains('Machine offline'));
        $this->assertTrue($titles->contains('Fuel budget exceeded'));
        $component->assertSet('unreadCount', 3);
    }

    public function test_acknowledged_or_resolved_alerts_of_any_source_do_not_appear(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->update(['current_team_id' => $team->id]);

        AIPredictiveAlert::factory()->acknowledged()->create(['team_id' => $team->id]);
        Alert::factory()->resolved()->create(['team_id' => $team->id]);
        FuelAlert::create([
            'team_id' => $team->id,
            'alert_type' => 'tank_low',
            'title' => 'Old resolved fuel alert',
            'message' => 'Resolved.',
            'severity' => 'warning',
            'status' => 'resolved',
            'triggered_at' => now(),
        ]);

        Livewire::actingAs($user)
            ->test(AINotifications::class)
            ->assertSet('unreadCount', 0);
    }

    public function test_acknowledging_an_operational_alert_removes_it_and_updates_the_real_record(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->update(['current_team_id' => $team->id]);

        $alert = Alert::factory()->create(['team_id' => $team->id, 'status' => 'active']);

        Livewire::actingAs($user)
            ->test(AINotifications::class)
            ->call('acknowledge', $alert->id, 'alert')
            ->assertSet('unreadCount', 0);

        $this->assertSame('acknowledged', $alert->fresh()->status);
        $this->assertSame($user->id, $alert->fresh()->acknowledged_by);
    }

    public function test_acknowledging_a_fuel_alert_removes_it_and_updates_the_real_record(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->update(['current_team_id' => $team->id]);

        $fuelAlert = FuelAlert::create([
            'team_id' => $team->id,
            'alert_type' => 'tank_critical',
            'title' => 'Tank critical',
            'message' => 'Tank is critically low.',
            'severity' => 'critical',
            'status' => 'active',
            'triggered_at' => now(),
        ]);

        Livewire::actingAs($user)
            ->test(AINotifications::class)
            ->call('acknowledge', $fuelAlert->id, 'fuel_alert')
            ->assertSet('unreadCount', 0);

        $this->assertSame('acknowledged', $fuelAlert->fresh()->status);
    }

    public function test_acknowledge_all_clears_every_source(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->update(['current_team_id' => $team->id]);

        AIPredictiveAlert::factory()->create(['team_id' => $team->id, 'is_acknowledged' => false]);
        Alert::factory()->create(['team_id' => $team->id, 'status' => 'active']);
        FuelAlert::create([
            'team_id' => $team->id,
            'alert_type' => 'tank_low',
            'title' => 'Low fuel',
            'message' => 'Tank is low.',
            'severity' => 'warning',
            'status' => 'active',
            'triggered_at' => now(),
        ]);

        Livewire::actingAs($user)
            ->test(AINotifications::class)
            ->assertSet('unreadCount', 3)
            ->call('acknowledgeAll')
            ->assertSet('unreadCount', 0);
    }

    public function test_min_severity_preference_filters_the_bell(): void
    {
        $user = User::factory()->create([
            'notification_preferences' => ['min_severity' => 'high'],
        ]);
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->update(['current_team_id' => $team->id]);

        Alert::factory()->create(['team_id' => $team->id, 'status' => 'active', 'priority' => 'low', 'title' => 'Low priority']);
        Alert::factory()->create(['team_id' => $team->id, 'status' => 'active', 'priority' => 'high', 'title' => 'High priority']);

        $component = Livewire::actingAs($user)->test(AINotifications::class);
        $titles = $component->get('notifications')->pluck('title');

        $this->assertFalse($titles->contains('Low priority'));
        $this->assertTrue($titles->contains('High priority'));
        $component->assertSet('unreadCount', 1);
    }

    /**
     * "In-App Alerts" and quiet hours were both real, saved Settings
     * preferences that nothing ever read -- toggling either off had zero
     * effect on what the bell showed.
     */
    public function test_in_app_alerts_disabled_hides_non_critical_notifications_from_the_bell(): void
    {
        $user = User::factory()->create([
            'notification_preferences' => ['in_app_alerts' => false],
        ]);
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->update(['current_team_id' => $team->id]);

        Alert::factory()->create(['team_id' => $team->id, 'status' => 'active', 'priority' => 'high', 'title' => 'High priority']);
        Alert::factory()->create(['team_id' => $team->id, 'status' => 'active', 'priority' => 'critical', 'title' => 'Critical priority']);

        $component = Livewire::actingAs($user)->test(AINotifications::class);
        $titles = $component->get('notifications')->pluck('title');

        $this->assertFalse($titles->contains('High priority'));
        $this->assertTrue($titles->contains('Critical priority'));
        $component->assertSet('unreadCount', 1);
    }

    public function test_quiet_hours_hides_non_critical_notifications_from_the_bell(): void
    {
        $user = User::factory()->create([
            'notification_preferences' => [
                'quiet_hours_enabled' => true,
                'quiet_hours_start' => '00:00',
                'quiet_hours_end' => '23:59',
            ],
        ]);
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->update(['current_team_id' => $team->id]);

        Alert::factory()->create(['team_id' => $team->id, 'status' => 'active', 'priority' => 'high', 'title' => 'High priority']);
        Alert::factory()->create(['team_id' => $team->id, 'status' => 'active', 'priority' => 'critical', 'title' => 'Critical priority']);

        $component = Livewire::actingAs($user)->test(AINotifications::class);
        $titles = $component->get('notifications')->pluck('title');

        $this->assertFalse($titles->contains('High priority'));
        $this->assertTrue($titles->contains('Critical priority'));
        $component->assertSet('unreadCount', 1);
    }

    /**
     * A user setting their threshold to "critical only" must never hide an
     * actually-critical alert -- that would be a real safety regression
     * disguised as a preference.
     */
    public function test_critical_alerts_always_show_regardless_of_preference(): void
    {
        $user = User::factory()->create([
            'notification_preferences' => ['min_severity' => 'critical'],
        ]);
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->update(['current_team_id' => $team->id]);

        Alert::factory()->create(['team_id' => $team->id, 'status' => 'active', 'priority' => 'critical', 'title' => 'Critical issue']);
        Alert::factory()->create(['team_id' => $team->id, 'status' => 'active', 'priority' => 'medium', 'title' => 'Medium issue']);

        $component = Livewire::actingAs($user)->test(AINotifications::class);
        $titles = $component->get('notifications')->pluck('title');

        $this->assertTrue($titles->contains('Critical issue'));
        $this->assertFalse($titles->contains('Medium issue'));
    }

    public function test_a_teams_alerts_never_leak_to_another_team(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->update(['current_team_id' => $team->id]);

        $otherTeam = Team::factory()->create();
        Alert::factory()->create(['team_id' => $otherTeam->id, 'status' => 'active']);
        FuelAlert::create([
            'team_id' => $otherTeam->id,
            'alert_type' => 'tank_low',
            'title' => 'Other team fuel alert',
            'message' => 'Should not be visible.',
            'severity' => 'critical',
            'status' => 'active',
            'triggered_at' => now(),
        ]);

        Livewire::actingAs($user)
            ->test(AINotifications::class)
            ->assertSet('unreadCount', 0);
    }
}
