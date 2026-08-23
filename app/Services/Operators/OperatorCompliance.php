<?php

namespace App\Services\Operators;

use App\Models\Operator;
use App\Models\OperatorMedical;
use App\Models\OperatorQualification;
use App\Models\OperatorTraining;
use App\Support\ApiPayload;
use App\Support\CredentialStatus;
use App\Support\EquipmentType;

/**
 * The one answer to "is this operator compliant, and why not?"
 *
 * The operator page, the management list, the fleet assignment picker, the
 * dashboard counts and the expiry alerts all show compliance. They must all
 * compute it HERE, from the same rows, or two screens will eventually give
 * two answers and nobody will trust either.
 *
 * What counts as required comes from config/operators.php, because "what a
 * compliant operator must hold" is site policy, not a fact about the code.
 *
 * Verdicts: 'compliant', 'expiring' (compliant today, something lapses inside
 * the warning window), 'non_compliant'.
 */
class OperatorCompliance
{
    public const COMPLIANT = 'compliant';

    public const EXPIRING = 'expiring';

    public const NON_COMPLIANT = 'non_compliant';

    /**
     * The full compliance picture for one operator.
     *
     * @return array{
     *     verdict: string,
     *     items: list<array{requirement: string, label: string, status: string, detail: string, expires_on: string|null}>
     * }
     */
    public function summarise(Operator $operator): array
    {
        $operator->loadMissing(['qualifications', 'medicals', 'trainings']);

        $items = [];

        if ($this->required('licence')) {
            $items[] = $this->licenceItem($operator);
        }

        if ($this->required('medical')) {
            $items[] = $this->medicalItem($operator);
        }

        if ($this->required('induction')) {
            $items[] = $this->inductionItem($operator);
        }

        $items = [...$items, ...$this->machineCompetencyItems($operator)];

        return [
            'verdict' => $this->verdictFrom($operator, $items),
            'items' => $items,
        ];
    }

    /**
     * The single traffic-light most surfaces need.
     */
    public function verdict(Operator $operator): string
    {
        return $this->summarise($operator)['verdict'];
    }

    /**
     * @param  list<array{requirement: string, label: string, status: string, detail: string, expires_on: string|null}>  $items
     */
    private function verdictFrom(Operator $operator, array $items): string
    {
        // Employment is the gate in front of every other gate.
        if (! $operator->isEmployed()) {
            return self::NON_COMPLIANT;
        }

        $statuses = array_column($items, 'status');

        if (in_array(CredentialStatus::EXPIRED, $statuses, true) || in_array(CredentialStatus::MISSING, $statuses, true)) {
            return self::NON_COMPLIANT;
        }

        return in_array(CredentialStatus::EXPIRING, $statuses, true) ? self::EXPIRING : self::COMPLIANT;
    }

    /**
     * At least one current equipment licence. Which machine a specific
     * assignment needs is AssignmentEligibility's question; compliance asks
     * only whether the person is licensed to operate anything at all.
     *
     * @return array{requirement: string, label: string, status: string, detail: string, expires_on: string|null}
     */
    private function licenceItem(Operator $operator): array
    {
        $equipmentLicences = $operator->qualifications
            ->filter(fn (OperatorQualification $q): bool => $q->equipment_type !== null);

        if ($equipmentLicences->isEmpty()) {
            return $this->item('licence', 'Equipment Licence', CredentialStatus::MISSING, 'No equipment licence on record.');
        }

        $best = $this->best($equipmentLicences->values()->all());

        return $this->describedItem('licence', 'Equipment Licence', $best, 'licence');
    }

    /**
     * @return array{requirement: string, label: string, status: string, detail: string, expires_on: string|null}
     */
    private function medicalItem(Operator $operator): array
    {
        $medical = $operator->currentMedical();

        if ($medical === null) {
            return $this->item('medical', 'Medical Fitness', CredentialStatus::MISSING, 'No medical certificate on record.');
        }

        if (! $medical->isInGoodStanding()) {
            return $this->item(
                'medical',
                'Medical Fitness',
                CredentialStatus::EXPIRED,
                OperatorMedical::FITNESS_LABELS[$medical->fitness] ?? 'Not fit for duty.',
                $medical->expires_on?->toDateString(),
            );
        }

        [$status, $detail, $expires] = $this->describe($medical, 'medical');

        if ($medical->has_restrictions && $status !== CredentialStatus::EXPIRED) {
            $detail .= ' Fit with restrictions -- review before assignment.';
        }

        return $this->item('medical', 'Medical Fitness', $status, $detail, $expires);
    }

    /**
     * @return array{requirement: string, label: string, status: string, detail: string, expires_on: string|null}
     */
    private function inductionItem(Operator $operator): array
    {
        $category = ApiPayload::str(config('operators.induction_category'), 'site_induction');

        $inductions = $operator->trainings
            ->filter(fn (OperatorTraining $t): bool => strcasecmp((string) $t->category, $category) === 0);

        if ($inductions->isEmpty()) {
            return $this->item('induction', 'Site Induction', CredentialStatus::MISSING, 'No site induction on record.');
        }

        $best = $this->best($inductions->values()->all());

        return $this->describedItem('induction', 'Site Induction', $best, 'induction');
    }

    /**
     * Machine competencies are reported per equipment type but never counted
     * as "missing" -- not every operator needs one for every machine. An
     * expired one is still worth surfacing.
     *
     * @return list<array{requirement: string, label: string, status: string, detail: string, expires_on: string|null}>
     */
    private function machineCompetencyItems(Operator $operator): array
    {
        $items = [];

        $competencies = $operator->trainings
            ->filter(fn (OperatorTraining $t): bool => $t->category === OperatorTraining::CATEGORY_MACHINE_COMPETENCY)
            ->groupBy(fn (OperatorTraining $t): string => (string) $t->equipment_type);

        foreach ($competencies as $equipmentType => $group) {
            $best = $this->best($group->values()->all());
            $label = 'Competency: '.EquipmentType::label($equipmentType);

            $items[] = $this->describedItem('competency:'.$equipmentType, $label, $best, 'competency');
        }

        return $items;
    }

    /**
     * The credential that answers for a group: a current one with the latest
     * expiry if any exists, otherwise the most recently expired -- so the
     * detail line shows the nearest miss, not the oldest failure.
     *
     * @param  array<int, OperatorQualification|OperatorMedical|OperatorTraining>  $credentials
     */
    private function best(array $credentials): OperatorQualification|OperatorMedical|OperatorTraining
    {
        usort($credentials, function (OperatorQualification|OperatorMedical|OperatorTraining $a, OperatorQualification|OperatorMedical|OperatorTraining $b): int {
            if ($a->isCurrent() !== $b->isCurrent()) {
                return $a->isCurrent() ? -1 : 1;
            }

            return strcmp(
                $b->expires_on?->toDateString() ?? '9999-12-31',
                $a->expires_on?->toDateString() ?? '9999-12-31',
            );
        });

        return $credentials[0];
    }

    /**
     * A ready-made item for the credential that answers a requirement.
     *
     * @return array{requirement: string, label: string, status: string, detail: string, expires_on: string|null}
     */
    private function describedItem(
        string $requirement,
        string $label,
        OperatorQualification|OperatorMedical|OperatorTraining $credential,
        string $noun,
    ): array {
        [$status, $detail, $expires] = $this->describe($credential, $noun);

        return $this->item($requirement, $label, $status, $detail, $expires);
    }

    /**
     * @return array{0: string, 1: string, 2: string|null}
     */
    private function describe(OperatorQualification|OperatorMedical|OperatorTraining $credential, string $noun): array
    {
        $expires = $credential->expires_on?->toDateString();

        if (! $credential->isInGoodStanding()) {
            return [CredentialStatus::EXPIRED, ucfirst($noun).' is not in good standing.', $expires];
        }

        return match ($credential->expiryStatus()) {
            CredentialStatus::EXPIRED => [
                CredentialStatus::EXPIRED,
                ucfirst($noun).' expired on '.($expires ?? 'an unknown date').'.',
                $expires,
            ],
            CredentialStatus::EXPIRING => [
                CredentialStatus::EXPIRING,
                ucfirst($noun).' expires in '.(string) ($credential->daysUntilExpiry() ?? 0).' days.',
                $expires,
            ],
            default => [CredentialStatus::VALID, 'Valid'.($expires !== null ? ' until '.$expires : '').'.', $expires],
        };
    }

    /**
     * @return array{requirement: string, label: string, status: string, detail: string, expires_on: string|null}
     */
    private function item(string $requirement, string $label, string $status, string $detail, ?string $expires = null): array
    {
        return [
            'requirement' => $requirement,
            'label' => $label,
            'status' => $status,
            'detail' => $detail,
            'expires_on' => $expires,
        ];
    }

    private function required(string $requirement): bool
    {
        return (bool) data_get(config('operators.required'), $requirement, false);
    }
}
