<?php

namespace App\Livewire;

use App\Models\Machine;
use App\Models\MachineAreaAssignment;
use App\Models\MineArea;
use App\Traits\BrowserEventBridge;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

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
 */
class MachineAssignmentManager extends Component
{
    use BrowserEventBridge, WithPagination;

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
        $team = Auth::user()->currentTeam;
        abort_unless($team && $mineArea->team_id === $team->id, 404);

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

        $team = Auth::user()->currentTeam;

        if ($this->view === 'assign') {
            $this->selectedMachineIds = $this->assignableMachinesQuery($team)
                ->pluck('id')->map(fn ($id) => (string) $id)->toArray();
        } else {
            $this->selectedMachineIds = Machine::where('team_id', $team->id)
                ->where('mine_area_id', $this->mineArea->id)
                ->when($this->searchTerm, fn ($q) => $q->where(function ($q) {
                    $q->where('name', 'like', "%{$this->searchTerm}%")
                        ->orWhere('model', 'like', "%{$this->searchTerm}%");
                }))
                ->pluck('id')->map(fn ($id) => (string) $id)->toArray();
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
        $team = Auth::user()->currentTeam;
        $machine = Machine::where('team_id', $team->id)->findOrFail($machineId);
        $this->authorize('update', $machine);

        $this->assign($machine, $this->selectedNotes ?: null);
        $this->cancelAssignForm();
    }

    public function assignSelectedMachines(): void
    {
        $team = Auth::user()->currentTeam;
        $machines = Machine::where('team_id', $team->id)
            ->whereIn('id', $this->selectedMachineIds)
            ->get();

        foreach ($machines as $machine) {
            $this->authorize('update', $machine);
            $this->assign($machine, null);
        }

        $count = $machines->count();
        $this->resetSelection();
        $this->dispatchBrowserEvent('notify', ['message' => "{$count} machine(s) assigned to {$this->mineArea->name}", 'type' => 'success']);
    }

    private function assign(Machine $machine, ?string $notes): void
    {
        $team = Auth::user()->currentTeam;

        if ($machine->mine_area_id === $this->mineArea->id) {
            return;
        }

        $machine->update(['mine_area_id' => $this->mineArea->id]);

        MachineAreaAssignment::create([
            'team_id' => $team->id,
            'machine_id' => $machine->id,
            'mine_area_id' => $this->mineArea->id,
            'assigned_by' => Auth::id(),
            'assigned_at' => now(),
            'notes' => $notes,
        ]);
    }

    public function unassignMachine(int $machineId): void
    {
        $team = Auth::user()->currentTeam;
        $machine = Machine::where('team_id', $team->id)
            ->where('mine_area_id', $this->mineArea->id)
            ->findOrFail($machineId);
        $this->authorize('update', $machine);

        if (! $this->moveToAnotherArea($machine, $team->id)) {
            $this->dispatchBrowserEvent('notify', [
                'message' => "Cannot unassign {$machine->name}; at least one active mine area must be set. Create another mine area first.",
                'type' => 'error',
            ]);

            return;
        }

        $this->dispatchBrowserEvent('notify', ['message' => "{$machine->name} removed from {$this->mineArea->name}", 'type' => 'success']);
    }

    public function unassignMultipleMachines(): void
    {
        $team = Auth::user()->currentTeam;
        $machines = Machine::where('team_id', $team->id)
            ->where('mine_area_id', $this->mineArea->id)
            ->whereIn('id', $this->selectedMachineIds)
            ->get();

        $moved = 0;
        foreach ($machines as $machine) {
            $this->authorize('update', $machine);
            if ($this->moveToAnotherArea($machine, $team->id)) {
                $moved++;
            }
        }

        $this->resetSelection();

        if ($moved === 0) {
            $this->dispatchBrowserEvent('notify', ['message' => 'Cannot unassign; at least one active mine area must be set.', 'type' => 'error']);

            return;
        }

        $this->dispatchBrowserEvent('notify', ['message' => "{$moved} machine(s) removed from {$this->mineArea->name}", 'type' => 'success']);
    }

    /**
     * Machine::mine_area_id is NOT NULL, so a machine can never be truly
     * unassigned -- "removing" it from this area means moving it to another
     * active area. Mirrors MineAreaDetail::unassignMachine().
     */
    private function moveToAnotherArea(Machine $machine, int $teamId): bool
    {
        $otherArea = MineArea::where('team_id', $teamId)
            ->where('status', 'active')
            ->where('id', '!=', $this->mineArea->id)
            ->first();

        if (! $otherArea) {
            return false;
        }

        MachineAreaAssignment::where('machine_id', $machine->id)
            ->where('mine_area_id', $this->mineArea->id)
            ->whereNull('unassigned_at')
            ->update(['unassigned_at' => now()]);

        $machine->update(['mine_area_id' => $otherArea->id]);

        MachineAreaAssignment::create([
            'team_id' => $teamId,
            'machine_id' => $machine->id,
            'mine_area_id' => $otherArea->id,
            'assigned_by' => Auth::id(),
            'assigned_at' => now(),
            'reason' => 'Removed from '.$this->mineArea->name,
        ]);

        return true;
    }

    public function exportAssignmentReport()
    {
        $team = Auth::user()->currentTeam;
        $this->authorize('viewAny', Machine::class);

        $machines = Machine::where('team_id', $team->id)
            ->where('mine_area_id', $this->mineArea->id)
            ->get();

        $rows = ["Machine,Model,Status\n"];
        foreach ($machines as $machine) {
            $rows[] = sprintf("%s,%s,%s\n", $machine->name, $machine->model, $machine->status);
        }

        $filename = str($this->mineArea->name)->slug().'-assignments-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($rows) {
            echo implode('', $rows);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    private function assignableMachinesQuery($team)
    {
        return Machine::where('team_id', $team->id)
            ->where('mine_area_id', '!=', $this->mineArea->id)
            ->when($this->searchTerm, fn ($q) => $q->where(function ($q) {
                $q->where('name', 'like', "%{$this->searchTerm}%")
                    ->orWhere('model', 'like', "%{$this->searchTerm}%");
            }))
            ->when($this->filterStatus, fn ($q) => $q->where('status', $this->filterStatus));
    }

    public function render()
    {
        $team = Auth::user()->currentTeam;

        $totalMachines = Machine::where('team_id', $team->id)->count();

        $assignedMachines = Machine::where('team_id', $team->id)
            ->where('mine_area_id', $this->mineArea->id)
            ->when($this->view === 'manage' && $this->searchTerm, fn ($q) => $q->where(function ($q) {
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

        $selectedMachine = $this->selectedMachineId
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
