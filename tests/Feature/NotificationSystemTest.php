<?php

namespace Tests\Feature;

use App\Events\GeofenceEntryDetected;
use App\Jobs\SendNotificationEmailJob;
use App\Listeners\SendGeofenceBreachNotification;
use App\Mail\NotificationAlertMail;
use App\Models\Geofence;
use App\Models\GeofenceEntry;
use App\Models\Machine;
use App\Models\MaintenanceRecord;
use App\Models\MineArea;
use App\Models\Notification;
use App\Models\Role;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\TeamRoleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class NotificationSystemTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<mixed>
     */
    private function makeTeamWithRoles(): array
    {
        $admin = User::factory()->withPersonalTeam()->create();
        $team = $admin->currentTeam;
        TeamRoleService::provisionTeam($team, $admin);

        $fleetManager = User::factory()->create();
        $team->users()->attach($fleetManager->id);
        $fmRole = Role::where('team_id', $team->id)->where('name', 'fleet_manager')->first();
        $fleetManager->roles()->attach($fmRole->id);

        return [$admin, $fleetManager, $team];
    }

    // ===================== NotificationService =====================

    #[Test]
    public function notification_service_creates_in_app_notification(): void
    {
        [$admin, $fleetManager, $team] = $this->makeTeamWithRoles();

        Queue::fake();

        $notification = NotificationService::dispatch([
            'team_id' => $team->id,
            'type' => NotificationService::TYPE_MACHINE,
            'title' => 'Test Notification',
            'message' => 'This is a test.',
            'alert_level' => NotificationService::LEVEL_INFO,
            'notify_roles' => ['admin', 'fleet_manager'],
        ]);

        $this->assertNotNull($notification);
        $this->assertDatabaseHas('notifications', [
            'team_id' => $team->id,
            'type' => NotificationService::TYPE_MACHINE,
            'title' => 'Test Notification',
        ]);

        Queue::assertPushedOn('notifications', SendNotificationEmailJob::class);
    }

    #[Test]
    public function notification_service_skips_email_when_disabled(): void
    {
        [$admin, $fleetManager, $team] = $this->makeTeamWithRoles();

        Queue::fake();

        NotificationService::dispatch([
            'team_id' => $team->id,
            'type' => NotificationService::TYPE_CUSTOM,
            'title' => 'No email',
            'message' => 'This should not queue an email.',
            'notify_roles' => ['admin'],
            'email' => false,
        ]);

        Queue::assertNotPushed(SendNotificationEmailJob::class);
    }

    // ===================== Machine Observer =====================

    #[Test]
    public function machine_created_notification_is_dispatched(): void
    {
        [$admin, $fleetManager, $team] = $this->makeTeamWithRoles();

        Queue::fake();

        $machine = Machine::factory()->create(['team_id' => $team->id]);

        $this->assertDatabaseHas('notifications', [
            'team_id' => $team->id,
            'type' => NotificationService::TYPE_MACHINE,
        ]);

        Queue::assertPushedOn('notifications', SendNotificationEmailJob::class);
    }

    #[Test]
    public function machine_deleted_notification_is_dispatched(): void
    {
        [$admin, $fleetManager, $team] = $this->makeTeamWithRoles();
        $machine = Machine::factory()->create(['team_id' => $team->id]);

        Queue::fake();

        $machine->delete();

        $this->assertDatabaseHas('notifications', [
            'team_id' => $team->id,
            'type' => NotificationService::TYPE_MACHINE,
            'alert_level' => NotificationService::LEVEL_WARNING,
        ]);
    }

    // ===================== Maintenance Observer =====================

    #[Test]
    public function maintenance_created_sends_notification_to_operators(): void
    {
        [$admin, $fleetManager, $team] = $this->makeTeamWithRoles();
        $machine = Machine::factory()->create(['team_id' => $team->id]);

        Queue::fake();

        MaintenanceRecord::factory()->create([
            'team_id' => $team->id,
            'machine_id' => $machine->id,
            'status' => 'scheduled',
            'priority' => 'medium',
        ]);

        $this->assertDatabaseHas('notifications', [
            'team_id' => $team->id,
            'type' => NotificationService::TYPE_MAINTENANCE,
        ]);
    }

    // ===================== MineArea Observer =====================

    #[Test]
    public function mine_area_created_sends_notification(): void
    {
        [$admin, $fleetManager, $team] = $this->makeTeamWithRoles();

        Queue::fake();

        MineArea::factory()->create(['team_id' => $team->id]);

        $this->assertDatabaseHas('notifications', [
            'team_id' => $team->id,
            'type' => NotificationService::TYPE_MINE_AREA,
        ]);
    }

    // ===================== Email Job =====================

    #[Test]
    public function send_notification_email_job_queues_mail_to_users(): void
    {
        [$admin, $fleetManager, $team] = $this->makeTeamWithRoles();
        Mail::fake();

        $notification = Notification::factory()->create([
            'team_id' => $team->id,
            'type' => NotificationService::TYPE_MACHINE,
            'title' => 'Machine Alert',
            'message' => 'Your machine needs attention.',
            'alert_level' => 'high',
        ]);

        $job = new SendNotificationEmailJob($notification->id, [$admin->id, $fleetManager->id]);
        $job->handle();

        Mail::assertQueued(NotificationAlertMail::class, 2);
    }

    #[Test]
    public function send_notification_email_job_records_delivery_log(): void
    {
        [$admin, $fleetManager, $team] = $this->makeTeamWithRoles();
        Mail::fake();

        $notification = Notification::factory()->create([
            'team_id' => $team->id,
            'type' => NotificationService::TYPE_ALERT,
            'title' => 'Critical Alert',
            'message' => 'Immediate action required.',
            'alert_level' => 'critical',
        ]);

        $job = new SendNotificationEmailJob($notification->id, [$admin->id]);
        $job->handle();

        $this->assertDatabaseHas('notification_delivery_logs', [
            'notification_id' => $notification->id,
            'user_id' => $admin->id,
            'channel' => 'email',
            'status' => 'sent',
        ]);
    }

    // ===================== Event Listeners =====================

    #[Test]
    public function geofence_entry_event_triggers_notification(): void
    {
        [$admin, $fleetManager, $team] = $this->makeTeamWithRoles();
        $machine = Machine::factory()->create(['team_id' => $team->id]);
        $mineArea = MineArea::factory()->create(['team_id' => $team->id]);
        $geofence = Geofence::factory()->create(['team_id' => $team->id, 'mine_area_id' => $mineArea->id]);

        Mail::fake();

        $entry = GeofenceEntry::factory()->create([
            'team_id' => $team->id,
            'geofence_id' => $geofence->id,
            'machine_id' => $machine->id,
        ]);

        // Call the listener directly (it's queued in production but sync in tests)
        $listener = new SendGeofenceBreachNotification;
        $listener->handleEntry(new GeofenceEntryDetected($entry));

        $this->assertDatabaseHas('notifications', [
            'team_id' => $team->id,
            'type' => NotificationService::TYPE_GEOFENCE_BREACH,
            'alert_level' => NotificationService::LEVEL_HIGH,
        ]);
    }
}
