<?php

namespace Tests\Feature;

use App\Livewire\Settings;
use App\Mail\TeamInvitationMail;
use App\Models\OperatorFatigue;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use App\Notifications\OperatorFatigueAlert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Regression tests for two stubs found in App\Livewire\Settings:
 *
 * 1. inviteUser() used to create the invited person's account immediately,
 *    with a hardcoded literal password and no email sent -- they had no way
 *    to learn it or sign in. It now delegates to Jetstream's own invitation
 *    action, which queues a real email.
 *
 * 2. saveNotificationSettings() validated and displayed a success toast but
 *    never persisted anything ("would use a proper preferences table in
 *    production"); reloading the page silently reset every toggle. It now
 *    saves to users.notification_preferences, and OperatorFatigueAlert (the
 *    one real notification currently in the app) honors the "Email Alerts"
 *    toggle instead of ignoring it.
 *
 * 3. saveGeneralSettings() called $team->update(['email' => ..., 'timezone'
 *    => ..., 'language' => ..., 'currency' => ..., 'name' => ...]) but
 *    Team::$fillable only allowed 'name' and 'personal_team' -- every other
 *    field was silently dropped by mass assignment while the toast still
 *    said "General settings updated". Team::$fillable now includes all four
 *    columns (they already existed on the teams table and mount() already
 *    read them back correctly; only the write path was broken).
 */
class SettingsFunctionalityTest extends TestCase
{
    use RefreshDatabase;

    public function test_inviting_a_member_queues_a_real_invitation_email(): void
    {
        Mail::fake();

        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);
        $owner->update(['current_team_id' => $team->id]);
        Role::factory()->create(['name' => 'operator', 'team_id' => $team->id]);

        Livewire::actingAs($owner)
            ->test(Settings::class)
            ->set('inviteEmail', 'newperson@example.com')
            ->set('selectedRole', 'operator')
            ->call('inviteUser');

        Mail::assertQueued(TeamInvitationMail::class, function ($mail) {
            return $mail->hasTo('newperson@example.com');
        });
    }

    public function test_notification_preferences_persist_across_reloads(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->update(['current_team_id' => $team->id]);

        Livewire::actingAs($user)
            ->test(Settings::class)
            ->set('emailAlerts', false)
            ->set('inAppAlerts', true)
            ->call('saveNotificationSettings');

        $this->assertSame([
            'email_alerts' => false,
            'email_reports' => true,
            'in_app_alerts' => true,
            'quiet_hours_enabled' => false,
            'quiet_hours_start' => '22:00',
            'quiet_hours_end' => '08:00',
            'min_severity' => 'low',
        ], $user->fresh()->notification_preferences);

        // A fresh mount() must read the saved value back, not silently reset to defaults.
        Livewire::actingAs($user->fresh())
            ->test(Settings::class)
            ->assertSet('emailAlerts', false);
    }

    public function test_notification_min_severity_preference_persists_and_reloads(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->update(['current_team_id' => $team->id]);

        Livewire::actingAs($user)
            ->test(Settings::class)
            ->set('notificationMinSeverity', 'high')
            ->call('saveNotificationSettings');

        $this->assertSame('high', $user->fresh()->notification_preferences['min_severity']);

        Livewire::actingAs($user->fresh())
            ->test(Settings::class)
            ->assertSet('notificationMinSeverity', 'high');
    }

    public function test_general_settings_persist_across_reloads(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);
        $owner->update(['current_team_id' => $team->id]);

        Livewire::actingAs($owner)
            ->test(Settings::class)
            ->set('teamEmail', 'ops@example.com')
            ->set('timezone', 'Africa/Johannesburg')
            ->set('language', 'af')
            ->set('currency', 'EUR')
            ->call('saveGeneralSettings');

        $team->refresh();
        $this->assertSame('ops@example.com', $team->email);
        $this->assertSame('Africa/Johannesburg', $team->timezone);
        $this->assertSame('af', $team->language);
        $this->assertSame('EUR', $team->currency);

        // A fresh mount() must read the saved values back, not silently reset to defaults.
        Livewire::actingAs($owner->fresh())
            ->test(Settings::class)
            ->assertSet('currency', 'EUR')
            ->assertSet('timezone', 'Africa/Johannesburg');
    }

    private function makeFatigueRecord(User $user, Team $team): OperatorFatigue
    {
        return OperatorFatigue::create([
            'user_id' => $user->id,
            'team_id' => $team->id,
            'shift_date' => now()->toDateString(),
            'shift_type' => 'day',
            'shift_start' => '06:00',
            'shift_end' => '18:00',
            'hours_worked' => 12,
            'consecutive_days' => 6,
            'fatigue_score' => 85,
            'alert_level' => 'high',
        ]);
    }

    public function test_operator_fatigue_alert_is_not_emailed_when_the_recipient_opted_out(): void
    {
        Notification::fake();

        $team = Team::factory()->create();
        $user = User::factory()->create([
            'notification_preferences' => ['email_alerts' => false],
        ]);
        $fatigue = $this->makeFatigueRecord($user, $team);

        $user->notify(new OperatorFatigueAlert($fatigue));

        // via() returning [] means Laravel's channel manager has nothing to
        // dispatch through, so no delivery is ever recorded for this user.
        Notification::assertNotSentTo($user, OperatorFatigueAlert::class);
    }

    public function test_operator_fatigue_alert_is_still_emailed_by_default(): void
    {
        $team = Team::factory()->create();
        $user = User::factory()->create(['notification_preferences' => null]);
        $fatigue = $this->makeFatigueRecord($user, $team);

        $notification = new OperatorFatigueAlert($fatigue);

        $this->assertSame(['mail'], $notification->via($user));
    }

    /**
     * Quiet hours was a real, saved preference (enabled flag + start/end
     * time) that nothing ever read -- enabling it, or setting any start/end
     * time, had zero effect on anything.
     */
    public function test_operator_fatigue_alert_is_suppressed_during_quiet_hours(): void
    {
        $team = Team::factory()->create();
        $user = User::factory()->create([
            'notification_preferences' => [
                'quiet_hours_enabled' => true,
                'quiet_hours_start' => '00:00',
                'quiet_hours_end' => '23:59',
            ],
        ]);
        $fatigue = $this->makeFatigueRecord($user, $team);

        $notification = new OperatorFatigueAlert($fatigue);

        $this->assertSame([], $notification->via($user));
    }

    /**
     * A critical fatigue alert -- an operator who should be pulled off
     * machinery -- must never be silently swallowed by quiet hours.
     */
    public function test_critical_operator_fatigue_alert_bypasses_quiet_hours(): void
    {
        $team = Team::factory()->create();
        $user = User::factory()->create([
            'notification_preferences' => [
                'quiet_hours_enabled' => true,
                'quiet_hours_start' => '00:00',
                'quiet_hours_end' => '23:59',
            ],
        ]);
        $fatigue = $this->makeFatigueRecord($user, $team);
        $fatigue->update(['alert_level' => 'critical']);

        $notification = new OperatorFatigueAlert($fatigue->fresh());

        $this->assertSame(['mail'], $notification->via($user));
    }
}
