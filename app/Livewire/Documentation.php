<?php

namespace App\Livewire;

use Illuminate\View\View;
use Livewire\Component;

class Documentation extends Component
{
    public string $activeSection = 'getting-started';

    public function setSection($section): void
    {
        $this->activeSection = $section;
    }

    public function render(): View
    {
        return view('livewire.documentation');
    }
}
