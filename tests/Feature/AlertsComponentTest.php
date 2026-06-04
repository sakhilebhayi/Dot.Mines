<?php

namespace Tests\Feature;

use App\Livewire\Alerts;
use App\Models\Alert;
use App\Models\Machine;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AlertsComponentTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdminUser(): array
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id, 'personal_team' => true]);
        $user->forceFill(['current_team_id' => $team->id])->save();

        $role = Role::firstOrCreate(
            ['name' => 'admin'],
            ['display_name' => 'Admin', 'team_id' => $team->id]
        );
        $user->roles()->attach($role);

        return [$user, $team];
    }

    #[Test]
    public function component_mounts_for_authenticated_user(): void
    {
        [$user] = $this->makeAdminUser();
        $this->actingAs($user);

        Livewire::test(Alerts::class)->assertOk();
    }

    #[Test]
    public function acknowledging_alert_sets_acknowledged_fields(): void
    {
        [$user, $team] = $this->makeAdminUser();
        $machine = Machine::factory()->create(['team_id' => $team->id]);
        $alert = Alert::factory()->create(['team_id' => $team->id, 'machine_id' => $machine->id, 'status' => 'active']);

        $this->actingAs($user);

        Livewire::test(Alerts::class)
            ->call('acknowledgeAlert', $alert->id);

        $this->assertDatabaseHas('alerts', [
            'id' => $alert->id,
            'acknowledged_by' => $user->id,
        ]);
        $this->assertNotNull($alert->fresh()->acknowledged_at);
    }

    #[Test]
    public function resolving_alert_marks_it_resolved(): void
    {
        [$user, $team] = $this->makeAdminUser();
        $machine = Machine::factory()->create(['team_id' => $team->id]);
        $alert = Alert::factory()->create(['team_id' => $team->id, 'machine_id' => $machine->id, 'status' => 'active']);

        $this->actingAs($user);

        Livewire::test(Alerts::class)
            ->call('resolveAlert', $alert->id);

        $this->assertDatabaseHas('alerts', [
            'id' => $alert->id,
            'status' => 'resolved',
            'resolved_by' => $user->id,
        ]);
    }

    #[Test]
    public function cross_team_alert_cannot_be_acknowledged(): void
    {
        [$user] = $this->makeAdminUser();
        [, $otherTeam] = $this->makeAdminUser();
        $machine = Machine::factory()->create(['team_id' => $otherTeam->id]);
        $alert = Alert::factory()->create(['team_id' => $otherTeam->id, 'machine_id' => $machine->id]);

        $this->actingAs($user);

        Livewire::test(Alerts::class)
            ->call('acknowledgeAlert', $alert->id);

        // The other team's alert must not be modified
        $this->assertNull($alert->fresh()->acknowledged_by);
    }

    #[Test]
    public function sort_by_rejects_invalid_column(): void
    {
        [$user] = $this->makeAdminUser();
        $this->actingAs($user);

        Livewire::test(Alerts::class)
            ->call('setSortBy', 'injected_column')
            ->assertOk();
    }

    #[Test]
    public function sort_direction_toggles_on_same_column(): void
    {
        [$user] = $this->makeAdminUser();
        $this->actingAs($user);

        Livewire::test(Alerts::class)
            ->assertSet('sortDirection', 'desc')
            ->call('setSortBy', 'created_at')
            ->assertSet('sortDirection', 'asc')
            ->call('setSortBy', 'created_at')
            ->assertSet('sortDirection', 'desc');
    }

    #[Test]
    public function only_own_team_alerts_are_returned(): void
    {
        [$user, $team] = $this->makeAdminUser();
        [, $otherTeam] = $this->makeAdminUser();
        $machine = Machine::factory()->create(['team_id' => $team->id]);
        $otherMachine = Machine::factory()->create(['team_id' => $otherTeam->id]);

        Alert::factory()->count(3)->create(['team_id' => $team->id, 'machine_id' => $machine->id]);
        Alert::factory()->count(2)->create(['team_id' => $otherTeam->id, 'machine_id' => $otherMachine->id]);

        $this->actingAs($user);

        $alerts = Livewire::test(Alerts::class)->instance()->getAlerts();

        $this->assertSame(3, $alerts->total());
    }

    #[Test]
    public function dismissing_resolved_alert_marks_it_dismissed(): void
    {
        [$user, $team] = $this->makeAdminUser();
        $machine = Machine::factory()->create(['team_id' => $team->id]);
        $alert = Alert::factory()->create([
            'team_id' => $team->id,
            'machine_id' => $machine->id,
            'status' => 'resolved',
        ]);

        $this->actingAs($user);

        Livewire::test(Alerts::class)
            ->call('dismissAlert', $alert->id);

        $this->assertDatabaseHas('alerts', [
            'id' => $alert->id,
            'status' => 'dismissed',
        ]);
    }
}
