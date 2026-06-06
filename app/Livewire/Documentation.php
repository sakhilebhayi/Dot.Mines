<?php

namespace App\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Component;

class Documentation extends Component
{
    public string $activeSection = 'onboarding';

    public string $search = '';

    /** @var array<string, bool> */
    public array $checklist = [
        'add_user' => false,
        'add_machine' => false,
        'add_fuel_tank' => false,
        'add_geofence' => false,
        'add_mine_area' => false,
        'add_maintenance' => false,
    ];

    public function setSection(string $section): void
    {
        $this->activeSection = $section;
        $this->search = '';
    }

    public function toggleCheck(string $key): void
    {
        if (array_key_exists($key, $this->checklist)) {
            $this->checklist[$key] = ! $this->checklist[$key];
        }
    }

    public function completedCount(): int
    {
        return count(array_filter($this->checklist));
    }

    public function render(): View
    {
        return view('livewire.documentation');
    }
}
