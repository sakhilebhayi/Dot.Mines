<?php

namespace Tests\Feature\Operators;

use App\Exceptions\IneligibleAssignmentException;
use App\Models\Machine;
use App\Models\MineArea;
use App\Models\Operator;
use App\Models\OperatorMachineAssignment;
use App\Models\Team;
use App\Models\User;
use App\Services\Operators\AssignmentEligibility;
use App\Services\Operators\OperatorAssignmentService;
use App\Services\TeamRoleProvisioner;
use App\Support\EquipmentType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The gate between an operator and a machine: eligibility checked server-side,
 * conflicts refused, overrides loud and audited.
 */
class OperatorAssignmentTest extends TestCase
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
        $this->actingAs($this->admin);
    }

    private function adt(): Machine
    {
        $area = MineArea::factory()->create(['team_id' => $this->team->id]);

        return Machine::factory()->create([
            'team_id' => $this->team->id,
            'mine_area_id' => $area->id,
            'machine_type' => 'adt',
            'name' => 'ADT-01',
        ]);
    }

    private function compliantOperator(): Operator
    {
        return Operator::factory()->compliantFor(EquipmentType::ADT)->create(['team_id' => $this->team->id]);
    }

    private function service(): OperatorAssignmentService
    {
        return app(OperatorAssignmentService::class);
    }

    public function test_a_compliant_operator_can_be_assigned(): void
    {
        $operator = $this->compliantOperator();
        $machine = $this->adt();

        $assignment = $this->service()->assign($operator, $machine, $this->admin, 'day');

        $this->assertTrue($assignment->isOpen());
        $this->assertFalse($assignment->was_override);
        $this->assertSame('day', $assignment->shift);
        $this->assertSame($operator->id, $machine->fresh()->currentOperatorAssignment()?->operator_id);
        $this->assertSame($machine->id, $operator->fresh()->currentAssignment()?->machine_id);
    }

    public function test_an_expired_licence_blocks_assignment_with_the_reason(): void
    {
        $operator = $this->compliantOperator();
        $operator->qualifications()->update(['expires_on' => now()->subDays(3)->toDateString()]);
        $machine = $this->adt();

        try {
            $this->service()->assign($operator->fresh(), $machine, $this->admin);
            $this->fail('An expired licence must block assignment.');
        } catch (IneligibleAssignmentException $e) {
            $this->assertStringContainsString('licence expired on', implode(' ', $e->blockers));
        }

        $this->assertSame(0, OperatorMachineAssignment::query()->count());
    }

    public function test_a_missing_licence_for_that_equipment_blocks(): void
    {
        // Licensed for excavators, being put on an ADT.
        $operator = Operator::factory()->compliantFor(EquipmentType::EXCAVATOR)->create(['team_id' => $this->team->id]);
        $machine = $this->adt();

        try {
            $this->service()->assign($operator, $machine, $this->admin);
            $this->fail('A licence for different equipment must not authorise this machine.');
        } catch (IneligibleAssignmentException $e) {
            $this->assertStringContainsString('no Articulated Dump Truck licence', implode(' ', $e->blockers));
        }
    }

    public function test_machine_type_spellings_are_normalised_before_matching(): void
    {
        $operator = $this->compliantOperator();
        $area = MineArea::factory()->create(['team_id' => $this->team->id]);

        // Production data really contains this spelling; the ADT licence must
        // still match it.
        $machine = Machine::factory()->create([
            'team_id' => $this->team->id,
            'mine_area_id' => $area->id,
            'machine_type' => 'articulated dump truck',
        ]);

        $check = app(AssignmentEligibility::class)->check($operator, $machine);

        $this->assertTrue($check['eligible'], implode(' ', $check['blockers']));
    }

    public function test_an_expiring_licence_warns_but_does_not_block(): void
    {
        $operator = $this->compliantOperator();
        $operator->qualifications()->update(['expires_on' => now()->addDays(10)->toDateString()]);
        $machine = $this->adt();

        $check = app(AssignmentEligibility::class)->check($operator->fresh(), $machine);

        $this->assertTrue($check['eligible'], 'Expiring-soon is exactly when the operator is still legal to work.');
        $this->assertStringContainsString('expires in', implode(' ', $check['warnings']));
    }

    public function test_medical_restrictions_warn_without_exposing_the_detail(): void
    {
        $operator = $this->compliantOperator();
        $operator->medicals()->update([
            'fitness' => 'fit_with_restrictions',
            'has_restrictions' => true,
            'restrictions' => 'No night shift for Mr Confidential',
        ]);

        $check = app(AssignmentEligibility::class)->check($operator->fresh(), $this->adt());

        $this->assertTrue($check['eligible']);
        $joined = implode(' ', $check['warnings']);
        $this->assertStringContainsString('medical restrictions', $joined);
        $this->assertStringNotContainsString('Confidential', $joined, 'The restriction text stays behind the medical permission.');
    }

    public function test_an_operator_cannot_be_on_two_machines_at_once(): void
    {
        $operator = $this->compliantOperator();
        $first = $this->adt();
        $second = Machine::factory()->create([
            'team_id' => $this->team->id,
            'mine_area_id' => $first->mine_area_id,
            'machine_type' => 'adt',
            'name' => 'ADT-02',
        ]);

        $this->service()->assign($operator, $first, $this->admin);

        try {
            $this->service()->assign($operator->fresh(), $second, $this->admin);
            $this->fail('One operator, one machine at a time.');
        } catch (IneligibleAssignmentException $e) {
            $this->assertStringContainsString('already assigned to ADT-01', implode(' ', $e->blockers));
        }
    }

    public function test_assigning_a_new_operator_relieves_the_old_one(): void
    {
        $first = $this->compliantOperator();
        $second = $this->compliantOperator();
        $machine = $this->adt();

        $original = $this->service()->assign($first, $machine, $this->admin);
        $this->service()->assign($second, $machine, $this->admin);

        $original->refresh();
        $this->assertFalse($original->isOpen());
        $this->assertSame('Relieved by new assignment', $original->reason);
        $this->assertSame($second->id, $machine->fresh()->currentOperatorAssignment()?->operator_id);

        // History keeps both rows.
        $this->assertSame(2, $machine->operatorAssignments()->count());
    }

    public function test_an_override_requires_a_reason_and_is_fully_audited(): void
    {
        $operator = $this->compliantOperator();
        $operator->qualifications()->update(['expires_on' => now()->subDay()->toDateString()]);
        $machine = $this->adt();

        // No reason -> refused even for an admin.
        try {
            $this->service()->assign($operator->fresh(), $machine, $this->admin, null, '   ');
            $this->fail('An override without a reason is not an override, it is a bypass.');
        } catch (IneligibleAssignmentException) {
        }

        $assignment = $this->service()->assign(
            $operator->fresh(),
            $machine,
            $this->admin,
            'day',
            'Supervised training with instructor present',
        );

        $this->assertTrue($assignment->was_override);
        $this->assertSame('Supervised training with instructor present', $assignment->override_reason);
        $this->assertSame($this->admin->id, $assignment->assigned_by);
        $this->assertNotEmpty($assignment->overridden_failures, 'The failures overridden must be snapshotted.');
        $this->assertStringContainsString('licence expired', implode(' ', $assignment->overridden_failures));

        $this->assertDatabaseHas('activity_logs', [
            'team_id' => $this->team->id,
            'user_id' => $this->admin->id,
            'action' => 'operator_assignment_override',
        ]);
    }

    public function test_an_override_needs_the_manage_permission(): void
    {
        $operator = $this->compliantOperator();
        $operator->qualifications()->update(['expires_on' => now()->subDay()->toDateString()]);
        $machine = $this->adt();

        // The operator role can view but not manage operators.
        $viewer = User::factory()->create(['current_team_id' => $this->team->id]);
        TeamRoleProvisioner::assignRole($viewer, $this->team, 'operator');

        $this->expectException(IneligibleAssignmentException::class);

        $this->service()->assign($operator->fresh(), $machine, $viewer->fresh(), null, 'trying anyway');
    }

    public function test_unassign_closes_the_row_and_logs(): void
    {
        $operator = $this->compliantOperator();
        $machine = $this->adt();
        $assignment = $this->service()->assign($operator, $machine, $this->admin);

        $this->service()->unassign($assignment, $this->admin, 'End of shift');

        $this->assertFalse($assignment->fresh()->isOpen());
        $this->assertSame($this->admin->id, $assignment->fresh()->unassigned_by);
        $this->assertNull($machine->fresh()->currentOperatorAssignment());
        $this->assertDatabaseHas('activity_logs', ['action' => 'operator_unassigned']);
    }

    public function test_an_unclassifiable_machine_type_warns_instead_of_requiring_nothing_silently(): void
    {
        $operator = $this->compliantOperator();
        $area = MineArea::factory()->create(['team_id' => $this->team->id]);
        $machine = Machine::factory()->create([
            'team_id' => $this->team->id,
            'mine_area_id' => $area->id,
            'machine_type' => 'other',
        ]);

        $check = app(AssignmentEligibility::class)->check($operator, $machine);

        $this->assertTrue($check['eligible']);
        $this->assertStringContainsString('not classified', implode(' ', $check['warnings']));
    }

    public function test_a_suspended_operator_is_blocked_regardless_of_credentials(): void
    {
        $operator = $this->compliantOperator();
        $operator->update(['employment_status' => Operator::STATUS_SUSPENDED]);

        $check = app(AssignmentEligibility::class)->check($operator->fresh(), $this->adt());

        $this->assertFalse($check['eligible']);
        $this->assertStringContainsString('not actively employed', implode(' ', $check['blockers']));
    }
}
