<?php

namespace Tests\Feature;

use App\Livewire\Alerts;
use App\Models\Alert;
use App\Models\Team;
use App\Models\User;
use App\Services\TeamRoleProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Alerts::acknowledgeAlert()/resolveAlert()/dismissAlert()/confirmDismiss()
 * now authorize against AlertPolicy (which was already defined but unused).
 * This depends on TeamRoleProvisioner having bootstrapped the team's roles
 * -- see TeamRoleProvisioningTest for that half of the fix.
 */
class AlertsAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_viewer_role_cannot_acknowledge_an_alert(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);
        $viewer = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($viewer->id);
        TeamRoleProvisioner::assignRole($viewer, $team, 'viewer');

        $alert = Alert::factory()->create(['team_id' => $team->id, 'status' => 'new']);

        Livewire::actingAs($viewer)
            ->test(Alerts::class)
            ->call('acknowledgeAlert', $alert->id);

        $this->assertSame('new', $alert->fresh()->status);
    }

    public function test_operator_role_can_acknowledge_but_not_resolve_an_alert(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);
        $operator = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($operator->id);
        TeamRoleProvisioner::assignRole($operator, $team, 'operator');

        $alert = Alert::factory()->create(['team_id' => $team->id, 'status' => 'new']);

        Livewire::actingAs($operator)
            ->test(Alerts::class)
            ->call('acknowledgeAlert', $alert->id);

        $this->assertSame('acknowledged', $alert->fresh()->status);

        Livewire::actingAs($operator)
            ->test(Alerts::class)
            ->call('resolveAlert', $alert->id);

        $this->assertSame('acknowledged', $alert->fresh()->status);
    }

    public function test_team_owner_can_acknowledge_and_resolve_an_alert(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);
        $owner->update(['current_team_id' => $team->id]);
        TeamRoleProvisioner::assignRole($owner, $team, 'admin');

        $alert = Alert::factory()->create(['team_id' => $team->id, 'status' => 'new']);

        Livewire::actingAs($owner)
            ->test(Alerts::class)
            ->call('resolveAlert', $alert->id);

        $this->assertSame('resolved', $alert->fresh()->status);
    }
}
