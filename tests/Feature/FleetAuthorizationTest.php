<?php

namespace Tests\Feature;

use App\Livewire\Fleet;
use App\Models\Machine;
use App\Models\Team;
use App\Models\User;
use App\Services\TeamRoleProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Fleet::saveMachine()/deleteMachine() and the excavator/mine-area
 * assignment actions now authorize against MachinePolicy (which was already
 * defined but unused).
 */
class FleetAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_viewer_role_cannot_delete_a_machine(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);
        $viewer = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($viewer->id);
        TeamRoleProvisioner::assignRole($viewer, $team, 'viewer');

        $machine = Machine::factory()->create(['team_id' => $team->id]);

        Livewire::actingAs($viewer)
            ->test(Fleet::class)
            ->call('deleteMachine', $machine->id);

        $this->assertDatabaseHas('machines', ['id' => $machine->id]);
    }

    public function test_viewer_role_cannot_create_a_machine(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);
        $viewer = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($viewer->id);
        TeamRoleProvisioner::assignRole($viewer, $team, 'viewer');

        Livewire::actingAs($viewer)
            ->test(Fleet::class)
            ->set('name', 'Test Excavator')
            ->set('model', 'A45G')
            ->set('machineType', 'excavator')
            ->set('status', 'active')
            ->call('saveMachine');

        $this->assertDatabaseMissing('machines', ['name' => 'Test Excavator']);
    }

    public function test_fleet_manager_can_create_but_not_delete_a_machine(): void
    {
        // fleet_manager has create/update_machines but not delete_machines --
        // deleting is admin-only by design (see TeamRoleProvisioner::definitions()).
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);
        $manager = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($manager->id);
        TeamRoleProvisioner::assignRole($manager, $team, 'fleet_manager');

        Livewire::actingAs($manager)
            ->test(Fleet::class)
            ->set('name', 'Test Excavator')
            ->set('model', 'A45G')
            ->set('machineType', 'excavator')
            ->set('status', 'active')
            ->call('saveMachine');

        $this->assertDatabaseHas('machines', ['name' => 'Test Excavator', 'team_id' => $team->id]);

        $machine = Machine::where('name', 'Test Excavator')->first();

        Livewire::actingAs($manager)
            ->test(Fleet::class)
            ->call('deleteMachine', $machine->id);

        $this->assertDatabaseHas('machines', ['id' => $machine->id]);
    }

    public function test_admin_can_delete_a_machine(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);
        $owner->update(['current_team_id' => $team->id]);
        TeamRoleProvisioner::assignRole($owner, $team, 'admin');

        $machine = Machine::factory()->create(['team_id' => $team->id]);

        Livewire::actingAs($owner)
            ->test(Fleet::class)
            ->call('deleteMachine', $machine->id);

        $this->assertDatabaseMissing('machines', ['id' => $machine->id]);
    }
}
