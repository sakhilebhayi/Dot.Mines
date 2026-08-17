<?php

namespace Tests\Feature;

use App\Models\AIPredictiveAlert;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression test: the navbar's notification bell used to show hardcoded
 * sample text instead of real data, even though a working AINotifications
 * component existed but was never embedded anywhere. Embedding it surfaced
 * two further bugs: $notifications was typed `array` but loadNotifications()
 * always assigned an Eloquent Collection (crashes on render), and the view
 * read $notification->message when the real column is `description`. This
 * proves the full chain -- embed, query, render, real body text -- works.
 */
class NavbarNotificationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_shows_a_real_unacknowledged_alert_in_the_notification_bell(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->update(['current_team_id' => $team->id]);

        AIPredictiveAlert::factory()->create([
            'team_id' => $team->id,
            'title' => 'Predicted brake wear on Excavator 3',
            'description' => 'Sensor trend suggests brake pad replacement within 2 weeks.',
            'severity' => 'high',
            'is_acknowledged' => false,
        ]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        $response->assertSee('Predicted brake wear on Excavator 3');
        $response->assertSee('Sensor trend suggests brake pad replacement within 2 weeks.');
        $response->assertDontSee('Low fuel alert on Machine #1');
    }
}
