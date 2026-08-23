<?php

namespace Tests\Feature;

use App\Events\NotificationCreated;
use App\Jobs\SendNotificationEmailJob;
use App\Mail\NotificationAlertMail;
use App\Models\Machine;
use App\Models\Notification;
use App\Models\NotificationDeliveryLog;
use App\Models\Team;
use App\Models\User;
use App\Providers\BroadcastServiceProvider;
use App\Services\NotificationService;
use App\Services\RealTimeAlertService;
use App\Services\TeamRoleProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * C3a slice of the #27 split: the centralized notification pipeline.
 * NotificationService is the single dispatcher -- in-app row + bell
 * broadcast + preference-gated role-based emails with delivery logs.
 */
class NotificationPipelineTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: Team}
     */
    private function teamWithAdmin(): array
    {
        $admin = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $admin->id]);
        $admin->update(['current_team_id' => $team->id]);
        TeamRoleProvisioner::assignRole($admin, $team, 'admin');

        return [$admin, $team];
    }

    public function test_dispatch_creates_the_notification_row_and_broadcasts_to_the_bell(): void
    {
        [, $team] = $this->teamWithAdmin();
        Event::fake([NotificationCreated::class]);

        $notification = NotificationService::dispatch([
            'team_id' => $team->id,
            'type' => NotificationService::TYPE_MACHINE,
            'title' => 'Machine Added: CAT 775F',
            'message' => 'A new machine has been added to the fleet.',
        ]);

        $this->assertNotNull($notification);
        $this->assertSame($team->id, $notification->team_id);
        $this->assertFalse((bool) $notification->is_read);
        Event::assertDispatched(NotificationCreated::class, fn ($event) => $event->notification->is($notification));
    }

    public function test_role_targeted_dispatch_queues_the_email_job_for_matching_roles_only(): void
    {
        [$admin, $team] = $this->teamWithAdmin();
        $operator = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($operator, ['role' => 'editor']);
        Queue::fake();

        NotificationService::notifyAdmins(
            $team->id,
            NotificationService::TYPE_MAINTENANCE,
            'Maintenance due',
            'Machine needs service.',
        );

        Queue::assertPushedOn('notifications', SendNotificationEmailJob::class, function (SendNotificationEmailJob $job) use ($admin) {
            return $job->userIds === [$admin->id];
        });
    }

    public function test_email_job_respects_user_preferences_and_writes_delivery_logs(): void
    {
        [$admin, $team] = $this->teamWithAdmin();
        $optedOut = User::factory()->create([
            'current_team_id' => $team->id,
            'notification_preferences' => ['email_alerts' => false],
        ]);
        Mail::fake();

        $notification = Notification::create([
            'team_id' => $team->id,
            'type' => 'maintenance_alert',
            'title' => 'Maintenance due',
            'message' => 'Machine needs service.',
            'alert_level' => 'high',
            'is_read' => false,
        ]);

        (new SendNotificationEmailJob($notification->id, [$admin->id, $optedOut->id]))->handle();

        Mail::assertQueued(NotificationAlertMail::class, 1);
        Mail::assertQueued(NotificationAlertMail::class, fn (NotificationAlertMail $mail) => $mail->hasTo($admin->email));

        $this->assertSame(1, NotificationDeliveryLog::count());
        $log = NotificationDeliveryLog::firstOrFail();
        $this->assertSame($admin->id, $log->user_id);
        $this->assertSame('sent', $log->status);
    }

    public function test_critical_alerts_email_even_when_preferences_opt_out(): void
    {
        [, $team] = $this->teamWithAdmin();
        $optedOut = User::factory()->create([
            'current_team_id' => $team->id,
            'notification_preferences' => ['email_alerts' => false],
        ]);
        Mail::fake();

        $notification = Notification::create([
            'team_id' => $team->id,
            'type' => 'maintenance_alert',
            'title' => 'CRITICAL: brake failure predicted',
            'message' => 'Immediate attention required.',
            'alert_level' => 'critical',
            'is_read' => false,
        ]);

        (new SendNotificationEmailJob($notification->id, [$optedOut->id]))->handle();

        Mail::assertQueued(NotificationAlertMail::class, fn (NotificationAlertMail $mail) => $mail->hasTo($optedOut->email));
    }

    public function test_bell_channel_rejects_users_from_other_teams(): void
    {
        config(['broadcasting.default' => 'reverb']);
        app(BroadcastServiceProvider::class, ['app' => app()])->boot();

        [$member, $team] = $this->teamWithAdmin();
        [$outsider] = $this->teamWithAdmin();

        $memberResponse = $this->actingAs($member)->post('/broadcasting/auth', [
            'channel_name' => "private-team.{$team->id}.notifications",
            'socket_id' => '123.456',
        ]);
        $memberResponse->assertOk();

        $outsiderResponse = $this->actingAs($outsider)->post('/broadcasting/auth', [
            'channel_name' => "private-team.{$team->id}.notifications",
            'socket_id' => '123.456',
        ]);
        $outsiderResponse->assertForbidden();
    }

    public function test_maintenance_alerts_flow_through_the_pipeline(): void
    {
        [, $team] = $this->teamWithAdmin();
        $machine = Machine::factory()->create(['team_id' => $team->id]);
        Queue::fake();
        Event::fake([NotificationCreated::class]);

        app(RealTimeAlertService::class)->dispatchMaintenanceAlert($machine, 0.9, now()->addWeek(), $team->id);

        $this->assertDatabaseHas('notifications', [
            'team_id' => $team->id,
            'type' => 'maintenance_alert',
            'alert_level' => 'critical',
        ]);
        Queue::assertPushedOn('notifications', SendNotificationEmailJob::class);
        Event::assertDispatched(NotificationCreated::class);
    }

    public function test_alert_mail_renders_the_message_in_both_html_and_plain_text(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $notification = Notification::factory()->create([
            'team_id' => $user->currentTeam->id,
            'title' => 'Pump 3 pressure drop',
            'message' => 'Discharge pressure fell 40% in ten minutes.',
            'alert_level' => 'critical',
        ]);

        $mail = new NotificationAlertMail($notification, $user);
        $rendered = $mail->render();

        $this->assertStringContainsString('Discharge pressure fell 40% in ten minutes.', $rendered);

        // The plain-text part read a phantom ->body attribute (the column is
        // `message`), so the Details line silently vanished from every
        // text-format alert email.
        $text = view('emails.text.notification-alert', [
            'notification' => $notification,
            'recipient' => $user,
            'unsubscribeUrl' => 'https://example.test/unsubscribe',
        ])->render();

        $this->assertStringContainsString('Discharge pressure fell 40% in ten minutes.', $text);
    }
}
