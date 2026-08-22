<?php

namespace App\Livewire;

use App\Models\ActivityLog;
use App\Models\Alert;
use App\Models\Geofence;
use App\Models\Machine;
use App\Models\MachineMetric;
use App\Models\MineArea;
use App\Models\Team;
use App\Models\User;
use App\Services\DispatchService;
use App\Services\QueryCacheService;
use Carbon\CarbonInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Lazy;
use Livewire\Component;

#[Lazy]
class Dashboard extends Component
{
    /**
     * Skeleton shown while this page lazy-loads -- the page shell paints
     * immediately instead of blocking on mount()'s data queries.
     *
     * @psalm-suppress PossiblyUnusedMethod -- invoked by Livewire's lazy-loading lifecycle
     */
    public function placeholder(): View
    {
        return view('livewire.placeholders.dashboard');
    }

    public int $totalMachines = 0;

    public int $activeMachines = 0;

    public int $activeAlerts = 0;

    public int $totalGeofences = 0;

    public int $totalMineAreas = 0;

    /** @var array<int|string, mixed> */
    public array $recentAlerts = [];

    /** @var array<int|string, mixed> */
    public array $machineStatus = [];

    /** @var array<int|string, mixed> */
    public array $activityFeed = [];

    public bool $isLoading = true;

    public function mount(): void
    {
        // EnsureTeamContext (routes/web.php's `ensure_team` middleware) only
        // sets current_team_id when the user belongs to at least one team —
        // a user removed from their last team reaches this component with
        // currentTeam genuinely null (see ReportController::view2() for the
        // same, already-documented case). Send them to team creation instead
        // of crashing on a null dereference below.
        if (! $this->resolveCurrentTeam()) {
            $this->redirect(route('teams.create'), navigate: true);

            return;
        }

        $this->loadDashboardData();
    }

    /**
     * Auth::user()->currentTeam can be null (see mount()); centralising the
     * check here keeps every caller in this component consistent instead of
     * re-deriving the same null-guard in each method.
     */
    private function resolveCurrentTeam(): ?Team
    {
        $user = Auth::user();

        return $user instanceof User ? $user->currentTeam : null;
    }

    public function loadDashboardData(): void
    {
        $this->isLoading = true;
        $team = $this->resolveCurrentTeam();

        if (! $team) {
            $this->isLoading = false;
            $this->redirect(route('teams.create'), navigate: true);

            return;
        }

        // Use cache service for dashboard statistics
        $stats = QueryCacheService::dashboardStats($team->id, function () use ($team) {
            return [
                'total_machines' => Machine::where('team_id', $team->id)->count(),
                'active_machines' => Machine::where('team_id', $team->id)
                    ->where('status', 'active')
                    ->count(),
                'active_alerts' => Alert::where('team_id', $team->id)
                    ->where('status', 'active')
                    ->count(),
                'total_geofences' => Geofence::where('team_id', $team->id)->count(),
            ];
        });

        $this->totalMachines = $stats['total_machines'];
        $this->activeMachines = $stats['active_machines'];
        // Ensure active alerts count is accurate for the current team (bypass stale cache)
        $this->activeAlerts = Alert::where('team_id', $team->id)
            ->where('status', 'active')
            ->count();
        $this->totalGeofences = $stats['total_geofences'];
        $this->totalMineAreas = MineArea::where('team_id', $team->id)->count();

        // Recent Alerts (with eager loading)
        $this->recentAlerts = Alert::where('team_id', $team->id)
            ->with('machine')
            ->latest('created_at')
            ->take(5)
            ->get()
            ->map(fn ($alert) => [
                'id' => $alert->id,
                'type' => $alert->type,
                'priority' => $alert->priority,
                // 'title' is the alert's real headline; the old 'message'
                // key read a column that does not exist on alerts, so every
                // card rendered an empty description line.
                'title' => $alert->title,
                'machine' => $alert->machine?->name,
                'created_at' => $alert->created_at->diffForHumans(),
                'status' => $alert->status,
            ])
            ->toArray();

        // Machine Status Breakdown
        $machineStatuses = Machine::where('team_id', $team->id)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->get();

        $this->machineStatus = $machineStatuses
            ->map(fn ($status) => [
                'status' => ucfirst($status->status),
                'count' => $status->count,
            ])
            ->toArray();

        // Activity Feed
        $this->activityFeed = ActivityLog::where('team_id', $team->id)
            ->with('user')
            ->latest('created_at')
            ->take(10)
            ->get()
            ->map(fn ($log) => [
                'user' => $log->user->name ?? 'System',
                'action' => $log->action,
                'description' => $log->description,
                'created_at' => $log->created_at->diffForHumans(),
            ])
            ->toArray();

        $this->isLoading = false;
    }

    public function acknowledgeAlert(int $alertId): void
    {
        $team = $this->resolveCurrentTeam();

        if (! $team) {
            abort(403, 'No active team selected.');
        }

        $alert = Alert::where('team_id', $team->id)->findOrFail($alertId);

        $alert->update([
            'status' => 'acknowledged',
            'acknowledged_at' => now(),
            'acknowledged_by' => Auth::id(),
        ]);

        $this->loadDashboardData();
        $this->dispatch('alert-updated', message: 'Alert acknowledged successfully');
    }

    /**
     * Live dispatch snapshot for the fleet-flow section. Computed property
     * so the wire:poll on the section re-derives it from current telemetry
     * and open geofence entries on every poll.
     *
     * @return array{machines: array<int, array<string, mixed>>, counts: array<string, int>, generated_at: CarbonInterface}
     */
    public function getFleetDispatchProperty(): array
    {
        return app(DispatchService::class)
            ->fleetSnapshot(Auth::user()->current_team_id ?? 0);
    }

    /**
     * The newest telemetry timestamp across the whole fleet -- the honest
     * "how old is what you're looking at" for the dispatch section. This is
     * the DATA's own recorded_at (Bell's telemetry time), not the moment
     * the snapshot was computed; the two can legitimately differ by many
     * minutes on a polled integration and pretending otherwise is exactly
     * the fake liveness the UX brief forbids.
     */
    public function getTelemetryFreshAtProperty(): ?CarbonInterface
    {
        $teamId = $this->resolveCurrentTeam()?->id ?? 0;

        /** @var string|null $latest */
        $latest = MachineMetric::query()
            ->where('team_id', $teamId)
            ->max('recorded_at');

        return $latest !== null ? Carbon::parse($latest) : null;
    }

    public function render(): View
    {
        return view('livewire.dashboard');
    }
}
