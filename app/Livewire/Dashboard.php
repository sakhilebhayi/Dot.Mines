<?php

namespace App\Livewire;

use App\Models\Alert;
use App\Models\FeedItem;
use App\Models\Machine;
use App\Models\MachineMetric;
use App\Models\MaintenanceRecord;
use App\Models\ProductionRecord;
use App\Models\Team;
use App\Models\User;
use App\Services\DispatchService;
use App\Services\FuelReserveRunwayCalculator;
use App\Services\ProductionService;
use Carbon\CarbonInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Lazy;
use Livewire\Component;

/**
 * The operations board under the landing: open alerts, the fleet right now
 * with a selected-machine panel, seven days of production, and what is on
 * shift. Every figure is read for the signed-in person's current team and
 * carries its own age; nothing here is cached or derived from render time.
 */
#[Lazy]
class Dashboard extends Component
{
    /** Machine telemetry older than this reads as stale (signal colour). */
    public const STALE_AFTER_MINUTES = 30;

    /** Open alerts listed on the board (the alerts page has the rest). */
    private const ALERT_ROWS = 5;

    /** State labels, in the words the dispatch snapshot already uses. */
    private const STATE_LABELS = [
        'loading' => 'Loading',
        'dumping' => 'Dumping',
        'travelling' => 'Travelling',
        'idling' => 'Idle',
        'parked' => 'Parked',
        'no_telemetry' => 'No telemetry',
    ];

    /**
     * Skeleton shown while this page lazy-loads -- the page shell paints
     * immediately instead of blocking on the data queries.
     *
     * @psalm-suppress PossiblyUnusedMethod -- invoked by Livewire's lazy-loading lifecycle
     */
    public function placeholder(): View
    {
        return view('livewire.placeholders.dashboard-board');
    }

    public function mount(): void
    {
        // EnsureTeamContext (routes/web.php's `ensure_team` middleware) only
        // sets current_team_id when the user belongs to at least one team --
        // a user removed from their last team reaches this component with
        // currentTeam genuinely null. Send them to team creation instead of
        // crashing on a null dereference below.
        if (! $this->resolveCurrentTeam()) {
            $this->redirect(route('teams.create'), navigate: true);
        }
    }

    /**
     * Auth::user()->currentTeam can be null (see mount()); centralising the
     * check here keeps every caller in this component consistent.
     */
    private function resolveCurrentTeam(): ?Team
    {
        $user = Auth::user();

        return $user instanceof User ? $user->currentTeam : null;
    }

    public function acknowledgeAlert(int $alertId): void
    {
        $team = $this->resolveCurrentTeam();

        if (! $team) {
            abort(403, 'No active team selected.');
        }

        $alert = Alert::where('team_id', $team->id)->findOrFail($alertId);

        // Same gate as the Alerts page: AlertPolicy::acknowledge (own team
        // and the acknowledge_alerts permission). The control is hidden from
        // people who lack it, and this refusal stays as the real guard.
        $this->authorize('acknowledge', $alert);

        $alert->update([
            'status' => 'acknowledged',
            'acknowledged_at' => now(),
            'acknowledged_by' => Auth::id(),
        ]);

        $this->dispatch('alert-updated', message: 'Alert acknowledged successfully');
    }

    /**
     * Whether this person may acknowledge alerts: the permission the policy
     * checks, read once for the whole board instead of once per row.
     */
    public function getCanAcknowledgeProperty(): bool
    {
        $user = Auth::user();

        return $user instanceof User && $user->hasPermission('acknowledge_alerts');
    }

    /**
     * Priority as the stored word. Critical and high are the only ones that
     * take the signal colour; the old board folded `critical` into a blue
     * LOW chip.
     *
     * @return array{label: string, signal: bool}
     */
    public static function priorityChip(?string $priority): array
    {
        $word = in_array($priority, ['critical', 'high', 'medium', 'low'], true) ? $priority : 'low';

        return [
            'label' => ucfirst($word),
            'signal' => in_array($word, ['critical', 'high'], true),
        ];
    }

    /**
     * The five newest feed items, for the compact Mine Activity list.
     * Empty when the user lacks view_feed -- the list then hides itself.
     *
     * @return Collection<int, FeedItem>
     */
    public function getRecentFeedProperty(): Collection
    {
        if (! (auth()->user()?->hasPermission('view_feed') ?? false)) {
            return new Collection;
        }

        $query = FeedItem::query();
        $query->orderByDesc('occurred_at');
        $query->orderByDesc('id');
        $query->limit(5);

        return $query->get();
    }

    /**
     * The newest telemetry timestamp across the whole fleet: the data's own
     * recorded_at, not the moment this was computed.
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
     * Open alerts, newest first, plus the per-machine view of the same set.
     * One query for the team's open alerts (capped), one count.
     *
     * @return array{rows: list<array<string, mixed>>, total: int, byMachine: array<int, list<array<string, mixed>>>}
     */
    private function openAlerts(int $teamId): array
    {
        $open = Alert::query()
            ->where('team_id', $teamId)
            ->where('status', 'active')
            ->orderByDesc('triggered_at')
            ->orderByDesc('id')
            ->limit(500)
            ->get(['id', 'machine_id', 'type', 'title', 'priority', 'triggered_at', 'created_at']);

        $rank = ['critical' => 0, 'high' => 1, 'medium' => 2, 'low' => 3];

        $rows = $open->take(self::ALERT_ROWS)->map(function (Alert $alert): array {
            $at = $alert->triggered_at ?? $alert->created_at;

            return [
                'id' => $alert->id,
                'type' => ucfirst(str_replace('_', ' ', (string) $alert->type)),
                'title' => $alert->title,
                'priority' => self::priorityChip($alert->priority),
                'machine_id' => $alert->machine_id,
                'age' => $at?->diffForHumans(short: true) ?? '',
                'at' => $at?->toIso8601String(),
            ];
        })->values()->all();

        $byMachine = [];
        foreach ($open as $alert) {
            if ($alert->machine_id === null) {
                continue;
            }
            $byMachine[$alert->machine_id][] = [
                'priority' => $alert->priority,
                'title' => $alert->title,
            ];
        }
        foreach ($byMachine as &$list) {
            usort($list, fn (array $a, array $b): int => ($rank[$a['priority']] ?? 9) <=> ($rank[$b['priority']] ?? 9));
        }
        unset($list);

        return [
            'rows' => $rows,
            'total' => $open->count() >= 500
                ? Alert::where('team_id', $teamId)->where('status', 'active')->count()
                : $open->count(),
            'byMachine' => $byMachine,
        ];
    }

    /**
     * The fleet table and the facts for the selected-machine panel. The
     * snapshot already carries state, zone and speed; fuel, hours and the
     * operator come in as three more queries for the whole fleet, never per
     * row.
     *
     * @param  array<int, list<array<string, mixed>>>  $alertsByMachine
     * @return array{rows: list<array<string, mixed>>, counts: array<string, int>}
     */
    private function fleet(int $teamId, array $alertsByMachine): array
    {
        $snapshot = app(DispatchService::class)->fleetSnapshot($teamId);

        $machines = new Collection(array_map(fn (array $row): Machine => $row['machine'], $snapshot['machines']));
        $machines->load([
            'latestFuelMetric',
            'integration',
            'operatorAssignments' => fn ($query) => $query->whereNull('unassigned_at')->with('operator'),
        ]);

        $now = now();
        $rows = [];

        foreach ($snapshot['machines'] as $row) {
            /** @var Machine $machine */
            $machine = $row['machine'];
            /** @var CarbonInterface|null $heard */
            $heard = $row['updated_at'];
            $fuel = $machine->latestFuelMetric;
            $hours = $machine->latestEngineHoursMetric;
            $operator = $machine->operatorAssignments->first()?->operator;
            $open = $alertsByMachine[$machine->id] ?? [];
            $state = (string) $row['state'];
            $speed = $row['speed'];
            $stale = $heard === null || $heard->diffInMinutes($now) > self::STALE_AFTER_MINUTES;

            $fuelLevel = $fuel?->fuel_level;
            $fuelText = $fuelLevel === null
                ? null
                : rtrim(rtrim(number_format((float) $fuelLevel, 1, '.', ''), '0'), '.').($machine->fuelLevelUnit() === 'percent' ? ' %' : '');

            $rows[] = [
                'id' => $machine->id,
                'name' => $machine->name,
                'type' => ucfirst(str_replace('_', ' ', (string) $machine->machine_type)),
                'marked' => ucfirst((string) $machine->status),
                'state' => $state,
                'stateLabel' => self::STATE_LABELS[$state] ?? ucfirst($state),
                'mark' => match ($state) {
                    'travelling', 'loading', 'dumping' => 'moving',
                    'no_telemetry' => 'none',
                    default => 'parked',
                },
                'basis' => (string) $row['basis'],
                'where' => $row['zone'] ?? ($speed !== null && $speed >= 1 ? number_format($speed, 0).' km/h' : null),
                'speed' => $speed !== null ? number_format($speed, 0).' km/h' : null,
                'fuel' => $fuelText,
                'fuelAge' => $fuel?->recorded_at?->diffForHumans(short: true),
                'hours' => $hours?->operating_hours !== null ? number_format((float) $hours->operating_hours, 0, '.', ' ').' h' : null,
                'hoursAge' => $hours?->recorded_at?->diffForHumans(short: true),
                'operator' => $operator?->name,
                'latitude' => $row['latitude'],
                'longitude' => $row['longitude'],
                'positionAge' => $machine->last_location_update?->diffForHumans(short: true),
                'heard' => $heard?->diffForHumans(short: true),
                'heardLong' => $heard?->diffForHumans(),
                'stale' => $stale,
                'alerts' => count($open),
                'topAlert' => $open[0] ?? null,
                'filter' => mb_strtolower($machine->name.' '.$machine->machine_type.' '.(self::STATE_LABELS[$state] ?? '').' '.($row['zone'] ?? '')),
            ];
        }

        // Needs attention first: open alerts, then no telemetry, then name.
        usort($rows, fn (array $a, array $b): int => [$a['alerts'] > 0 ? 0 : 1, $a['state'] === 'no_telemetry' ? 0 : 1, $a['name']]
            <=> [$b['alerts'] > 0 ? 0 : 1, $b['state'] === 'no_telemetry' ? 0 : 1, $b['name']]);

        return ['rows' => $rows, 'counts' => $snapshot['counts']];
    }

    /**
     * Last seven days of production, one bar per day with its target, from
     * the same service the Production page uses. Null when nothing was
     * recorded, so the page shows no chart rather than an empty one.
     *
     * @return array{days: list<array<string, mixed>>, produced: float, target: float, percent: ?int, unit: string}|null
     */
    private function production(int $teamId): ?array
    {
        $trend = app(ProductionService::class)->getProductionTrend($teamId, 7);

        if ($trend->isEmpty()) {
            return null;
        }

        $days = $trend->map(fn (array $day): array => [
            'date' => (string) $day['date'],
            'label' => Carbon::parse((string) $day['date'])->format('D'),
            'produced' => (float) $day['produced'],
            'target' => (float) $day['target'],
        ])->values()->all();

        $units = ProductionRecord::forTeam($teamId)
            ->where('record_date', '>=', Carbon::now()->subDays(7))
            ->distinct()
            ->pluck('unit')
            ->filter()
            ->values();
        $unit = $units->count() === 1
            ? ($units->first() === 'tonnes' ? 't' : str_replace('_', ' ', (string) $units->first()))
            : 'mixed units';

        $produced = array_sum(array_column($days, 'produced'));
        $target = array_sum(array_column($days, 'target'));

        return [
            'days' => $days,
            'produced' => $produced,
            'target' => $target,
            'percent' => $target > 0 ? (int) round($produced / $target * 100) : null,
            'unit' => $unit,
        ];
    }

    /**
     * @return array{reserve: ?string, inProgress: int, overdue: int}
     */
    private function onShift(int $teamId): array
    {
        $cushion = (new FuelReserveRunwayCalculator)->calculate();
        $reserve = null;

        if (($cushion['available'] ?? false) === true) {
            $days = $cushion['days'] ?? null;
            $reserve = ($cushion['has_no_recent_consumption'] ?? false) || ! is_numeric($days)
                ? 'No recent fuel consumption recorded'
                : 'About '.(int) $days.' day'.((int) $days === 1 ? '' : 's').' at current use';
        }

        $maintenance = MaintenanceRecord::query()
            ->where('team_id', $teamId)
            ->selectRaw("sum(case when status = 'in_progress' then 1 else 0 end) as in_progress")
            ->selectRaw("sum(case when status = 'scheduled' and scheduled_date < ? then 1 else 0 end) as overdue", [now()])
            ->toBase()
            ->first();

        return [
            'reserve' => $reserve,
            'inProgress' => (int) data_get($maintenance, 'in_progress', 0),
            'overdue' => (int) data_get($maintenance, 'overdue', 0),
        ];
    }

    public function render(): View
    {
        $team = $this->resolveCurrentTeam();
        $teamId = $team?->id ?? 0;

        $alerts = $this->openAlerts($teamId);
        $fleet = $this->fleet($teamId, $alerts['byMachine']);

        // Machine names come from the fleet already loaded, not a second query.
        $names = array_column($fleet['rows'], 'name', 'id');
        $alerts['rows'] = array_map(
            fn (array $row): array => $row + ['machine' => $names[$row['machine_id']] ?? null],
            $alerts['rows'],
        );

        return view('livewire.dashboard', [
            'alerts' => $alerts,
            'fleet' => $fleet,
            'production' => $this->production($teamId),
            'shift' => $this->onShift($teamId),
        ]);
    }
}
