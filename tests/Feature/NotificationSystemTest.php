<?php

namespace Tests\Feature;

use App\Events\ComplianceViolationDetected;
use App\Events\GeofenceEntryDetected;
use App\Events\MachineOffline;
use App\Events\NotificationCreated;
use App\Events\SensorReadingRecorded;
use App\Jobs\SendNotificationEmailJob;
use App\Listeners\SendComplianceViolationNotification;
use App\Listeners\SendGeofenceBreachNotification;
use App\Listeners\SendMachineOfflineNotification;
use App\Listeners\SendSensorAlertNotification;
use App\Mail\NotificationAlertMail;
use App\Models\Geofence;
use App\Models\GeofenceEntry;
use App\Models\IoTSensor;
use App\Models\Machine;
use App\Models\MaintenanceRecord;
use App\Models\MineArea;
use App\Models\Notification;
use App\Models\NotificationPreference;
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

    // ===================== MineArea Observer::updated =====================

    #[Test]
    public function mine_area_updated_sends_notification_when_name_changes(): void
    {
        [$admin, $fleetManager, $team] = $this->makeTeamWithRoles();
        $mineArea = MineArea::factory()->create(['team_id' => $team->id]);

        Queue::fake();

        $mineArea->update(['name' => 'Updated Area Name']);

        $this->assertDatabaseHas('notifications', [
            'team_id' => $team->id,
            'type' => NotificationService::TYPE_MINE_AREA,
        ]);
    }

    #[Test]
    public function mine_area_updated_does_not_notify_on_untracked_fields(): void
    {
        [$admin, $fleetManager, $team] = $this->makeTeamWithRoles();
        $mineArea = MineArea::factory()->create(['team_id' => $team->id]);

        $countBefore = Notification::where('team_id', $team->id)
            ->where('type', NotificationService::TYPE_MINE_AREA)
            ->count();

        Queue::fake();

        // Update a field that is not in the watched list
        $mineArea->update(['description' => 'Some description change']);

        $countAfter = Notification::where('team_id', $team->id)
            ->where('type', NotificationService::TYPE_MINE_AREA)
            ->count();

        $this->assertEquals($countBefore, $countAfter);
    }

    // ===================== SensorReadingRecorded Listener =====================

    #[Test]
    public function sensor_anomaly_reading_dispatches_notification(): void
    {
        [$admin, $fleetManager, $team] = $this->makeTeamWithRoles();
        $sensor = IoTSensor::factory()->create(['team_id' => $team->id]);

        Queue::fake();

        $event = new SensorReadingRecorded(
            $sensor,
            ['value' => 120.5, 'unit' => 'bar', 'is_anomaly' => true],
            $team->id,
        );

        $listener = new SendSensorAlertNotification;
        $listener->handle($event);

        $this->assertDatabaseHas('notifications', [
            'team_id' => $team->id,
            'type' => NotificationService::TYPE_ALERT,
            'alert_level' => NotificationService::LEVEL_WARNING,
        ]);
    }

    #[Test]
    public function normal_sensor_reading_does_not_dispatch_notification(): void
    {
        [$admin, $fleetManager, $team] = $this->makeTeamWithRoles();
        $sensor = IoTSensor::factory()->create(['team_id' => $team->id]);

        Queue::fake();

        $event = new SensorReadingRecorded(
            $sensor,
            ['value' => 75.0, 'unit' => 'bar', 'is_anomaly' => false],
            $team->id,
        );

        $listener = new SendSensorAlertNotification;
        $listener->handle($event);

        $this->assertDatabaseMissing('notifications', [
            'team_id' => $team->id,
            'type' => NotificationService::TYPE_ALERT,
        ]);
    }

    // ===================== MachineOffline Listener =====================

    #[Test]
    public function machine_offline_event_dispatches_high_level_notification(): void
    {
        [$admin, $fleetManager, $team] = $this->makeTeamWithRoles();
        $machine = Machine::factory()->create(['team_id' => $team->id]);

        Queue::fake();

        $event = new MachineOffline($machine, 'GPS signal lost');

        $listener = new SendMachineOfflineNotification;
        $listener->handle($event);

        $this->assertDatabaseHas('notifications', [
            'team_id' => $team->id,
            'type' => NotificationService::TYPE_MACHINE,
            'alert_level' => NotificationService::LEVEL_HIGH,
        ]);
    }

    // ===================== ComplianceViolationDetected Listener =====================

    #[Test]
    public function compliance_violation_critical_dispatches_critical_notification(): void
    {
        [$admin, $fleetManager, $team] = $this->makeTeamWithRoles();

        Queue::fake();

        $violation = (object) [
            'id' => 99,
            'violation_type' => 'Missing Safety Inspection',
            'severity' => 'critical',
            'description' => 'Annual safety inspection is overdue.',
            'remediation_deadline' => now()->addDays(7)->toDateString(),
        ];

        $event = new ComplianceViolationDetected($violation, $team->id);

        $listener = new SendComplianceViolationNotification;
        $listener->handle($event);

        $this->assertDatabaseHas('notifications', [
            'team_id' => $team->id,
            'type' => NotificationService::TYPE_ALERT,
            'alert_level' => NotificationService::LEVEL_CRITICAL,
        ]);
    }

    // ===================== NotificationCreated broadcast =====================

    #[Test]
    public function notification_service_dispatch_fires_notification_created_event(): void
    {
        [$admin, $fleetManager, $team] = $this->makeTeamWithRoles();

        Event::fake([NotificationCreated::class]);
        Queue::fake();

        NotificationService::dispatch([
            'team_id' => $team->id,
            'type' => NotificationService::TYPE_CUSTOM,
            'title' => 'Broadcast Test',
            'message' => 'Should fire NotificationCreated.',
            'notify_roles' => ['admin'],
        ]);

        Event::assertDispatched(NotificationCreated::class);
    }

    // ===================== NotificationPreference model =====================

    #[Test]
    public function notification_preference_can_be_created_and_retrieved(): void
    {
        [$admin, , $team] = $this->makeTeamWithRoles();

        NotificationPreference::create([
            'user_id' => $admin->id,
            'team_id' => $team->id,
            'notification_type' => NotificationService::TYPE_MACHINE,
            'email_enabled' => false,
            'in_app_enabled' => true,
            'min_alert_level' => NotificationService::LEVEL_WARNING,
        ]);

        $this->assertDatabaseHas('notification_preferences', [
            'user_id' => $admin->id,
            'team_id' => $team->id,
            'notification_type' => NotificationService::TYPE_MACHINE,
            'email_enabled' => false,
        ]);
    }

    #[Test]
    public function notification_preference_is_above_min_level_returns_correct_result(): void
    {
        $pref = new NotificationPreference;
        $pref->min_alert_level = NotificationService::LEVEL_WARNING;

        $this->assertFalse($pref->isAboveMinLevel(NotificationService::LEVEL_INFO));
        $this->assertTrue($pref->isAboveMinLevel(NotificationService::LEVEL_WARNING));
        $this->assertTrue($pref->isAboveMinLevel(NotificationService::LEVEL_HIGH));
        $this->assertTrue($pref->isAboveMinLevel(NotificationService::LEVEL_CRITICAL));
    }
}
