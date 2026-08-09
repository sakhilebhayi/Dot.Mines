<?php

namespace Tests\Feature;

use App\Models\AIPredictiveAlert;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression test: the navbar's notification bell showed hardcoded sample
 * text ("Low fuel alert on Machine #1", "Machine #3 entered North Pit")
 * instead of real data -- a fully-working AINotifications component existed
 * in the codebase but was never embedded anywhere, so nobody ever saw real
 * alerts there. Wired <livewire:ai-notifications /> into the navbar in its
 * place. Doing so surfaced two pre-existing bugs, invisible until the
 * component was actually rendered for the first time:
 *  1. AINotifications::$notifications was typed `array` but
 *     loadNotifications() always assigned it an Eloquent Collection, which
 *     crashes on render.
 *  2. The view read `$notification->message`, but the real column is
 *     `description` -- the notification body text was always blank.
 * This test proves the whole chain (embed -> real query -> real render,
 * with real body text) now works.
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
