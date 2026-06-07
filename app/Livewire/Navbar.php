<?php

namespace App\Livewire;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Navbar extends Component
{
    public bool $profileMenuOpen = false;

    public function toggleProfileMenu(): void
    {
        $this->profileMenuOpen = ! $this->profileMenuOpen;
    }

    public function logout(): void
    {
        Auth::logout();
        redirect()->route('login');
    }

    public function render(): View
    {
        /** @var User|null $user */
        $user = Auth::user();

        return view('livewire.navbar', [
            'user' => $user,
            'team' => $user?->currentTeam,
        ]);
    }
}
