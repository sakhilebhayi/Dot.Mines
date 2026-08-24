<?php

namespace Tests\Feature\Operators;

use App\Models\Machine;
use App\Models\MineArea;
use App\Models\Operator;
use App\Models\Team;
use App\Models\User;
use App\Services\TeamRoleProvisioner;
use App\Support\EquipmentType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The fleet page's operator chip and assignment picker.
 */
class FleetOperatorUiTest extends TestCase
{
    use RefreshDatabase;

    private function actingAdmin(): User
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->forceFill(['current_team_id' => $team->id, 'two_factor_confirmed_at' => now()])->save();
        TeamRoleProvisioner::assignRole($user, $team, 'admin');
        $this->actingAs($user->fresh());

        return $user->fresh();
    }

    public function test_the_fleet_page_renders_with_operator_chips_and_can_assign(): void
    {
        $user = $this->actingAdmin();
        $area = MineArea::factory()->create(['team_id' => $user->current_team_id]);
        $machine = Machine::factory()->create([
            'team_id' => $user->current_team_id,
            'mine_area_id' => $area->id,
            'machine_type' => 'adt',
            'name' => 'ADT-UI-01',
        ]);
        $operator = Operator::factory()->compliantFor(EquipmentType::ADT)->create([
            'team_id' => $user->current_team_id,
            'first_name' => 'Sipho',
            'last_name' => 'Mokoena',
        ]);

        $component = Livewire::test('fleet');

        $component->assertSee('ADT-UI-01')->assertSee('+ Assign Operator');

        // Open the picker; the compliant operator is offered as eligible.
        $component->call('openAssignOperator', $machine->id);
        $rows = $component->instance()->getAssignableOperatorsProperty();
        $this->assertNotEmpty($rows);
        $this->assertTrue($rows[0]['eligible']);

        // Assign, then the card shows the operator.
        $component->call('assignOperator', $operator->id)
            ->assertSee('Sipho Mokoena');

        $this->assertSame($operator->id, $machine->fresh()->currentOperatorAssignment()?->operator_id);

        // Unassign from the card.
        $component->call('unassignOperator', $machine->id)
            ->assertSee('+ Assign Operator');
        $this->assertNull($machine->fresh()->currentOperatorAssignment());
    }

    public function test_closing_the_picker_from_the_backdrop_fully_resets_its_state(): void
    {
        $user = $this->actingAdmin();
        $area = MineArea::factory()->create(['team_id' => $user->current_team_id]);
        $machine = Machine::factory()->create([
            'team_id' => $user->current_team_id,
            'mine_area_id' => $area->id,
            'machine_type' => 'adt',
        ]);

        // Backdrop click / Escape entangle `false` into the ?int open-state,
        // which Livewire coerces to 0 -- a half-closed modal. The updated
        // hook must normalise it back to null.
        Livewire::test('fleet')
            ->call('openAssignOperator', $machine->id)
            ->set('assignOperatorMachineId', false)
            ->assertSet('assignOperatorMachineId', null);
    }

    public function test_an_ineligible_pick_surfaces_blockers_in_the_modal_instead_of_assigning(): void
    {
        $user = $this->actingAdmin();
        $area = MineArea::factory()->create(['team_id' => $user->current_team_id]);
        $machine = Machine::factory()->create([
            'team_id' => $user->current_team_id,
            'mine_area_id' => $area->id,
            'machine_type' => 'adt',
        ]);
        $unqualified = Operator::factory()->create(['team_id' => $user->current_team_id]);

        $component = Livewire::test('fleet');
        $component->call('openAssignOperator', $machine->id);
        $component->call('assignOperator', $unqualified->id);

        $this->assertNotEmpty($component->get('assignmentBlockers'));
        $this->assertNull($machine->fresh()->currentOperatorAssignment());
    }
}
