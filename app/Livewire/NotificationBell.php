<?php

namespace App\Livewire;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

class NotificationBell extends Component
{
    /** @var array<int, array<string, mixed>> */
    public array $notifications = [];

    public int $unreadCount = 0;

    public bool $open = false;

    public ?int $teamId = null;

    public function mount(): void
    {
        /** @var User|null $user */
        $user = Auth::user();
        $this->teamId = $user?->currentTeam?->id;
        $this->loadNotifications();
    }

    #[On('echo-private:team.{teamId}.notifications,notification.created')]
    public function handleNewNotification(mixed $event): void
    {
        $this->loadNotifications();
    }

    public function loadNotifications(): void
    {
        /** @var User|null $user */
        $user = Auth::user();
        $team = $user?->currentTeam;

        if ($user === null || $team === null) {
            $this->notifications = [];
            $this->unreadCount = 0;

            return;
        }

        $records = Notification::where('team_id', $team->id)
            ->latest()
            ->limit(15)
            ->with('readBy')
            ->get();

        $userId = $user->id;

        $this->notifications = $records->map(fn (Notification $n) => [
            'id' => $n->id,
            'type' => $n->type,
            'title' => $n->title,
            'message' => $n->message,
            'alert_level' => $n->alert_level,
            'action_url' => $n->action_url,
            /** @phpstan-ignore-next-line */
            'is_read' => $n->readBy->contains('id', $userId),
            'created_at' => $n->created_at?->diffForHumans(),
        ])->values()->all();

        $this->unreadCount = (int) array_reduce(
            $this->notifications,
            fn (int $carry, array $item) => $carry + (($item['is_read'] ?? true) ? 0 : 1),
            0,
        );
    }

    public function toggle(): void
    {
        $this->open = ! $this->open;

        if ($this->open) {
            $this->loadNotifications();
        }
    }

    public function markAsRead(int $notificationId): void
    {
        /** @var User|null $user */
        $user = Auth::user();
        $team = $user?->currentTeam;

        if ($user === null || $team === null) {
            return;
        }

        $notification = Notification::where('team_id', $team->id)->find($notificationId);

        if ($notification) {
            $notification->markAsRead($user->id);
            $this->loadNotifications();
        }
    }

    public function markAllAsRead(): void
    {
        /** @var User|null $user */
        $user = Auth::user();
        $team = $user?->currentTeam;

        if ($user === null || $team === null) {
            return;
        }

        $userId = $user->id;

        Notification::where('team_id', $team->id)
            ->whereDoesntHave('readBy', fn ($q) => $q->where('user_id', $userId))
            ->with('readBy')
            ->get()
            ->each(fn (Notification $n) => $n->markAsRead($userId));

        $this->loadNotifications();
    }

    public function render(): View
    {
        return view('livewire.notification-bell');
    }
}
