<?php

namespace App\Livewire;

use Livewire\Component;

class Sidebar extends Component
{
    public bool $sidebarOpen = true;

    public function toggleSidebar(): void
    {
        $this->sidebarOpen = ! $this->sidebarOpen;
    }

    public function render(): \Illuminate\View\View
    {
        return view('livewire.sidebar');
    }
}
