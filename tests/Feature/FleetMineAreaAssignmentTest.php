<?php

namespace Tests\Feature;

use App\Livewire\Fleet;
use App\Models\Machine;
use App\Models\MineArea;
use App\Models\Team;
use App\Models\User;
use App\Services\TeamRoleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FleetMineAreaAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->forceFill(['current_team_id' => $team->id])->save();
        TeamRoleService::provisionTeam($team, $user);

        return $user;
    }

    public function test_machine_can_be_assigned_to_a_mine_area(): void
    {
        $user = $this->makeUser();
        $team = $user->currentTeam;

        $machine = Machine::factory()->create([
            'team_id' => $team->id,
            'mine_area_id' => null,
        ]);

        $mineArea = MineArea::factory()->create(['team_id' => $team->id]);

        $this->actingAs($user);

        // Confirm unassigned before action
        $this->assertNull($machine->fresh()->mine_area_id);

        Livewire::test(Fleet::class)
            ->set('assigningMineAreaMachineId', $machine->id)
            ->set('selectedMineAreaId', $mineArea->id)
            ->call('assignToMineArea');

        $this->assertEquals($mineArea->id, $machine->fresh()->mine_area_id);
        $this->assertDatabaseHas('machines', [
            'id' => $machine->id,
            'mine_area_id' => $mineArea->id,
        ]);
    }

    public function test_assignment_is_rejected_when_no_mine_area_selected(): void
    {
        $user = $this->makeUser();
        $team = $user->currentTeam;

        $machine = Machine::factory()->create([
            'team_id' => $team->id,
            'mine_area_id' => null,
        ]);

        $this->actingAs($user);

        Livewire::test(Fleet::class)
            ->set('assigningMineAreaMachineId', $machine->id)
            ->set('selectedMineAreaId', null)
            ->call('assignToMineArea');

        $this->assertNull($machine->fresh()->mine_area_id);
        $this->assertDatabaseMissing('machines', [
            'id' => $machine->id,
            'mine_area_id' => $mineArea->id ?? -1,
        ]);
    }

    public function test_assignment_is_rejected_for_machine_belonging_to_another_team(): void
    {
        $user = $this->makeUser();
        $other = $this->makeUser();

        $machine = Machine::factory()->create(['team_id' => $other->currentTeam->id]);
        $mineArea = MineArea::factory()->create(['team_id' => $user->currentTeam->id]);

        $this->actingAs($user);

        Livewire::test(Fleet::class)
            ->set('assigningMineAreaMachineId', $machine->id)
            ->set('selectedMineAreaId', $mineArea->id)
            ->call('assignToMineArea')
            ->assertStatus(403);

        $this->assertNull($machine->fresh()->mine_area_id);
        $this->assertDatabaseMissing('machines', [
            'id' => $machine->id,
            'mine_area_id' => $mineArea->id,
        ]);
    }
}
