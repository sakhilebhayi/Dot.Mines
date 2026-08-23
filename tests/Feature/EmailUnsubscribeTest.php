<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * The signed unsubscribe endpoint that alert emails have linked since the
 * notification pipeline shipped. The route was never registered, so the
 * mailable's own render threw RouteNotFoundException -- meaning every alert
 * email would have failed at SEND time while Mail::assertQueued tests stayed
 * green (queued mail is never rendered). R9 audit find.
 */
class EmailUnsubscribeTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_signed_link_switches_off_alert_emails_without_a_session(): void
    {
        $user = User::factory()->create([
            'notification_preferences' => ['email_alerts' => true, 'email_reports' => true],
        ]);

        $url = URL::signedRoute('email.unsubscribe', ['user' => $user->id, 'type' => 'alert_notifications']);

        $response = $this->get($url); // deliberately unauthenticated

        $response->assertOk();
        $response->assertSee("You're unsubscribed", false);

        $user->refresh();
        $this->assertFalse($user->notification_preferences['email_alerts']);
        $this->assertTrue($user->notification_preferences['email_reports'], 'Only the linked category may be switched off.');
        $this->assertFalse($user->wantsEmailAlert());
    }

    public function test_report_notifications_map_to_the_report_preference(): void
    {
        $user = User::factory()->create([
            'notification_preferences' => ['email_alerts' => true, 'email_reports' => true],
        ]);

        $url = URL::signedRoute('email.unsubscribe', ['user' => $user->id, 'type' => 'report_notifications']);

        $this->get($url)->assertOk();

        $user->refresh();
        $this->assertFalse($user->notification_preferences['email_reports']);
        $this->assertTrue($user->notification_preferences['email_alerts']);
    }

    public function test_an_unsigned_link_is_rejected_and_changes_nothing(): void
    {
        $user = User::factory()->create([
            'notification_preferences' => ['email_alerts' => true],
        ]);

        $response = $this->get("/email/unsubscribe/{$user->id}/alert_notifications");

        $response->assertForbidden();

        $user->refresh();
        $this->assertTrue($user->notification_preferences['email_alerts']);
    }

    public function test_an_unknown_type_is_rejected(): void
    {
        $user = User::factory()->create([
            'notification_preferences' => ['email_alerts' => true],
        ]);

        $url = URL::signedRoute('email.unsubscribe', ['user' => $user->id, 'type' => 'password']);

        $this->get($url)->assertNotFound();

        $user->refresh();
        $this->assertTrue($user->notification_preferences['email_alerts']);
    }
}
