<?php

namespace App\Livewire;

use Livewire\Component;

class Documentation extends Component
{
    public string $activeSection = 'getting-started';

    public function setSection($section)
    {
        $this->activeSection = $section;
    }

    public function render(): \Illuminate\View\View
    {
        return view('livewire.documentation');
    }
}
