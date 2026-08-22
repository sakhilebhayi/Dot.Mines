<?php

namespace App\Livewire;

use App\Models\Team;
use App\Models\User;
use App\Services\FuelReserveRunwayCalculator;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class FuelCushion extends Component
{
    /** @var array<string, mixed> */
    public array $cushion = [];

    public function mount(): void
    {
        if (! $this->resolveCurrentTeam()) {
            return;
        }

        $this->cushion = (new FuelReserveRunwayCalculator)->calculate();
    }

    private function resolveCurrentTeam(): ?Team
    {
        $user = Auth::user();

        return $user instanceof User ? $user->currentTeam : null;
    }

    public function render(): View
    {
        return view('livewire.fuel-cushion');
    }
}
