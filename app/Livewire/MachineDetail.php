<?php

namespace App\Livewire;

use App\Models\Machine;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Component;

class MachineDetail extends Component
{
    public Machine $machine;

    public function mount(Machine $machine): void
    {
        if ($machine->team_id !== Auth::user()->currentTeam->id) {
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
        ]);
    }
}
