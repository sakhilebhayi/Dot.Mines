<?php

namespace App\Livewire;

use App\Models\AIPredictiveAlert;
use App\Models\Alert;
use App\Models\FuelAlert;
use App\Models\Notification;
use App\Support\ApiPayload;
use App\Support\CurrentUser;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * The notification bell. Used to show only AIPredictiveAlert rows -- real
 * operational alerts (machine/geofence, the Alert model) and fuel alerts
 * (tank critical, budget exceeded, the FuelAlert model) had no unified view
 * anywhere in the app despite both being real, live data with their own
 * severity/status/acknowledge lifecycle. A user could have a machine
 * actually offline right now and the bell would show nothing, or "no unread
 * alerts" while a fuel budget was exceeded. Now merges all four sources
 * (also App\Models\Notification -- RealTimeAlertService already persists a
 * Notification row for maintenance/compliance/sensor-reading alerts, but
 * nothing here ever read them until now).
 *
 * Also now respects User::wantsInAppAlert() (the "In-App Alerts" toggle and
 * quiet hours), neither of which anything previously read.
 *
 * Each notification is a plain array, not a value object -- Livewire's
 * collection synthesizer expects Eloquent models (it calls getMorphClass()
 * on hydration) or plain arrays, and throws on stdClass/DTOs.
 */
/**
 * @psalm-suppress MissingConstructor -- Livewire initializes public state in mount()/loadNotifications()
 */
class AINotifications extends Component
{
    /** @var Collection<int, array<string, mixed>> */
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
        $team = auth()->user()?->currentTeam;

        if (! $team) {
            $this->notifications = collect();
            $this->unreadCount = 0;

            return;
        }

        $aiAlerts = AIPredictiveAlert::where('team_id', $team->id)
            ->where('is_acknowledged', false)
            ->with('machine')
            ->latest('created_at')
            ->limit(100)
            ->get()
            ->toBase()
            ->map(fn (AIPredictiveAlert $a) => [
                'source' => 'ai',
                'id' => $a->id,
                'severity' => $a->severity,
                'title' => $a->title,
                'message' => $a->description,
                'created_at' => $a->created_at,
                'location' => $a->machine?->name,
            ]);

        $operationalAlerts = Alert::where('team_id', $team->id)
            ->active()
            ->with('machine', 'mineArea')
            ->latest('triggered_at')
            ->limit(100)
            ->get()
            ->toBase()
            ->map(fn (Alert $a) => [
                'source' => 'alert',
                'id' => $a->id,
                'severity' => $a->priority,
                'title' => $a->title,
                'message' => $a->description,
                'created_at' => $a->triggered_at,
                'location' => $a->machine?->name ?? $a->mineArea?->name,
            ]);

        $fuelAlerts = FuelAlert::where('team_id', $team->id)
            ->active()
            ->with('machine', 'fuelTank')
            ->latest('triggered_at')
            ->limit(100)
            ->get()
            ->toBase()
            ->map(fn (FuelAlert $a) => [
                'source' => 'fuel_alert',
                'id' => $a->id,
                'severity' => $a->severity,
                'title' => $a->title,
                'message' => $a->message,
                'created_at' => $a->triggered_at,
                'location' => $a->machine?->name ?? $a->fuelTank?->name,
            ]);

        $notificationAlerts = Notification::where('team_id', $team->id)
            ->whereDoesntHave('readBy', fn (Builder $q): mixed => $q->where('user_id', auth()->id()))
            ->latest('created_at')
            ->limit(100)
            ->get()
            ->toBase()
            ->map(fn (Notification $n) => [
                'source' => 'notification',
                'id' => $n->id,
                'severity' => $n->alert_level,
                'title' => $n->title,
                'message' => $n->message,
                'created_at' => $n->created_at,
                'location' => null,
            ]);

        $user = CurrentUser::get();

        if ($user === null) {
            $this->notifications = collect();
            $this->unreadCount = 0;

            return;
        }

        $minSeverity = ApiPayload::str($user->notification_preferences['min_severity'] ?? null, 'low');

        /**
         * @var Collection<int, array<string, mixed>> $visible
         *
         * @psalm-suppress RedundantConditionGivenDocblockType, DocblockTypeContradiction
         * Psalm infers severity as string from the map shapes above; phpstan
         * sees array<string, mixed>, so the is_string check must stay.
         */
        $visible = $aiAlerts
            ->concat($operationalAlerts)
            ->concat($fuelAlerts)
            ->concat($notificationAlerts)
            ->filter(fn (array $n): bool => $this->meetsSeverityThreshold($n['severity'], $minSeverity))
            // "In-App Alerts" and quiet hours used to be stored preferences
            // that nothing ever read -- toggling either off in Settings had
            // no effect on what the bell showed.
            ->filter(fn (array $n): bool => $user->wantsInAppAlert(is_string($n['severity']) ? $n['severity'] : null))
            ->sortByDesc(fn (array $n) => $n['created_at']);

        // Badge = everything unread that passed the filters, not just the 10
        // rows the panel shows -- the count used to silently max out at 10.
        $this->unreadCount = $visible->count();

        $this->notifications = $visible->take(10)->values();
    }

    /**
     * 'critical' always passes regardless of the user's threshold -- a
     * notification preference is a convenience filter, not a way to
     * accidentally hide something that genuinely needs immediate attention.
     */
    private function meetsSeverityThreshold(mixed $severity, string $minSeverity): bool
    {
        if ($severity === 'critical') {
            return true;
        }

        $rank = ['low' => 0, 'warning' => 1, 'medium' => 1, 'high' => 2, 'critical' => 3];

        $key = is_string($severity) ? $severity : '';

        return ($rank[$key] ?? 0) >= ($rank[$minSeverity] ?? 0);
    }

    public function togglePanel(): void
    {
        $this->showPanel = ! $this->showPanel;
    }

    public function acknowledge(int $id, string $source): void
    {
        $team = auth()->user()?->currentTeam;

        match ($source) {
            'ai' => AIPredictiveAlert::where('team_id', $team?->id)->find($id)?->update([
                'is_acknowledged' => true,
                'acknowledged_at' => now(),
                'acknowledged_by' => CurrentUser::get()?->id,
            ]),
            'alert' => Alert::where('team_id', $team?->id)->find($id)?->acknowledge(CurrentUser::get()?->id),
            'fuel_alert' => FuelAlert::where('team_id', $team?->id)->find($id)?->update([
                'status' => 'acknowledged',
                'acknowledged_at' => now(),
                'acknowledged_by' => CurrentUser::get()?->id,
            ]),
            // Notification's "read" tracking is per-user (readBy pivot),
            // not a single shared acknowledged/acknowledged_by pair like
            // the other three sources -- markAsRead() records this user
            // specifically, so the notification can still be unread for
            // teammates.
            'notification' => Notification::where('team_id', $team?->id)->find($id)?->markAsRead((int) CurrentUser::get()?->id),
            default => null,
        };

        $this->loadNotifications();

        $this->dispatch('alert-acknowledged', id: $id, source: $source);
    }

    public function acknowledgeAll(): void
    {
        $team = auth()->user()?->currentTeam;

        if (! $team) {
            return;
        }

        AIPredictiveAlert::where('team_id', $team->id)
            ->where('is_acknowledged', false)
            ->update([
                'is_acknowledged' => true,
                'acknowledged_at' => now(),
                'acknowledged_by' => CurrentUser::get()?->id,
            ]);

        Alert::where('team_id', $team->id)->active()->get()->each(
            fn (Alert $a) => $a->acknowledge(CurrentUser::get()?->id)
        );

        FuelAlert::where('team_id', $team->id)->active()->update([
            'status' => 'acknowledged',
            'acknowledged_at' => now(),
            'acknowledged_by' => CurrentUser::get()?->id,
        ]);

        Notification::where('team_id', $team->id)
            ->whereDoesntHave('readBy', fn (Builder $q): mixed => $q->where('user_id', auth()->id()))
            ->get()
            ->each(fn (Notification $n) => $n->markAsRead((int) CurrentUser::get()?->id));

        $this->loadNotifications();
    }

    public function render(): View
    {
        return view('livewire.ai-notifications');
    }
}
