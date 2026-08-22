<?php

namespace App\Livewire;

use App\Models\User;
use App\Traits\RealtimeUpdates;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Navbar extends Component
{
    use RealtimeUpdates;

    public bool $profileMenuOpen = false;

    /**
     * The navbar (and the notification bell it hosts) is present on every
     * authenticated page, so real-time subscriptions are initialized here
     * rather than on any single page component -- alerts/toasts previously
     * only fired while a user happened to be on the one page that
     * subscribed to them.
     */
    public function mount(): void
    {
        if (! Auth::check()) {
            return;
        }

        $this->initializeRealtimeUpdates();
        $this->subscribeToTeamAlerts();
        $this->subscribeToMaintenanceAlerts();
        $this->subscribeToComplianceViolations();
    }

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
        $user = Auth::user();

        return view('livewire.navbar', [
            'user' => $user,
            'team' => $user instanceof User ? $user->currentTeam : null,
        ]);
    }
}
