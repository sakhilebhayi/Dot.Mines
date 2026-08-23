<?php

namespace App\Services;

use App\Models\Integration;
use App\Models\Machine;
use App\Models\ProductionRecord;
use App\Models\Team;
use App\Services\Integration\TelemetryProductionCalculator;
use App\Support\ApiPayload;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * The single per-machine operational truth (brief §14: "one machine, one
 * source of truth"). Every surface that shows a machine's live state --
 * Fleet, Live Map, Production, Dashboard dispatch, Machine Detail -- reads
 * the same snapshot instead of re-deriving its own answer.
 *
 * Today's loads/cycles/tonnes are REAL counter arithmetic, never estimates:
 * the freshest cumulative counter reading (live fleet snapshot in
 * MachineMetric.raw_data, or today's telemetry production record if that
 * closed later) minus the previous day's closing value stored by the
 * production sync. When either side of that subtraction is missing the
 * value is null -- callers render "Awaiting API data", nothing is invented
 * (brief §6).
 */
class OperationalSnapshotService
{
    /** @psalm-suppress PossiblyUnusedMethod -- instantiated by the container (app()/DI), which psalm cannot see */
    public function __construct(private TelemetryProductionCalculator $calculator) {}

    /**
     * Snapshots for every machine of a team, keyed by machine id.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function forTeam(Team $team): Collection
    {
        $timezone = $team->timezone ?: config('app.timezone', 'UTC');
        $today = Carbon::now($timezone)->toDateString();

        $machines = Machine::where('team_id', $team->id)
            ->with('latestMetric')
            ->get();

        $baselines = $this->closingBaselines($team->id, $machines->pluck('id')->all(), $today);
        $todayRecords = $this->todayTelemetryRecords($team->id, $machines->pluck('id')->all(), $today);
        $staleAfter = $this->staleAfterSeconds($team->id);

        return $machines->mapWithKeys(fn (Machine $machine): array => [
            $machine->id => $this->snapshot(
                $machine,
                $baselines->get($machine->id),
                $todayRecords->get($machine->id),
                $staleAfter
            ),
        ]);
    }

    /**
     * Snapshot for a single machine (Machine Detail's deepest-truth view).
     *
     * @return array<string, mixed>
     */
    public function forMachine(Machine $machine): array
    {
        $team = $machine->team;
        $timezone = $team?->timezone ?? ApiPayload::str(config('app.timezone', 'UTC'), 'UTC');
        $today = Carbon::now($timezone)->toDateString();

        return $this->snapshot(
            $machine->loadMissing('latestMetric'),
            $this->closingBaselines($machine->team_id, [$machine->id], $today)->get($machine->id),
            $this->todayTelemetryRecords($machine->team_id, [$machine->id], $today)->get($machine->id),
            $this->staleAfterSeconds($machine->team_id)
        );
    }

    /**
     * The newest telemetry timestamp across the whole fleet -- what a page
     * header's freshness badge should show.
     */
    public function teamTelemetryFreshestAt(Team $team): ?Carbon
    {
        /** @var Carbon|null $latest */
        $latest = Machine::where('team_id', $team->id)
            ->with('latestMetric')
            ->get()
            ->map(fn (Machine $machine) => $machine->latestMetric?->recorded_at)
            ->filter()
            ->max();

        return $latest;
    }

    /**
     * Seconds after which this team's telemetry should be labeled stale:
     * three missed provider sync intervals. Derived from the connected
     * integration's own configured cadence, not a guess.
     */
    public function staleAfterSeconds(int $teamId): int
    {
        $provider = ApiPayload::str(Integration::where('team_id', $teamId)
            ->where('status', 'connected')
            ->value('provider'), 'unknown');

        $interval = (int) config("integrations.manufacturers.{$provider}.sync_interval", 300);

        return max(60, $interval * 3);
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(Machine $machine, ?ProductionRecord $baseline, ?ProductionRecord $todayRecord, int $staleAfter): array
    {
        $metric = $machine->latestMetric;
        $rawData = $metric?->raw_data;
        $raw = is_array($rawData) ? $rawData : [];

        [$liveLoads, $liveTonnesKgValue, $liveUnits, $counterAt] = $this->freshestCounters($metric?->recorded_at, $raw, $todayRecord);

        $baselineLoadEnd = $this->numericMeta($baseline, 'cumulative_load_count_end');
        $baselinePayloadEnd = $this->numericMeta($baseline, 'cumulative_payload_end');

        $loadsToday = ($liveLoads !== null && $baselineLoadEnd !== null)
            ? (int) round(max(0.0, $liveLoads - $baselineLoadEnd))
            : null;

        $tonnesToday = ($liveTonnesKgValue !== null && $baselinePayloadEnd !== null)
            ? $this->calculator->payloadToTonnes(max(0.0, $liveTonnesKgValue - $baselinePayloadEnd), $liveUnits)
            : null;

        $lastTelemetryAt = $metric?->recorded_at;
        $ageSeconds = $lastTelemetryAt ? (int) abs(Carbon::now()->diffInSeconds($lastTelemetryAt)) : null;

        return [
            'machine_id' => $machine->id,
            'name' => $machine->name,
            'status' => $machine->status,
            'latitude' => $machine->last_location_latitude !== null ? $machine->last_location_latitude : null,
            'longitude' => $machine->last_location_longitude !== null ? $machine->last_location_longitude : null,
            'speed' => $metric?->speed,
            'heading' => $metric?->heading,
            'fuel_level' => $metric?->fuel_level,
            'engine_hours' => $metric?->operating_hours ?? $metric?->total_hours,
            'loads_today' => $loadsToday,
            // Bell ADTs have no separate cycle counter; a load event IS one
            // haul cycle (same real counter the production sync stores).
            'cycles_today' => $loadsToday,
            'tonnes_today' => $tonnesToday,
            'baseline_date' => $baseline?->record_date?->toDateString(),
            'lifetime_loads' => $liveLoads !== null ? (int) round($liveLoads) : null,
            'lifetime_tonnes' => $liveTonnesKgValue !== null ? $this->calculator->payloadToTonnes($liveTonnesKgValue, $liveUnits) : null,
            'counter_reading_at' => $counterAt,
            'last_telemetry_at' => $lastTelemetryAt,
            'last_location_at' => $machine->last_location_update,
            'freshness' => match (true) {
                $ageSeconds === null => 'none',
                $ageSeconds <= $staleAfter => 'live',
                $ageSeconds <= $staleAfter * 2 => 'recent',
                default => 'stale',
            },
            'stale_after_seconds' => $staleAfter,
        ];
    }

    /**
     * The freshest cumulative counter pair available for a machine: the
     * live fleet-snapshot values carried on the latest metric, or today's
     * telemetry production record's closing values if the production sync
     * read the series more recently. Both are real Bell counters -- this
     * only picks whichever was READ later.
     *
     * @param  array<string, mixed>  $raw
     * @return array{0: ?float, 1: ?float, 2: ?string, 3: ?Carbon}
     */
    private function freshestCounters(?Carbon $metricAt, array $raw, ?ProductionRecord $todayRecord): array
    {
        $liveLoads = is_numeric($raw['load_count'] ?? null) ? (float) $raw['load_count'] : null;
        $livePayload = is_numeric($raw['cumulative_payload'] ?? null) ? (float) $raw['cumulative_payload'] : null;
        /** @psalm-suppress MixedAssignment */
        $liveUnitsRaw = $raw['payload_units'] ?? null;
        $liveUnits = is_string($liveUnitsRaw) ? $liveUnitsRaw : null;

        $recordLoads = $this->numericMeta($todayRecord, 'cumulative_load_count_end');
        $recordPayload = $this->numericMeta($todayRecord, 'cumulative_payload_end');
        /** @psalm-suppress MixedAssignment */
        $recordUnitsRaw = data_get($todayRecord?->metadata, 'payload_units');
        $recordUnits = is_string($recordUnitsRaw) ? $recordUnitsRaw : null;
        /** @psalm-suppress MixedAssignment */
        $recordAtRaw = data_get($todayRecord?->metadata, 'last_reading_utc');
        $recordAt = is_string($recordAtRaw) ? Carbon::parse($recordAtRaw) : null;

        $useRecord = $recordAt !== null && ($metricAt === null || $recordAt->gt($metricAt));

        if ($useRecord && ($recordLoads !== null || $recordPayload !== null)) {
            return [
                $recordLoads ?? $liveLoads,
                $recordPayload ?? $livePayload,
                $recordUnits ?? $liveUnits,
                $recordAt,
            ];
        }

        return [$liveLoads, $livePayload, $liveUnits, $metricAt];
    }

    /**
     * Latest telemetry production record strictly before today, per
     * machine -- its cumulative_*_end metadata is the previous close that
     * "today so far" subtracts from.
     *
     * @param  list<int>  $machineIds
     * @return \Illuminate\Database\Eloquent\Collection<int, ProductionRecord>
     *
     * @psalm-suppress MoreSpecificReturnType, LessSpecificReturnStatement -- psalm's
     * keyBy() stub adds a phantom Support\Collection<int, null> union arm
     */
    private function closingBaselines(int $teamId, array $machineIds, string $today): \Illuminate\Database\Eloquent\Collection
    {
        return ProductionRecord::where('team_id', $teamId)
            ->whereIn('machine_id', $machineIds)
            ->where('metadata->source', 'telemetry')
            ->whereDate('record_date', '<', $today)
            ->orderByDesc('record_date')
            ->get()
            ->groupBy('machine_id')
            ->map(fn (Collection $records) => $records->first());
    }

    /**
     * @param  list<int>  $machineIds
     * @return \Illuminate\Database\Eloquent\Collection<int, ProductionRecord>
     *
     * @psalm-suppress MoreSpecificReturnType, LessSpecificReturnStatement -- psalm's
     * keyBy() stub adds a phantom Support\Collection<int, null> union arm
     */
    private function todayTelemetryRecords(int $teamId, array $machineIds, string $today): \Illuminate\Database\Eloquent\Collection
    {
        return ProductionRecord::where('team_id', $teamId)
            ->whereIn('machine_id', $machineIds)
            ->where('metadata->source', 'telemetry')
            ->whereDate('record_date', $today)
            ->get()
            ->keyBy('machine_id');
    }

    private function numericMeta(?ProductionRecord $record, string $key): ?float
    {
        /** @psalm-suppress MixedAssignment */
        $value = data_get($record?->metadata, $key);

        return is_numeric($value) ? (float) $value : null;
    }
}
