<?php

namespace Tests\Feature;

use App\Mail\NotificationAlertMail;
use App\Mail\ReportReadyMail;
use App\Mail\WelcomeMail;
use App\Models\Notification;
use App\Models\Report;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * R9 audit: every mailable must RENDER, not just queue. Mail::assertQueued
 * never evaluates the views, so a phantom attribute or an unregistered
 * route inside a mail template only surfaces when the queue worker tries to
 * send -- which is exactly how NotificationAlertMail shipped linking a
 * signed 'email.unsubscribe' route that did not exist (RouteNotFoundException
 * on every send) without a single test going red.
 */
class MailablesRenderTest extends TestCase
{
    use RefreshDatabase;

    public function test_notification_alert_mail_renders(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $notification = Notification::factory()->create([
            'team_id' => $user->currentTeam->id,
            'title' => 'Conveyor stall',
            'message' => 'Belt 2 stopped under load.',
        ]);

        $rendered = (new NotificationAlertMail($notification, $user))->render();

        $this->assertStringContainsString('Conveyor stall', $rendered);
        $this->assertStringContainsString('Belt 2 stopped under load.', $rendered);
        $this->assertStringContainsString('/email/unsubscribe/', $rendered);
    }

    public function test_report_ready_mail_renders(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $report = Report::create([
            'team_id' => $team->id,
            'title' => 'Weekly production summary',
            'type' => 'production',
            'format' => 'pdf',
            'status' => 'completed',
            'generated_by' => $user->id,
        ]);

        $rendered = (new ReportReadyMail($report))->render();

        $this->assertStringContainsString('Weekly production summary', $rendered);
    }

    public function test_welcome_mail_renders(): void
    {
        $user = User::factory()->create(['name' => 'Nomvula Dlamini']);

        $rendered = (new WelcomeMail($user))->render();

        $this->assertStringContainsString('Nomvula Dlamini', $rendered);
    }
}
