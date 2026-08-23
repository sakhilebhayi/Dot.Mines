<?php

namespace App\Livewire;

use App\Models\Operator;
use App\Services\Operators\OperatorCompliance;
use App\Support\EquipmentType;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * The central place for managing the people who operate the fleet.
 *
 * Compliance filtering happens in PHP rather than SQL on purpose: the verdict
 * is computed by OperatorCompliance from several credential tables and config
 * (never stored), and duplicating that logic as SQL would create the second
 * source of truth the whole design exists to avoid. The team-sized lists this
 * serves (tens of operators, not thousands) make that a good trade.
 */
class OperatorManager extends Component
{
    use WithPagination;

    public string $search = '';

    public string $statusFilter = '';

    public string $complianceFilter = '';

    public string $equipmentFilter = '';

    public bool $showCreateModal = false;

    /** @var array<string, string> */
    public array $form = [
        'employee_number' => '',
        'first_name' => '',
        'last_name' => '',
        'phone' => '',
        'department' => '',
        'job_title' => '',
        'default_shift' => 'day',
    ];

    public function mount(): void
    {
        $this->authorize('viewAny', Operator::class);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedComplianceFilter(): void
    {
        $this->resetPage();
    }

    public function updatedEquipmentFilter(): void
    {
        $this->resetPage();
    }

    /**
     * Operators with their verdicts, filtered.
     *
     * @return Collection<int, array{operator: Operator, verdict: string}>
     */
    public function getRowsProperty(): Collection
    {
        $query = Operator::query()->with(['qualifications', 'medicals', 'trainings', 'mineArea']);

        if ($this->search !== '') {
            $term = '%'.strtolower($this->search).'%';
            $query->where(function (Builder $q) use ($term): void {
                $q->whereRaw('LOWER(first_name) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(last_name) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(preferred_name) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(employee_number) LIKE ?', [$term]);
            });
        }

        if ($this->statusFilter !== '') {
            $query->where('employment_status', $this->statusFilter);
        }

        if ($this->equipmentFilter !== '') {
            // Normalised so a raw machine_type pasted from a machine record
            // ("haul truck", "Bulldozer") still matches canonical licences.
            $wanted = EquipmentType::normalise($this->equipmentFilter);
            $query->whereHas('qualifications', function (\Illuminate\Contracts\Database\Query\Builder $q) use ($wanted): void {
                $q->where('equipment_type', $wanted);
            });
        }

        $compliance = app(OperatorCompliance::class);

        /**
         * @var \Illuminate\Database\Eloquent\Collection<int, Operator> $operators
         *
         * @psalm-suppress UnnecessaryVarAnnotation -- phpstan loses the model
         * generic through the where() closures; psalm keeps it.
         */
        $operators = $query->orderBy('last_name')->get();

        $rows = $operators
            ->map(fn (Operator $operator): array => [
                'operator' => $operator,
                'verdict' => $compliance->verdict($operator),
            ]);

        if ($this->complianceFilter !== '') {
            $rows = $rows
                ->filter(fn (array $row): bool => $row['verdict'] === $this->complianceFilter)
                ->values();
        }

        return $rows;
    }

    /**
     * The headline numbers: how many operators, and how many are in trouble.
     *
     * @return array{total: int, compliant: int, expiring: int, non_compliant: int}
     */
    public function getCountsProperty(): array
    {
        $verdicts = $this->getRowsProperty()
            ->map(fn (array $row): string => $row['verdict']);

        return [
            'total' => $verdicts->count(),
            'compliant' => $verdicts->filter(fn (string $v): bool => $v === OperatorCompliance::COMPLIANT)->count(),
            'expiring' => $verdicts->filter(fn (string $v): bool => $v === OperatorCompliance::EXPIRING)->count(),
            'non_compliant' => $verdicts->filter(fn (string $v): bool => $v === OperatorCompliance::NON_COMPLIANT)->count(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function getEquipmentTypesProperty(): array
    {
        return EquipmentType::CATALOGUE;
    }

    public function create(): void
    {
        $this->authorize('create', Operator::class);

        $this->validate([
            'form.employee_number' => 'required|string|max:50',
            'form.first_name' => 'required|string|max:100',
            'form.last_name' => 'required|string|max:100',
            'form.phone' => 'nullable|string|max:50',
            'form.department' => 'nullable|string|max:100',
            'form.job_title' => 'nullable|string|max:100',
            'form.default_shift' => 'required|in:day,night',
        ]);

        // The typed property holds exactly what just passed validation.
        $validated = $this->form;

        $teamId = auth()->user()?->current_team_id;

        $exists = Operator::query()
            ->where('employee_number', $validated['employee_number'])
            ->exists();

        if ($exists) {
            $this->addError('form.employee_number', 'An operator with this employee number already exists.');

            return;
        }

        $operator = Operator::create([...$validated, 'team_id' => $teamId]);

        $this->showCreateModal = false;
        $this->reset('form');

        $this->redirectRoute('operators.show', ['operator' => $operator->id]);
    }

    public function render(): View
    {
        return view('livewire.operator-manager');
    }
}
