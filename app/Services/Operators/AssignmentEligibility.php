<?php

namespace App\Services\Operators;

use App\Models\Machine;
use App\Models\Operator;
use App\Models\OperatorMachineAssignment;
use App\Models\OperatorQualification;
use App\Support\ApiPayload;
use App\Support\CredentialStatus;
use App\Support\EquipmentType;

/**
 * May THIS operator be put on THAT machine?
 *
 * OperatorCompliance answers the general question ("is this person in good
 * standing at all?"); this answers the specific one, and adds the pieces
 * that only exist relative to a machine: a licence for its equipment type,
 * and no conflicting open assignment.
 *
 * Two kinds of finding, deliberately distinct:
 *  - blockers: assigning would be non-compliant. The service refuses unless
 *    an authorised person overrides with a recorded reason.
 *  - warnings: assignment is allowed but a human should look (a credential
 *    inside its expiry window, medical restrictions). Warnings never block,
 *    because "expiring in 20 days" is precisely the period in which the
 *    operator is still legal to work.
 */
class AssignmentEligibility
{
    /**
     * @return array{eligible: bool, blockers: list<string>, warnings: list<string>}
     */
    public function check(Operator $operator, Machine $machine): array
    {
        $operator->loadMissing(['qualifications', 'medicals', 'trainings']);

        $blockers = [];
        $warnings = [];

        // 1. Employment
        if (! $operator->isEmployed()) {
            $blockers[] = $operator->name.' is not actively employed ('
                .(Operator::STATUSES[$operator->employment_status] ?? $operator->employment_status).').';
        }

        // 2 + 3. A licence for this machine's equipment, and that licence valid
        $equipmentType = EquipmentType::normalise($machine->machine_type);
        $licence = $this->bestLicenceFor($operator, $equipmentType);

        if ($equipmentType === EquipmentType::OTHER) {
            // A machine whose type we cannot classify cannot demand a
            // specific licence -- surfaced instead of silently passing.
            $warnings[] = 'The machine type "'.ApiPayload::str($machine->machine_type, '(none)')
                .'" is not classified, so no specific licence can be required. Verify manually.';
        } elseif ($licence === null) {
            $blockers[] = $operator->name.' holds no '.EquipmentType::label($equipmentType).' licence.';
        } elseif (! $licence->isInGoodStanding()) {
            $blockers[] = 'The '.EquipmentType::label($equipmentType).' licence is '.$licence->standing.'.';
        } elseif ($licence->hasExpired()) {
            $blockers[] = 'The '.EquipmentType::label($equipmentType).' licence expired on '
                .($licence->expires_on?->format('j F Y') ?? 'an unknown date').'.';
        } elseif ($licence->expiryStatus() === CredentialStatus::EXPIRING) {
            $warnings[] = 'The '.EquipmentType::label($equipmentType).' licence expires in '
                .(string) ($licence->daysUntilExpiry() ?? 0).' days.';
        }

        // 4. Medical
        if ((bool) data_get(config('operators.required'), 'medical', false)) {
            $medical = $operator->currentMedical();

            if ($medical === null) {
                $blockers[] = $operator->name.' has no medical certificate on record.';
            } elseif (! $medical->isInGoodStanding()) {
                $blockers[] = $operator->name.' is not medically fit for duty.';
            } elseif ($medical->hasExpired()) {
                $blockers[] = 'The medical certificate expired on '.($medical->expires_on?->format('j F Y') ?? 'an unknown date').'.';
            } else {
                if ($medical->expiryStatus() === CredentialStatus::EXPIRING) {
                    $warnings[] = 'The medical certificate expires in '
                        .(string) ($medical->daysUntilExpiry() ?? 0).' days.';
                }

                // The restriction text itself stays behind the medical
                // permission; the assigning user only learns that a review
                // is needed.
                if ($medical->has_restrictions) {
                    $warnings[] = $operator->name.' is fit with medical restrictions -- review before assigning.';
                }
            }
        }

        // 5. Site induction
        if ((bool) data_get(config('operators.required'), 'induction', false)) {
            $summary = app(OperatorCompliance::class)->summarise($operator);
            $induction = collect($summary['items'])->firstWhere('requirement', 'induction');

            if ($induction !== null && in_array($induction['status'], [CredentialStatus::MISSING, CredentialStatus::EXPIRED], true)) {
                $blockers[] = 'Site induction: '.$induction['detail'];
            }
        }

        // 6. No conflicting open assignment, either side.
        $operatorBusy = OperatorMachineAssignment::query()->whereNull('unassigned_at')
            ->where('operator_id', $operator->id)
            ->where('machine_id', '!=', $machine->id)
            ->with('machine')
            ->first();

        if ($operatorBusy !== null) {
            $blockers[] = $operator->name.' is already assigned to '
                .($operatorBusy->machine?->name ?? 'another machine')
                .'. Unassign them first -- one operator, one machine at a time.';
        }

        return [
            'eligible' => $blockers === [],
            'blockers' => $blockers,
            'warnings' => $warnings,
        ];
    }

    /**
     * The licence that answers for this equipment type: a current one if any,
     * otherwise the one that lapsed most recently (so the failure message
     * names the nearest miss).
     */
    private function bestLicenceFor(Operator $operator, string $equipmentType): ?OperatorQualification
    {
        return $operator->qualifications
            ->filter(fn (OperatorQualification $q): bool => $q->equipment_type === $equipmentType)
            ->sortBy([
                fn (OperatorQualification $a, OperatorQualification $b): int => (int) $b->isCurrent() <=> (int) $a->isCurrent(),
                fn (OperatorQualification $a, OperatorQualification $b): int => strcmp(
                    $b->expires_on?->toDateString() ?? '9999-12-31',
                    $a->expires_on?->toDateString() ?? '9999-12-31',
                ),
            ])
            ->first();
    }
}
