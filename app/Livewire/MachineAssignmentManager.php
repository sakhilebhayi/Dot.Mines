<?php

namespace App\Livewire;

use App\Actions\MineAreas\AssignMachineToArea;
use App\Actions\MineAreas\UnassignMachineFromArea;
use App\Livewire\Concerns\NotifiesUser;
use App\Models\Machine;
use App\Models\MachineAreaAssignment;
use App\Models\MineArea;
use App\Models\Team;
use App\Models\User;
use App\Support\ApiPayload;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Per-mine-area machine assignment dashboard.
 *
 * Rebuilt from resources/views/livewire/machine-assignment-manager/*.blade.php,
 * whose backing class (App\Livewire\MachineAssignmentManager) had been
 * deleted -- the views were orphaned and the page was completely
 * unreachable (no route, php artisan livewire:verify already flagged the
 * missing class). The original views assumed a many-to-many
 * machine<->mine-area pivot (`$machine->pivot->assigned_at`), but the app's
 * actual data model is one current mine area per machine
 * (Machine::mine_area_id, NOT NULL) plus a MachineAreaAssignment history
 * log -- the exact pattern MineAreaDetail::assignMachine()/unassignMachine()
 * already use. This rebuild follows that same pattern instead of the
 * phantom pivot the deleted class apparently never actually had either.
 *
 * @psalm-suppress MissingConstructor -- Livewire injects state via mount()
 */
class MachineAssignmentManager extends Component
{
    use NotifiesUser;
    use WithPagination;

    public MineArea $mineArea;

    public string $view = 'overview';

    public string $searchTerm = '';

    public string $filterStatus = '';

    public bool $selectAll = false;

    /** @var array<int, string> */
    public array $selectedMachineIds = [];

    public string $selectedNotes = '';

    public bool $showAssignModal = false;

    public ?int $selectedMachineId = null;

    public function mount(MineArea $mineArea): void
    {
        /** @var User $authUser */
        $authUser = Auth::user();
        $team = $authUser->currentTeam;

        if ($team === null) {
            abort(403);
        }
        abort_unless($mineArea->team_id === $team->id, 404);

        $this->mineArea = $mineArea;
    }

    public function switchToOverview(): void
    {
        $this->view = 'overview';
        $this->resetSelection();
    }

    public function switchToManage(): void
    {
        $this->view = 'manage';
        $this->resetSelection();
    }

    public function switchToAssign(): void
    {
        $this->view = 'assign';
        $this->resetSelection();
    }

    public function switchToHistory(): void
    {
        $this->view = 'history';
    }

    protected function resetSelection(): void
    {
        $this->selectedMachineIds = [];
        $this->selectAll = false;
        $this->searchTerm = '';
        $this->filterStatus = '';
    }

    public function toggleSelectAll(): void
    {
        if (! $this->selectAll) {
            $this->selectedMachineIds = [];

            return;
        }

        /** @var User $authUser */
        $authUser = Auth::user();
        $team = $authUser->currentTeam;

        if ($team === null) {
            abort(403);
        }

        if ($this->view === 'assign') {
            $this->selectedMachineIds = $this->assignableMachinesQuery($team)
                ->pluck('id')->map(fn ($id): string => (string) $id)->values()->all();
        } else {
            $this->selectedMachineIds = Machine::where('team_id', $team->id)
                ->where('mine_area_id', $this->mineArea->id)
                ->when($this->searchTerm, fn (Builder $q): mixed => $q->where(function ($q) {
                    $q->where('name', 'like', "%{$this->searchTerm}%")
                        ->orWhere('model', 'like', "%{$this->searchTerm}%");
                }))
                ->pluck('id')->map(fn ($id): string => (string) $id)->values()->all();
        }
    }

    public function showAssignForm(int $machineId): void
    {
        $this->selectedMachineId = $machineId;
        $this->selectedNotes = '';
        $this->showAssignModal = true;
    }

    public function cancelAssignForm(): void
    {
        $this->showAssignModal = false;
        $this->selectedMachineId = null;
        $this->selectedNotes = '';
    }

    public function assignSingleMachine(int $machineId): void
    {
        /** @var User $authUser */
        $authUser = Auth::user();
        $team = $authUser->currentTeam;

        if ($team === null) {
            abort(403);
        }
        $machine = Machine::where('team_id', $team->id)->findOrFail($machineId);
        $this->authorize('update', $machine);

        $this->assign($machine, $this->selectedNotes ?: null);
        $this->cancelAssignForm();
    }

    public function assignSelectedMachines(): void
    {
        /** @var User $authUser */
        $authUser = Auth::user();
        $team = $authUser->currentTeam;

        if ($team === null) {
            abort(403);
        }
        $machines = Machine::where('team_id', $team->id)
            ->whereIn('id', $this->selectedMachineIds)
            ->get();

        foreach ($machines as $machine) {
            $this->authorize('update', $machine);
            $this->assign($machine, null);
        }

        $count = $machines->count();
        $this->resetSelection();
        $this->notify("{$count} machine(s) assigned to {$this->mineArea->name}", 'success');
    }

    private function assign(Machine $machine, ?string $notes): void
    {
        /** @var User $authUser */
        $authUser = Auth::user();

        $team = $authUser->currentTeam;

        if ($team === null) {
            abort(403);
        }

        app(AssignMachineToArea::class)->execute(
            $team,
            $this->mineArea,
            $machine,
            $authUser->id,
            null,
            $notes,
        );
    }

    public function unassignMachine(int $machineId): void
    {
        /** @var User $authUser */
        $authUser = Auth::user();
        $team = $authUser->currentTeam;

        if ($team === null) {
            abort(403);
        }
        $machine = Machine::where('team_id', $team->id)
            ->where('mine_area_id', $this->mineArea->id)
            ->findOrFail($machineId);
        $this->authorize('update', $machine);

        if (! $this->moveToAnotherArea($machine)) {
            $this->notify("Cannot unassign {$machine->name}; at least one active mine area must be set. Create another mine area first.", 'error');

            return;
        }

        $this->notify("{$machine->name} removed from {$this->mineArea->name}", 'success');
    }

    public function unassignMultipleMachines(): void
    {
        /** @var User $authUser */
        $authUser = Auth::user();
        $team = $authUser->currentTeam;

        if ($team === null) {
            abort(403);
        }
        $machines = Machine::where('team_id', $team->id)
            ->where('mine_area_id', $this->mineArea->id)
            ->whereIn('id', $this->selectedMachineIds)
            ->get();

        $moved = 0;
        foreach ($machines as $machine) {
            $this->authorize('update', $machine);
            if ($this->moveToAnotherArea($machine)) {
                $moved++;
            }
        }

        $this->resetSelection();

        if ($moved === 0) {
            $this->notify('Cannot unassign; at least one active mine area must be set.', 'error');

            return;
        }

        $this->notify("{$moved} machine(s) removed from {$this->mineArea->name}", 'success');
    }

    /**
     * Machine::mine_area_id is NOT NULL, so a machine can never be truly
     * unassigned -- "removing" it from this area means moving it to another
     * active area (shared with MineAreaDetail via the Action).
     */
    private function moveToAnotherArea(Machine $machine): bool
    {
        /** @var User $authUser */
        $authUser = Auth::user();

        $team = $authUser->currentTeam;

        if ($team === null) {
            abort(403);
        }

        return app(UnassignMachineFromArea::class)
            ->execute($team, $this->mineArea, $machine, $authUser->id) !== null;
    }

    public function exportAssignmentReport(): StreamedResponse
    {
        /** @var User $authUser */
        $authUser = Auth::user();
        $team = $authUser->currentTeam;

        if ($team === null) {
            abort(403);
        }
        $this->authorize('viewAny', Machine::class);

        $machines = Machine::where('team_id', $team->id)
            ->where('mine_area_id', $this->mineArea->id)
            ->get();

        $rows = ["Machine,Model,Status\n"];
        foreach ($machines as $machine) {
            $rows[] = sprintf("%s,%s,%s\n", $machine->name, ApiPayload::str($machine->model), $machine->status);
        }

        $filename = str($this->mineArea->name)->slug()->toString().'-assignments-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($rows) {
            echo implode('', $rows);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * @return Builder<Machine>
     */
    private function assignableMachinesQuery(Team $team): Builder
    {
        return Machine::where('team_id', $team->id)
            ->where('mine_area_id', '!=', $this->mineArea->id)
            ->when($this->searchTerm, fn (Builder $q): mixed => $q->where(function ($q) {
                $q->where('name', 'like', "%{$this->searchTerm}%")
                    ->orWhere('model', 'like', "%{$this->searchTerm}%");
            }))
            ->when($this->filterStatus, fn (Builder $q): mixed => $q->where('status', $this->filterStatus));
    }

    public function render(): View
    {
        /** @var User $authUser */
        $authUser = Auth::user();
        $team = $authUser->currentTeam;

        if ($team === null) {
            abort(403);
        }

        $totalMachines = Machine::where('team_id', $team->id)->count();

        $assignedMachines = Machine::where('team_id', $team->id)
            ->where('mine_area_id', $this->mineArea->id)
            ->when($this->view === 'manage' && $this->searchTerm, fn (Builder $q): mixed => $q->where(function ($q) {
                $q->where('name', 'like', "%{$this->searchTerm}%")
                    ->orWhere('model', 'like', "%{$this->searchTerm}%");
            }))
            ->get();

        // Latest active assignment record per assigned machine, for "assigned since" display.
        $assignmentByMachine = MachineAreaAssignment::where('mine_area_id', $this->mineArea->id)
            ->whereIn('machine_id', $assignedMachines->pluck('id'))
            ->whereNull('unassigned_at')
            ->get()
            ->keyBy('machine_id');

        $unassignedCount = Machine::where('team_id', $team->id)
            ->where('mine_area_id', '!=', $this->mineArea->id)
            ->count();

        $machines = $this->view === 'assign'
            ? $this->assignableMachinesQuery($team)->paginate(10)
            : null;

        $selectedMachine = ($this->selectedMachineId !== null && $this->selectedMachineId !== 0)
            ? Machine::where('team_id', $team->id)->find($this->selectedMachineId)
            : null;

        $assignmentHistory = MachineAreaAssignment::where('mine_area_id', $this->mineArea->id)
            ->whereNotNull('unassigned_at')
            ->with('machine')
            ->orderByDesc('unassigned_at')
            ->get();

        return view('livewire.machine-assignment-manager.index', [
            'totalMachines' => $totalMachines,
            'assignedMachines' => $assignedMachines,
            'assignmentByMachine' => $assignmentByMachine,
            'unassignedCount' => $unassignedCount,
            'machines' => $machines,
            'selectedMachine' => $selectedMachine,
            'assignmentHistory' => $assignmentHistory,
        ]);
    }
}
