<?php

namespace Tests\Feature\Api;

use App\Events\OperatorAssigned;
use App\Events\OperatorUnassigned;
use App\Models\ActivityLog;
use App\Models\Machine;
use App\Models\MineArea;
use App\Models\Operator;
use App\Models\OperatorMachineAssignment;
use App\Models\Team;
use App\Models\User;
use App\Services\TeamRoleProvisioner;
use App\Support\EquipmentType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Assigning through the API goes through OperatorAssignmentService: the
 * eligibility gate refuses, overrides stay loud, relieving is recorded, and
 * the domain events that feed webhooks fire.
 */
class OperatorAssignmentApiTest extends TestCase
{
    use RefreshDatabase;

    private Team $team;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create();
        $this->team = Team::factory()->create(['user_id' => $this->admin->id]);
        $this->admin->update(['current_team_id' => $this->team->id]);
        TeamRoleProvisioner::assignRole($this->admin, $this->team, 'admin');
        $this->admin = $this->admin->fresh();
    }

    private function adt(): Machine
    {
        $area = MineArea::factory()->create(['team_id' => $this->team->id]);

        return Machine::factory()->create(['team_id' => $this->team->id, 'mine_area_id' => $area->id, 'machine_type' => 'adt', 'name' => 'ADT-1']);
    }

    public function test_an_eligible_operator_is_assigned_and_relieves_the_previous_one(): void
    {
        Event::fake([OperatorAssigned::class, OperatorUnassigned::class]);
        $machine = $this->adt();
        $first = Operator::factory()->compliantFor(EquipmentType::ADT)->create(['team_id' => $this->team->id]);
        $second = Operator::factory()->compliantFor(EquipmentType::ADT)->create(['team_id' => $this->team->id]);

        $this->actingAs($this->admin)->postJson('/api/v1/machines/'.$machine->id.'/operator-assignments', ['operator_id' => $first->id, 'shift' => 'day'])
            ->assertCreated()->assertJsonPath('data.operator_id', $first->id)->assertJsonPath('data.machine.name', 'ADT-1')->assertJsonPath('data.shift', 'day')->assertJsonPath('data.unassigned_at', null);

        $this->actingAs($this->admin)->postJson('/api/v1/machines/'.$machine->id.'/operator-assignments', ['operator_id' => $second->id])
            ->assertCreated()->assertJsonPath('data.operator_id', $second->id);

        $this->assertSame(1, OperatorMachineAssignment::query()->whereNull('unassigned_at')->where('machine_id', $machine->id)->count());
        $this->assertSame($second->id, $machine->fresh()->currentOperatorAssignment()?->operator_id);
        $this->assertSame(2, ActivityLog::query()->where('action', 'operator_assigned')->count());
        Event::assertDispatchedTimes(OperatorAssigned::class, 2);
        Event::assertDispatched(OperatorUnassigned::class, fn (OperatorUnassigned $e) => $e->assignment->operator_id === $first->id);
    }

    public function test_the_eligibility_gate_refuses_with_the_blockers_as_validation_errors(): void
    {
        $machine = $this->adt();
        $unlicensed = Operator::factory()->create(['team_id' => $this->team->id]);

        $response = $this->actingAs($this->admin)->postJson('/api/v1/machines/'.$machine->id.'/operator-assignments', ['operator_id' => $unlicensed->id]);

        $response->assertUnprocessable()->assertJsonStructure(['message', 'errors' => ['operator_id']]);
        $this->assertStringContainsString('licence', implode(' ', $response->json('errors.operator_id')));
        $this->assertSame(0, OperatorMachineAssignment::query()->count());

        $this->actingAs($this->admin)->postJson('/api/v1/machines/'.$machine->id.'/operator-assignments', ['operator_id' => $unlicensed->id, 'override_reason' => 'Supervised training run'])
            ->assertCreated()->assertJsonPath('data.was_override', true)->assertJsonPath('data.override_reason', 'Supervised training run');
    }

    public function test_other_teams_operators_and_machines_are_refused_and_viewers_cannot_assign(): void
    {
        $machine = $this->adt();
        $foreign = Operator::factory()->compliantFor(EquipmentType::ADT)->create(['team_id' => Team::factory()->create()->id]);

        $this->actingAs($this->admin)->postJson('/api/v1/machines/'.$machine->id.'/operator-assignments', ['operator_id' => $foreign->id])
            ->assertUnprocessable()->assertJsonValidationErrors(['operator_id']);

        $viewer = User::factory()->create(['current_team_id' => $this->team->id]);
        TeamRoleProvisioner::assignRole($viewer, $this->team, 'viewer');
        $own = Operator::factory()->compliantFor(EquipmentType::ADT)->create(['team_id' => $this->team->id]);
        $this->actingAs($viewer->fresh())->postJson('/api/v1/machines/'.$machine->id.'/operator-assignments', ['operator_id' => $own->id])->assertForbidden();
    }

    public function test_unassign_closes_the_current_stretch_with_a_reason(): void
    {
        Event::fake([OperatorAssigned::class, OperatorUnassigned::class]);
        $machine = $this->adt();
        $operator = Operator::factory()->compliantFor(EquipmentType::ADT)->create(['team_id' => $this->team->id]);
        $this->actingAs($this->admin)->postJson('/api/v1/machines/'.$machine->id.'/operator-assignments', ['operator_id' => $operator->id])->assertCreated();

        $this->actingAs($this->admin)->deleteJson('/api/v1/machines/'.$machine->id.'/operator-assignments', ['reason' => 'End of shift'])
            ->assertOk()->assertJsonPath('data.reason', 'End of shift');
        $this->assertNotNull(OperatorMachineAssignment::query()->firstOrFail()->unassigned_at);
        Event::assertDispatched(OperatorUnassigned::class);

        $this->actingAs($this->admin)->deleteJson('/api/v1/machines/'.$machine->id.'/operator-assignments')->assertNotFound();
    }
}
