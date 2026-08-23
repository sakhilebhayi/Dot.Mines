<?php

namespace Tests\Feature\Operators;

use App\Models\Notification;
use App\Models\Operator;
use App\Models\Team;
use App\Services\Operators\ComplianceAlertService;
use App\Support\EquipmentType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * The expiry sweep: the right warning at the right time, exactly once.
 */
class ComplianceAlertTest extends TestCase
{
    use RefreshDatabase;

    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake(); // email fan-out is not under test here
        $this->team = Team::factory()->create();
    }

    private function sweep(): int
    {
        return app(ComplianceAlertService::class)->sweepTeam($this->team->id);
    }

    private function operatorExpiringIn(int $days): Operator
    {
        $operator = Operator::factory()->compliantFor(EquipmentType::ADT)->create(['team_id' => $this->team->id]);
        // Push medical and induction far out so only the licence is in play.
        $operator->medicals()->update(['expires_on' => now()->addYears(2)->toDateString()]);
        $operator->trainings()->update(['expires_on' => now()->addYears(2)->toDateString()]);
        $operator->qualifications()->update(['expires_on' => now()->addDays($days)->toDateString()]);

        return $operator->fresh();
    }

    public function test_the_thirty_day_warning_fires_inside_the_window(): void
    {
        $operator = $this->operatorExpiringIn(25);

        $this->assertSame(1, $this->sweep());

        $notification = Notification::where('type', ComplianceAlertService::TYPE)->firstOrFail();
        $this->assertStringContainsString('expires in 25 days', $notification->message);
        $this->assertSame('warning', $notification->alert_level);
        $this->assertStringContainsString('/operators/'.$operator->id, (string) $notification->action_url);
    }

    public function test_the_same_milestone_never_fires_twice(): void
    {
        $this->operatorExpiringIn(25);

        $this->assertSame(1, $this->sweep());
        $this->assertSame(0, $this->sweep(), 'A daily scheduler must not repeat the same warning daily.');
        $this->assertSame(0, $this->sweep());

        $this->assertSame(1, Notification::where('type', ComplianceAlertService::TYPE)->count());
    }

    public function test_each_escalation_fires_once_as_the_date_approaches(): void
    {
        $operator = $this->operatorExpiringIn(25);
        $this->assertSame(1, $this->sweep()); // 30-day milestone

        // Time passes: now 12 days out -> the 14-day escalation.
        $operator->qualifications()->update(['expires_on' => now()->addDays(12)->toDateString()]);
        $this->assertSame(1, $this->sweep());
        $this->assertSame(0, $this->sweep());

        // 5 days out -> the 7-day escalation, level high.
        $operator->qualifications()->update(['expires_on' => now()->addDays(5)->toDateString()]);
        $this->assertSame(1, $this->sweep());

        // Lapsed -> the expired alert, critical.
        $operator->qualifications()->update(['expires_on' => now()->subDay()->toDateString()]);
        $this->assertSame(1, $this->sweep());
        $this->assertSame(0, $this->sweep());

        $levels = Notification::where('type', ComplianceAlertService::TYPE)
            ->orderBy('id')->pluck('alert_level')->all();
        $this->assertSame(['warning', 'warning', 'high', 'critical'], $levels);
    }

    public function test_a_credential_first_seen_late_gets_one_alert_not_a_backlog(): void
    {
        // Added to the system already 5 days from expiry: the 7-day milestone
        // fires, not 30+14+7 all at once.
        $this->operatorExpiringIn(5);

        $this->assertSame(1, $this->sweep());
    }

    public function test_far_future_and_perpetual_credentials_stay_silent(): void
    {
        $operator = Operator::factory()->compliantFor(EquipmentType::ADT)->create(['team_id' => $this->team->id]);
        $operator->medicals()->update(['expires_on' => now()->addYears(2)->toDateString()]);
        $operator->trainings()->update(['expires_on' => null]);
        $operator->qualifications()->update(['expires_on' => now()->addYears(2)->toDateString()]);

        $this->assertSame(0, $this->sweep());
    }

    public function test_inactive_operators_are_not_swept(): void
    {
        $operator = $this->operatorExpiringIn(5);
        $operator->update(['employment_status' => Operator::STATUS_INACTIVE]);

        $this->assertSame(0, $this->sweep(), 'Nobody needs licence warnings for someone who left.');
    }

    public function test_the_sweep_is_scoped_to_the_team(): void
    {
        $otherTeam = Team::factory()->create();
        $other = Operator::factory()->compliantFor(EquipmentType::ADT)->create(['team_id' => $otherTeam->id]);
        $other->qualifications()->update(['expires_on' => now()->addDays(5)->toDateString()]);

        $this->assertSame(0, $this->sweep(), 'One team must never be alerted about another team\'s operators.');
    }

    public function test_the_artisan_command_sweeps_every_team(): void
    {
        $this->operatorExpiringIn(25);

        $this->artisan('operators:check-compliance')
            ->expectsOutputToContain('1 new alert(s)')
            ->assertSuccessful();
    }

    public function test_editing_the_warning_windows_does_not_resend_old_milestones(): void
    {
        $operator = $this->operatorExpiringIn(25);
        $this->assertSame(1, $this->sweep());

        // Site adds a 20-day escalation. At 25 days out nothing new applies,
        // and the already-sent 30-day alert stays sent.
        config(['operators.warning_days' => [30, 20, 14, 7]]);
        $this->assertSame(0, $this->sweep());

        // Once inside the new window, the 20-day milestone fires exactly once.
        $operator->qualifications()->update(['expires_on' => now()->addDays(18)->toDateString()]);
        $this->assertSame(1, $this->sweep());
        $this->assertSame(0, $this->sweep());
    }
}
