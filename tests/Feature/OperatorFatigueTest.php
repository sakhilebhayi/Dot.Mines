<?php

namespace Tests\Feature;

use App\Livewire\OperatorFatigueTracker;
use App\Models\Alert;
use App\Models\Team;
use App\Models\User;
use App\Notifications\OperatorFatigueAlert;
use App\Services\OperatorFatigueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class OperatorFatigueTest extends TestCase
{
    use RefreshDatabase;

    private function teamWithOwner(): array
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->update(['current_team_id' => $team->id]);

        return [$user, $team];
    }

    public function test_a_high_fatigue_shift_creates_an_alert_and_notifies_the_team(): void
    {
        Notification::fake();

        [$owner, $team] = $this->teamWithOwner();

        $service = app(OperatorFatigueService::class);
        $fatigue = $service->recordShift($owner, $team, [
            'shift_date' => now()->toDateString(),
            'shift_type' => 'night',
            'shift_start' => '18:00',
            'shift_end' => '06:00',
            'hours_worked' => 13,
            'consecutive_days' => 7,
            'break_time_minutes' => 15,
            'incidents_count' => 1,
        ]);

        $this->assertSame('critical', $fatigue->alert_level);
        $this->assertGreaterThanOrEqual(80, $fatigue->fatigue_score);

        $this->assertDatabaseHas('alerts', [
            'team_id' => $team->id,
            'type' => 'fatigue',
            'priority' => 'critical',
            'status' => 'active',
        ]);

        Notification::assertSentTo($owner, OperatorFatigueAlert::class);
    }

    public function test_a_low_fatigue_shift_does_not_create_an_alert_or_notify_anyone(): void
    {
        Notification::fake();

        [$owner, $team] = $this->teamWithOwner();

        $service = app(OperatorFatigueService::class);
        $fatigue = $service->recordShift($owner, $team, [
            'shift_date' => now()->toDateString(),
            'shift_type' => 'morning',
            'shift_start' => '06:00',
            'shift_end' => '14:00',
            'hours_worked' => 8,
            'consecutive_days' => 1,
            'break_time_minutes' => 60,
        ]);

        $this->assertContains($fatigue->alert_level, ['none', 'low', 'medium']);
        $this->assertDatabaseCount('alerts', 0);
        Notification::assertNothingSent();
    }

    public function test_the_route_requires_auth_and_a_team(): void
    {
        $this->get('/operator-fatigue')->assertRedirect('/login');

        $teamless = User::factory()->create(['current_team_id' => null]);
        $this->actingAs($teamless)
            ->get('/operator-fatigue')
            ->assertRedirect(route('teams.create'));
    }

    public function test_logging_a_critical_shift_through_the_livewire_component_creates_an_alert(): void
    {
        Notification::fake();

        [$owner, $team] = $this->teamWithOwner();

        Livewire::actingAs($owner)
            ->test(OperatorFatigueTracker::class)
            ->set('operatorId', $owner->id)
            ->set('shiftType', 'night')
            ->set('shiftStart', '18:00')
            ->set('shiftEnd', '06:00')
            ->set('hoursWorked', 13)
            ->set('consecutiveDays', 7)
            ->set('breakTimeMinutes', 15)
            ->set('incidentsCount', 1)
            ->call('submitShift')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('operator_fatigue', [
            'user_id' => $owner->id,
            'team_id' => $team->id,
            'alert_level' => 'critical',
        ]);

        $this->assertSame(1, Alert::where('type', 'fatigue')->count());
    }
}
