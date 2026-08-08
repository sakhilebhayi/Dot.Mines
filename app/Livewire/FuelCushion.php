<?php

namespace App\Livewire;

use App\Models\Team;
use App\Services\FuelReserveRunwayCalculator;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class FuelCushion extends Component
{
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
        return Auth::user()?->currentTeam;
    }

    public function render()
    {
        return view('livewire.fuel-cushion');
    }
}
