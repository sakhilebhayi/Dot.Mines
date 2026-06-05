<?php

namespace App\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Navbar extends Component
{
    public bool $profileMenuOpen = false;

    public bool $notificationsOpen = false;

    public function toggleProfileMenu(): void
    {
        $this->profileMenuOpen = ! $this->profileMenuOpen;
    }

    public function toggleNotifications(): void
    {
        $this->notificationsOpen = ! $this->notificationsOpen;
    }

    public function logout(): void
    {
        Auth::logout();
        redirect()->route('login');
    }

    public function render(): View
    {
        return view('livewire.navbar', [
            'user' => Auth::user(),
            'team' => Auth::user()?->currentTeam,
        ]);
    }
}
