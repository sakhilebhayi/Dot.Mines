<?php

namespace App\Livewire;

use App\Models\AIPredictiveAlert;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

class AINotifications extends Component
{
    public Collection $notifications;

    public int $unreadCount = 0;

    public bool $showPanel = false;

    public function mount(): void
    {
        $this->notifications = new Collection;
        $this->loadNotifications();
    }

    #[On('alert-created')]
    public function loadNotifications(): void
    {
        $team = Auth::user()->currentTeam;

        if ($team === null) {
            $this->notifications = new Collection;
            $this->unreadCount = 0;

            return;
        }

        $this->notifications = AIPredictiveAlert::where('team_id', $team->id)
            ->where('is_acknowledged', false)
            ->orderByDesc('created_at')
            ->limit(10)
            ->with('aiAgent')
            ->get();

        $this->unreadCount = $this->notifications->count();
    }

    public function togglePanel(): void
    {
        $this->showPanel = ! $this->showPanel;
    }

    public function acknowledge(int $alertId): void
    {
        $team = Auth::user()->currentTeam;

        if ($team === null) {
            return;
        }

        $alert = AIPredictiveAlert::where('team_id', $team->id)->find($alertId);

        if ($alert) {
            $alert->update([
                'is_acknowledged' => true,
                'acknowledged_at' => now(),
                'acknowledged_by' => Auth::id(),
            ]);

            $this->loadNotifications();

            $this->dispatch('alert-acknowledged', alertId: $alertId);
        }
    }

    public function acknowledgeAll(): void
    {
        $team = Auth::user()->currentTeam;

        if ($team === null) {
            return;
        }

        AIPredictiveAlert::where('team_id', $team->id)
            ->where('is_acknowledged', false)
            ->update([
                'is_acknowledged' => true,
                'acknowledged_at' => now(),
                'acknowledged_by' => Auth::id(),
            ]);

        $this->loadNotifications();
    }

    public function render(): View
    {
        return view('livewire.ai-notifications');
    }
}
