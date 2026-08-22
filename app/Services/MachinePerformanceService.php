<?php

namespace App\Services;

use App\Models\Machine;
use App\Models\MachineMetric;
use App\Models\ProductionRecord;
use App\Models\Team;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Derives real daily machine performance from the telemetry and production
 * data the integration sync already stores -- no placeholder scores.
 *
 * Telemetry counters (operating hours, idle hours, fuel consumed) are
 * cumulative lifetime totals, so a day's value is the delta between the
 * day's first and last readings; a day with fewer than two readings of a
 * counter cannot support a delta and reports null ("insufficient data")
 * instead of a misleading zero. Loads/cycles/tonnes come from the same
 * ProductionRecord rows the Production page reads. Days are bucketed in
 * the team's timezone.
 */
class MachinePerformanceService
{
    /**
     * Days of history (before today) used for trend comparison.
     */
    private const TREND_WINDOW_DAYS = 6;

    /**
     * Minimum prior days with data before a trend is reported.
     */
    private const TREND_MIN_DAYS = 2;

    /**
     * Trend thresholds: percentage-point change for utilisation, relative
     * change for loads. Movements inside the band read as "steady".
     */
    private const UTILISATION_TREND_POINTS = 5.0;

    private const LOADS_TREND_RATIO = 0.10;

    /** @psalm-suppress PossiblyUnusedMethod -- instantiated by the container (app()/DI), which psalm cannot see */
    public function __construct(private ProductionService $productionService) {}

    /**
     * Today's performance for every machine on the team that has any
     * telemetry or production data in the trend window. Machines with no
     * data at all are excluded rather than shown with invented zeroes.
     *
     * @return list<array<string, mixed>>
     */
    public function dailyPerformanceForTeam(int $teamId): array
    {
        $timezone = Team::query()->find($teamId)?->timezone ?: config('app.timezone', 'UTC');
        $today = Carbon::now($timezone)->toDateString();
        $windowStart = Carbon::now($timezone)->subDays(self::TREND_WINDOW_DAYS)->startOfDay();

        $machines = Machine::where('team_id', $teamId)->get();

        if ($machines->isEmpty()) {
            return [];
        }

        $metricsByMachine = MachineMetric::where('team_id', $teamId)
            ->where('created_at', '>=', $windowStart->clone()->utc())
            ->orderBy('created_at')
            ->get()
            ->groupBy('machine_id');

        $productionByMachine = ProductionRecord::forTeam($teamId)
            ->whereNotNull('machine_id')
            ->where('record_date', '>=', $windowStart->toDateString())
            ->get()
            ->groupBy('machine_id');

        $performance = [];

        foreach ($machines as $machine) {
            /** @var Collection<int, MachineMetric> $metrics */
            $metrics = $metricsByMachine->get($machine->id, collect());
            /** @var Collection<int, ProductionRecord> $production */
            $production = $productionByMachine->get($machine->id, collect());

            if ($metrics->isEmpty() && $production->isEmpty()) {
                continue;
            }

            // ->toBase(): Eloquent collections override except() to work on
            // model keys, but these are keyed by date string.
            $metricsByDay = $metrics->groupBy(
                fn (MachineMetric $metric) => ($metric->recorded_at ?? $metric->created_at)->clone()->setTimezone($timezone)->toDateString()
            )->toBase();
            $productionByDay = $production->groupBy(
                fn (ProductionRecord $record) => Carbon::parse($record->record_date)->toDateString()
            )->toBase();

            $todayTelemetry = $this->telemetryDay($metricsByDay->get($today, collect()));
            $todayProduction = $this->productionDay($productionByDay->get($today, collect()));

            $workingHours = $todayTelemetry['operating_hours'] !== null && $todayTelemetry['idle_hours'] !== null
                ? max(0.0, $todayTelemetry['operating_hours'] - $todayTelemetry['idle_hours'])
                : null;

            $performance[] = [
                'machine_id' => $machine->id,
                'machine_name' => $machine->name,
                'machine_type' => $machine->machine_type,
                'manufacturer' => $machine->manufacturer,
                'status' => $machine->status,
                'period' => $today,

                // Lifetime / latest-state values (labelled as such in the UI).
                'engine_hours_lifetime' => $this->latestValue($metrics, fn (MachineMetric $m) => $m->operating_hours),
                'fuel_level' => $this->latestValue($metrics, fn (MachineMetric $m) => $m->fuel_level),
                'engine_running' => $this->latestValue($metrics, fn (MachineMetric $m) => data_get($m->raw_data, 'engine_running')),

                // Today's derived values (null = insufficient data).
                'operating_hours_today' => $todayTelemetry['operating_hours'],
                'idle_hours_today' => $todayTelemetry['idle_hours'],
                'utilisation_today' => $this->utilisation($todayTelemetry['operating_hours'], $todayTelemetry['idle_hours']),
                'fuel_lph_today' => $this->fuelPerHour($todayTelemetry['fuel_consumed'], $todayTelemetry['operating_hours']),
                'loads_today' => $todayProduction['loads'],
                'cycles_today' => $todayProduction['cycles'],
                'tonnes_today' => $todayProduction['tonnes'],
                'avg_payload_tonnes' => $todayProduction['loads'] > 0
                    ? $todayProduction['tonnes'] / $todayProduction['loads']
                    : null,
                'avg_cycle_seconds' => $workingHours !== null && $workingHours > 0 && $todayProduction['cycles'] > 0
                    ? ($workingHours * 3600) / $todayProduction['cycles']
                    : null,

                // Trends against the prior days in the window (null =
                // not enough history to say anything honest).
                'utilisation_trend' => $this->utilisationTrend($metricsByDay, $today, $todayTelemetry),
                'loads_trend' => $this->loadsTrend($productionByDay, $today, $todayProduction['loads']),
            ];
        }

        return $performance;
    }

    /**
     * Delta each cumulative counter across one day's readings. A counter
     * needs at least two non-null readings; a negative delta (counter
     * reset) is not a real measurement, so it reports null rather than a
     * clamped guess -- unlike production backfill, there is no stored
     * baseline to recover the true value from within a single day. The
     * hour-denominated counters additionally cannot accrue faster than
     * wall-clock time between their readings; a larger jump is a sync
     * backfill landing prior days' hours in one day's bucket.
     *
     * @param  Collection<int, MachineMetric>  $dayMetrics
     * @return array{operating_hours: ?float, idle_hours: ?float, fuel_consumed: ?float}
     */
    private function telemetryDay(Collection $dayMetrics): array
    {
        return [
            'operating_hours' => $this->dayDelta($dayMetrics, fn (MachineMetric $m) => $m->operating_hours, boundedByElapsedTime: true),
            'idle_hours' => $this->dayDelta($dayMetrics, fn (MachineMetric $m) => $m->idle_hours, boundedByElapsedTime: true),
            'fuel_consumed' => $this->dayDelta($dayMetrics, fn (MachineMetric $m) => data_get($m->raw_data, 'fuel_consumed_cumulative')),
        ];
    }

    /**
     * @param  Collection<int, MachineMetric>  $dayMetrics
     */
    private function dayDelta(Collection $dayMetrics, callable $extract, bool $boundedByElapsedTime = false): ?float
    {
        $readings = $dayMetrics
            ->filter(fn (MachineMetric $m) => is_numeric($extract($m)))
            ->sortBy(fn (MachineMetric $m) => ($m->recorded_at ?? $m->created_at)->getTimestamp())
            ->values();

        if ($readings->count() < 2) {
            return null;
        }

        $delta = (float) $extract($readings->last()) - (float) $extract($readings->first());

        if ($delta < 0) {
            return null;
        }

        if ($boundedByElapsedTime) {
            $firstReading = $readings->first();
            $lastReading = $readings->last();

            if ($firstReading === null || $lastReading === null) {
                return null;
            }

            $first = $firstReading->recorded_at ?? $firstReading->created_at;
            $last = $lastReading->recorded_at ?? $lastReading->created_at;

            if ($first === null || $last === null) {
                return null;
            }

            $elapsedHours = abs($last->getTimestamp() - $first->getTimestamp()) / 3600;

            // The margin absorbs device-vs-server clock skew on otherwise
            // plausible readings.
            if ($delta > $elapsedHours * 1.1 + 0.1) {
                return null;
            }
        }

        return $delta;
    }

    /**
     * @param  Collection<int, ProductionRecord>  $dayRecords
     * @return array{loads: int, cycles: int, tonnes: float}
     */
    private function productionDay(Collection $dayRecords): array
    {
        return [
            'loads' => (int) $dayRecords->sum(fn (ProductionRecord $record) => $this->productionService->recordLoads($record)),
            'cycles' => (int) $dayRecords->sum(fn (ProductionRecord $record) => $this->productionService->recordCycles($record)),
            'tonnes' => (float) $dayRecords->sum('quantity_produced'),
        ];
    }

    /**
     * Share of engine-on time spent working rather than idling.
     */
    private function utilisation(?float $operatingHours, ?float $idleHours): ?float
    {
        if ($operatingHours === null || $idleHours === null || $operatingHours <= 0) {
            return null;
        }

        return max(0.0, min(100.0, (($operatingHours - $idleHours) / $operatingHours) * 100));
    }

    private function fuelPerHour(?float $fuelConsumed, ?float $operatingHours): ?float
    {
        if ($fuelConsumed === null || $operatingHours === null || $operatingHours <= 0) {
            return null;
        }

        return $fuelConsumed / $operatingHours;
    }

    /**
     * Latest non-null reading of one field, by telemetry timestamp.
     *
     * @param  Collection<int, MachineMetric>  $metrics
     */
    private function latestValue(Collection $metrics, callable $extract): mixed
    {
        $metric = $metrics
            ->filter(fn (MachineMetric $m) => $extract($m) !== null)
            ->sortBy(fn (MachineMetric $m) => ($m->recorded_at ?? $m->created_at)->getTimestamp())
            ->last();

        if (! $metric) {
            return null;
        }

        $value = $extract($metric);

        // Booleans (engine_running) are not numeric and pass through as-is.
        return is_numeric($value) ? (float) $value : $value;
    }

    /**
     * @param  Collection<string, Collection<int, MachineMetric>>  $metricsByDay
     * @param  array{operating_hours: ?float, idle_hours: ?float, fuel_consumed: ?float}  $todayTelemetry
     */
    private function utilisationTrend(Collection $metricsByDay, string $today, array $todayTelemetry): ?string
    {
        $todayUtilisation = $this->utilisation($todayTelemetry['operating_hours'], $todayTelemetry['idle_hours']);

        if ($todayUtilisation === null) {
            return null;
        }

        $priorValues = $metricsByDay
            ->except([$today])
            ->map(function (Collection $dayMetrics) {
                $day = $this->telemetryDay($dayMetrics);

                return $this->utilisation($day['operating_hours'], $day['idle_hours']);
            })
            ->filter(fn ($value) => $value !== null);

        if ($priorValues->count() < self::TREND_MIN_DAYS) {
            return null;
        }

        $difference = $todayUtilisation - $priorValues->avg();

        return match (true) {
            $difference > self::UTILISATION_TREND_POINTS => 'improving',
            $difference < -self::UTILISATION_TREND_POINTS => 'declining',
            default => 'steady',
        };
    }

    /**
     * @param  Collection<string, Collection<int, ProductionRecord>>  $productionByDay
     */
    private function loadsTrend(Collection $productionByDay, string $today, int $todayLoads): ?string
    {
        $priorValues = $productionByDay
            ->except([$today])
            ->map(fn (Collection $dayRecords) => $this->productionDay($dayRecords)['loads'])
            ->filter(fn (int $loads) => $loads > 0);

        if ($priorValues->count() < self::TREND_MIN_DAYS) {
            return null;
        }

        $average = (float) $priorValues->avg();

        if ($average <= 0) {
            return null;
        }

        $ratio = ($todayLoads - $average) / $average;

        return match (true) {
            $ratio > self::LOADS_TREND_RATIO => 'improving',
            $ratio < -self::LOADS_TREND_RATIO => 'declining',
            default => 'steady',
        };
    }
}
