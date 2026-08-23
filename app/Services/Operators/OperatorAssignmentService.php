<?php

namespace App\Services\Operators;

use App\Exceptions\IneligibleAssignmentException;
use App\Models\ActivityLog;
use App\Models\Machine;
use App\Models\Operator;
use App\Models\OperatorMachineAssignment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The one way an operator gets onto (or off) a machine.
 *
 * Every path -- the fleet page, the operator page, any future API endpoint --
 * calls this, so the eligibility gate cannot be walked around by picking a
 * different button. Overrides exist because mines have legitimate exceptions
 * (supervised training, an emergency recovery), but they are loud: authorised
 * role only, mandatory reason, the failures being overridden snapshotted on
 * the row, and an activity-log entry. Never silent.
 */
class OperatorAssignmentService
{
    /**
     * @throws IneligibleAssignmentException when blockers exist and no valid override was supplied
     */
    public function assign(
        Operator $operator,
        Machine $machine,
        User $assignedBy,
        ?string $shift = null,
        ?string $overrideReason = null,
    ): OperatorMachineAssignment {
        $check = app(AssignmentEligibility::class)->check($operator, $machine);

        $isOverride = false;

        if (! $check['eligible']) {
            $reason = trim((string) $overrideReason);

            // The override needs BOTH the permission and a reason. An empty
            // reason is refused rather than defaulted, because "who forced
            // this and why" is the entire value of the audit trail.
            if ($reason === '' || ! $assignedBy->hasPermission('manage_operators')) {
                throw new IneligibleAssignmentException($check['blockers']);
            }

            $isOverride = true;
        }

        return DB::transaction(function () use ($operator, $machine, $assignedBy, $shift, $overrideReason, $check, $isOverride): OperatorMachineAssignment {
            // Close the machine's current assignment: a machine has one
            // operator at a time, and assigning a new one relieves the old.
            OperatorMachineAssignment::query()->whereNull('unassigned_at')
                ->where('machine_id', $machine->id)
                ->update([
                    'unassigned_at' => now(),
                    'unassigned_by' => $assignedBy->id,
                    'reason' => 'Relieved by new assignment',
                ]);

            $assignment = OperatorMachineAssignment::create([
                'team_id' => $machine->team_id,
                'operator_id' => $operator->id,
                'machine_id' => $machine->id,
                'shift' => $shift,
                'assigned_at' => now(),
                'assigned_by' => $assignedBy->id,
                'was_override' => $isOverride,
                'override_reason' => $isOverride ? trim((string) $overrideReason) : null,
                'overridden_failures' => $isOverride ? $check['blockers'] : null,
            ]);

            ActivityLog::create([
                'team_id' => $machine->team_id,
                'user_id' => $assignedBy->id,
                'action' => $isOverride ? 'operator_assignment_override' : 'operator_assigned',
                'description' => $operator->name.' assigned to '.$machine->name
                    .($shift !== null ? ' ('.$shift.' shift)' : '')
                    .($isOverride ? ' — COMPLIANCE OVERRIDE: '.trim((string) $overrideReason) : ''),
            ]);

            return $assignment;
        });
    }

    public function unassign(
        OperatorMachineAssignment $assignment,
        User $unassignedBy,
        ?string $reason = null,
    ): OperatorMachineAssignment {
        if (! $assignment->isOpen()) {
            return $assignment;
        }

        $assignment->update([
            'unassigned_at' => now(),
            'unassigned_by' => $unassignedBy->id,
            'reason' => $reason,
        ]);

        ActivityLog::create([
            'team_id' => $assignment->team_id,
            'user_id' => $unassignedBy->id,
            'action' => 'operator_unassigned',
            'description' => ($assignment->operator?->name ?? 'Operator')
                .' unassigned from '.($assignment->machine?->name ?? 'machine')
                .($reason !== null && $reason !== '' ? ' — '.$reason : ''),
        ]);

        return $assignment->refresh();
    }
}
