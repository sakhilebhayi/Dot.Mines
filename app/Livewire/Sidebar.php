<?php

namespace App\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * The product navigation. Pure markup: the desktop rail choice and the phone drawer are script and CSS (resources/js/app.js,
 * dot-ui adopt mode), so nothing here round-trips to the server.
 */
class Sidebar extends Component
{
    public function render(): View
    {
        return view('livewire.sidebar');
    }
}
