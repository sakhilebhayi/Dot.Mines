<?php

namespace App\Livewire;

use App\Models\Alert;
use App\Models\Geofence;
use App\Models\Machine;
use App\Models\MachineMetric;
use App\Models\MineArea;
use App\Models\Team;
use App\Models\User;
use App\Support\ApiPayload;
use Carbon\CarbonInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * The first thing a signed-in person sees: who they are, whose mine this is,
 * what state the fleet is in, and the one action that state calls for.
 *
 * Deliberately NOT #[Lazy] (unlike the Dashboard board beneath it): the
 * welcome and the primary action must be in the first response, readable
 * with scripting blocked or slow. It stays cheap -- four count queries, one
 * max() and at most two permission lookups -- and every figure it shows is a live count
 * for the current team, never a cached or derived number.
 */
class DashboardLanding extends Component
{
    public string $firstName = '';

    public string $teamName = '';

    public int $totalMachines = 0;

    public int $activeMachines = 0;

    public int $activeAlerts = 0;

    public int $totalGeofences = 0;

    public int $totalMineAreas = 0;

    public bool $canCreateMineAreas = false;

    public bool $canCreateMachines = false;

    public bool $canAcknowledgeAlerts = false;

    public function mount(): void
    {
        $this->loadState();
    }

    private function resolveUser(): ?User
    {
        $user = Auth::user();

        return $user instanceof User ? $user : null;
    }

    private function resolveCurrentTeam(): ?Team
    {
        return $this->resolveUser()?->currentTeam;
    }

    /**
     * Re-read on the same 30s visible-only cadence as the board below, and
     * straight away when an alert is acknowledged there, so the two never
     * disagree about how many alerts are open.
     */
    #[On('alert-updated')]
    public function loadState(): void
    {
        $user = $this->resolveUser();
        $team = $this->resolveCurrentTeam();

        if ($user === null || $team === null) {
            // The Dashboard component beneath redirects a teamless user to
            // team creation; render nothing here in the meantime.
            $this->teamName = '';

            return;
        }

        $this->firstName = Str::of($user->name)->trim()->before(' ')->toString();
        $this->teamName = $team->name;

        // Both machine counts in one pass; the page has a query budget
        // (PageQueryBudgetTest) and the landing must stay a small share of it.
        $fleet = Machine::query()
            ->where('team_id', $team->id)
            ->selectRaw("count(*) as total, sum(case when status = 'active' then 1 else 0 end) as active")
            ->toBase()
            ->first();

        $this->totalMachines = self::count(data_get($fleet, 'total'));
        $this->activeMachines = self::count(data_get($fleet, 'active'));
        $this->activeAlerts = Alert::where('team_id', $team->id)->where('status', 'active')->count();
        $this->totalGeofences = Geofence::where('team_id', $team->id)->count();
        $this->totalMineAreas = MineArea::where('team_id', $team->id)->count();

        $this->loadPermissions($user);
    }

    private static function count(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    /**
     * Permissions only decide the action while setup is unfinished or an
     * alert is open, so a settled, quiet mine costs no lookup at all. When
     * they are needed it is one role check and one permission query, not
     * hasPermission() three times over.
     */
    private function loadPermissions(User $user): void
    {
        $this->canCreateMineAreas = false;
        $this->canCreateMachines = false;
        $this->canAcknowledgeAlerts = false;

        if ($this->totalMineAreas > 0 && $this->totalMachines > 0 && $this->activeAlerts === 0) {
            return;
        }

        if ($user->hasRole('admin')) {
            $this->canCreateMineAreas = true;
            $this->canCreateMachines = true;
            $this->canAcknowledgeAlerts = true;

            return;
        }

        $query = $user->permissions();
        $query->whereIn('permissions.name', ['create_mine_areas', 'create_machines', 'acknowledge_alerts']);
        $granted = $query->pluck('permissions.name')->all();

        $this->canCreateMineAreas = in_array('create_mine_areas', $granted, true);
        $this->canCreateMachines = in_array('create_machines', $granted, true);
        $this->canAcknowledgeAlerts = in_array('acknowledge_alerts', $granted, true);
    }

    /**
     * The one action this person's state calls for. Setup comes first for
     * the people allowed to do it, then open alerts for the people allowed
     * to acknowledge them; everyone else, and a quiet fleet, goes to the
     * live map. Each target is an existing page inside the signed-in group.
     *
     * @return array{label: string, route: string}
     */
    public function getPrimaryActionProperty(): array
    {
        if ($this->totalMineAreas === 0 && $this->canCreateMineAreas) {
            return ['label' => 'Create your first mine area', 'route' => 'mine-areas'];
        }

        if ($this->totalMachines === 0 && $this->canCreateMachines) {
            return ['label' => 'Add your first machine', 'route' => 'fleet'];
        }

        if ($this->activeAlerts > 0 && $this->canAcknowledgeAlerts) {
            return [
                'label' => $this->activeAlerts === 1
                    ? 'Review 1 active alert'
                    : 'Review '.$this->activeAlerts.' active alerts',
                'route' => 'alerts',
            ];
        }

        return ['label' => 'Open the live map', 'route' => 'map'];
    }

    /**
     * The state of the mine in words, ahead of any figure.
     */
    public function getStateSentenceProperty(): string
    {
        if ($this->totalMachines === 0) {
            return $this->totalMineAreas === 0
                ? 'There is no mine area and no machine yet.'
                : 'No machines are registered yet.';
        }

        $fleet = match (true) {
            $this->activeMachines === 0 => 'No machine is marked active',
            $this->activeMachines === $this->totalMachines => 'The whole fleet is marked active',
            default => 'Part of the fleet is marked active',
        };

        $alerts = match (true) {
            $this->activeAlerts === 0 => 'no alerts are open',
            $this->activeAlerts === 1 => 'one alert is open',
            default => 'alerts are open',
        };

        return $fleet.' and '.$alerts.'.';
    }

    /**
     * The newest telemetry timestamp for the team: the data's own
     * recorded_at, never the render time (same rule as the dispatch board).
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

    /**
     * InfoDot's base URL from the shared platform registry, so the way back
     * follows the same env override as every other cross-platform link.
     */
    public function getInfodotUrlProperty(): string
    {
        $url = ApiPayload::str(config('ecosystem.platforms.infodot.url'));

        return rtrim($url !== '' ? $url : 'https://infodot.co.za', '/');
    }

    public function render(): View
    {
        return view('livewire.dashboard-landing');
    }
}
