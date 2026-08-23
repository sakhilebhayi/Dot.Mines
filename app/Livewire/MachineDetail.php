<?php

namespace App\Livewire;

use App\Models\Machine;
use App\Services\OperationalSnapshotService;
use App\Support\CurrentUser;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Lazy;
use Livewire\Component;

/**
 * @psalm-suppress MissingConstructor -- Livewire injects state via mount()
 */
#[Lazy]
class MachineDetail extends Component
{
    /**
     * Skeleton shown while this page lazy-loads -- the page shell paints
     * immediately instead of blocking on mount()'s data queries.
     *
     * @psalm-suppress PossiblyUnusedMethod -- invoked by Livewire's lazy-loading lifecycle
     */
    public function placeholder(): View
    {
        return view('livewire.placeholders.detail');
    }

    public Machine $machine;

    public function mount(Machine $machine): void
    {
        if ($machine->team_id !== CurrentUser::get()?->currentTeam?->id) {
            abort(403);
        }
        $this->machine = $machine;
    }

    public function render(): View
    {
        $metrics = $this->machine->metrics()
            ->latest('created_at')
            ->take(10)
            ->get();

        $recentAlerts = $this->machine->alerts()
            ->latest('created_at')
            ->take(5)
            ->get();

        return view('livewire.machine-detail', [
            'metrics' => $metrics,
            'recentAlerts' => $recentAlerts,
            'snapshot' => app(OperationalSnapshotService::class)->forMachine($this->machine),
        ]);
    }
}
