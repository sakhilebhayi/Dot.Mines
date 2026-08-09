<?php

namespace App\Livewire;

use App\Models\AIPredictiveAlert;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\On;
use Livewire\Component;

class AINotifications extends Component
{
    /** @var Collection<int, AIPredictiveAlert> */
    public Collection $notifications;

    public int $unreadCount = 0;

    public bool $showPanel = false;

    public function mount(): void
    {
        $this->loadNotifications();
    }

    #[On('alert-created')]
    public function loadNotifications(): void
    {
        $team = auth()->user()->currentTeam;

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
        $team = auth()->user()->currentTeam;
        $alert = AIPredictiveAlert::where('team_id', $team->id)->find($alertId);

        if ($alert) {
            $alert->update([
                'is_acknowledged' => true,
                'acknowledged_at' => now(),
                'acknowledged_by' => auth()->id(),
            ]);

            $this->loadNotifications();

            $this->dispatch('alert-acknowledged', alertId: $alertId);
        }
    }

    public function acknowledgeAll(): void
    {
        $team = auth()->user()->currentTeam;

        AIPredictiveAlert::where('team_id', $team->id)
            ->where('is_acknowledged', false)
            ->update([
                'is_acknowledged' => true,
                'acknowledged_at' => now(),
                'acknowledged_by' => auth()->id(),
            ]);

        $this->loadNotifications();
    }

    public function render()
    {
        return view('livewire.ai-notifications');
    }
}
