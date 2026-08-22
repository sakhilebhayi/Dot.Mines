<?php

namespace App\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Component;

class Sidebar extends Component
{
    public bool $sidebarOpen = true;

    public function toggleSidebar(): void
    {
        $this->sidebarOpen = ! $this->sidebarOpen;
    }

    public function render(): View
    {
        return view('livewire.sidebar');
    }
}
