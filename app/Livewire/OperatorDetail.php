<?php

namespace App\Livewire;

use App\Models\Operator;
use App\Models\OperatorMachineAssignment;
use App\Models\OperatorMedical;
use App\Models\OperatorQualification;
use App\Models\OperatorTraining;
use App\Services\Operators\OperatorCompliance;
use App\Support\EquipmentType;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * One operator's complete operational and compliance profile.
 *
 * Medical information renders only after OperatorPolicy::viewMedical passes,
 * and is edited only behind manageMedical -- the operational sections work
 * fully without it, so a fleet user sees everything they need and nothing
 * they should not.
 *
 * @psalm-suppress MissingConstructor -- Livewire injects state via mount()
 */
class OperatorDetail extends Component
{
    public Operator $operator;

    public string $activeTab = 'overview';

    public bool $showQualificationModal = false;

    public bool $showMedicalModal = false;

    public bool $showTrainingModal = false;

    /** @var array<string, string> */
    public array $qualificationForm = [
        'title' => '',
        'licence_number' => '',
        'equipment_type' => '',
        'issuing_authority' => '',
        'issued_on' => '',
        'expires_on' => '',
    ];

    /** @var array<string, string> */
    public array $medicalForm = [
        'certificate_number' => '',
        'provider' => '',
        'examined_on' => '',
        'expires_on' => '',
        'fitness' => 'fit',
        'restrictions' => '',
    ];

    /** @var array<string, string> */
    public array $trainingForm = [
        'course' => '',
        'category' => '',
        'equipment_type' => '',
        'provider' => '',
        'completed_on' => '',
        'expires_on' => '',
    ];

    public function mount(Operator $operator): void
    {
        $this->authorize('view', $operator);

        $this->operator = $operator->load(['qualifications', 'medicals', 'trainings', 'mineArea', 'supervisor']);
    }

    /**
     * @return array{verdict: string, items: list<array{requirement: string, label: string, status: string, detail: string, expires_on: string|null}>}
     */
    public function getComplianceProperty(): array
    {
        return app(OperatorCompliance::class)->summarise($this->operator);
    }

    /**
     * The authorisation matrix: for each equipment type, is this operator
     * currently licensed to operate it?
     *
     * @return list<array{type: string, label: string, authorised: bool, licence: OperatorQualification|null}>
     */
    public function getAuthorisationsProperty(): array
    {
        $rows = [];

        foreach (EquipmentType::CATALOGUE as $type => $label) {
            if ($type === EquipmentType::OTHER) {
                continue;
            }

            $licence = $this->operator->qualifications
                ->filter(fn (OperatorQualification $q): bool => $q->equipment_type === $type)
                ->sortByDesc(fn (OperatorQualification $q): string => $q->expires_on?->toDateString() ?? '9999-12-31')
                ->first();

            $rows[] = [
                'type' => $type,
                'label' => $label,
                'authorised' => $licence !== null && $licence->authorises($type),
                'licence' => $licence,
            ];
        }

        return $rows;
    }

    /**
     * @return EloquentCollection<int, OperatorMachineAssignment>
     */
    public function getAssignmentHistoryProperty(): EloquentCollection
    {
        $query = $this->operator->machineAssignments();
        $query->with(['machine', 'assignedBy', 'unassignedBy']);
        $query->orderByDesc('assigned_at');
        $query->limit(20);

        return $query->get();
    }

    public function getCanViewMedicalProperty(): bool
    {
        return auth()->user()?->can('viewMedical', $this->operator) ?? false;
    }

    public function getCanManageMedicalProperty(): bool
    {
        return auth()->user()?->can('manageMedical', $this->operator) ?? false;
    }

    public function addQualification(): void
    {
        $this->authorize('update', $this->operator);

        $this->validate([
            'qualificationForm.title' => 'required|string|max:255',
            'qualificationForm.licence_number' => 'nullable|string|max:100',
            'qualificationForm.equipment_type' => ['nullable', Rule::in(EquipmentType::all())],
            'qualificationForm.issuing_authority' => 'nullable|string|max:255',
            'qualificationForm.issued_on' => 'nullable|date',
            'qualificationForm.expires_on' => 'nullable|date|after:qualificationForm.issued_on',
        ]);

        $validated = $this->qualificationForm;

        $this->operator->qualifications()->create([
            ...$this->blanksToNull($validated),
            'team_id' => $this->operator->team_id,
        ]);

        $this->showQualificationModal = false;
        $this->reset('qualificationForm');
        $this->operator->refresh()->load(['qualifications', 'medicals', 'trainings']);
    }

    public function addMedical(): void
    {
        // Medical writes are gated separately from operator edits.
        $this->authorize('manageMedical', $this->operator);

        $this->validate([
            'medicalForm.certificate_number' => 'nullable|string|max:100',
            'medicalForm.provider' => 'nullable|string|max:255',
            'medicalForm.examined_on' => 'nullable|date',
            'medicalForm.expires_on' => 'nullable|date',
            'medicalForm.fitness' => ['required', Rule::in(array_keys(OperatorMedical::FITNESS_LABELS))],
            'medicalForm.restrictions' => 'nullable|string|max:2000',
        ]);

        $validated = $this->medicalForm;

        $restrictions = $validated['restrictions'] === '' ? null : $validated['restrictions'];

        $this->operator->medicals()->create([
            'team_id' => $this->operator->team_id,
            'certificate_number' => $validated['certificate_number'] === '' ? null : $validated['certificate_number'],
            'provider' => $validated['provider'] === '' ? null : $validated['provider'],
            'examined_on' => $validated['examined_on'] === '' ? null : $validated['examined_on'],
            'expires_on' => $validated['expires_on'] === '' ? null : $validated['expires_on'],
            'fitness' => $validated['fitness'],
            'has_restrictions' => $restrictions !== null,
            'restrictions' => $restrictions,
        ]);

        $this->showMedicalModal = false;
        $this->reset('medicalForm');
        $this->medicalForm['fitness'] = 'fit';
        $this->operator->refresh()->load(['qualifications', 'medicals', 'trainings']);
    }

    public function addTraining(): void
    {
        $this->authorize('update', $this->operator);

        $this->validate([
            'trainingForm.course' => 'required|string|max:255',
            'trainingForm.category' => ['nullable', Rule::in(array_keys(OperatorTraining::CATEGORIES))],
            'trainingForm.equipment_type' => ['nullable', Rule::in(EquipmentType::all())],
            'trainingForm.provider' => 'nullable|string|max:255',
            'trainingForm.completed_on' => 'nullable|date',
            'trainingForm.expires_on' => 'nullable|date',
        ]);

        $validated = $this->trainingForm;

        $this->operator->trainings()->create([
            ...$this->blanksToNull($validated),
            'team_id' => $this->operator->team_id,
        ]);

        $this->showTrainingModal = false;
        $this->reset('trainingForm');
        $this->operator->refresh()->load(['qualifications', 'medicals', 'trainings']);
    }

    /**
     * @param  string  $status
     */
    public function setEmploymentStatus($status): void
    {
        $this->authorize('update', $this->operator);

        if (! array_key_exists($status, Operator::STATUSES)) {
            return;
        }

        $this->operator->update(['employment_status' => $status]);
        $this->operator->refresh()->load(['qualifications', 'medicals', 'trainings']);
    }

    /**
     * Empty form fields become NULL columns, not empty strings.
     *
     * @param  array<string, string>  $fields
     * @return array<string, string|null>
     */
    private function blanksToNull(array $fields): array
    {
        return array_map(static fn (string $v): ?string => $v === '' ? null : $v, $fields);
    }

    public function render(): View
    {
        return view('livewire.operator-detail');
    }
}
