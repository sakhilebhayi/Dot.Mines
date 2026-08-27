<?php

namespace App\Services;

use App\Models\GeofenceEntry;
use App\Models\Machine;
use App\Models\MachineMetric;
use App\Support\Geo;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Live fleet dispatch snapshot: where every machine is and what it is doing
 * right now, derived ONLY from signals the platform actually has --
 * the latest telemetry reading (speed, engine state, position, freshness)
 * and open geofence entries (which typed zone the machine is inside).
 *
 * States are claimed conservatively: "loading"/"dumping" only when the
 * machine is stationary inside a zone of that type; a machine we have not
 * heard from recently is reported as exactly that, never guessed.
 */
class DispatchService
{
    /** Telemetry older than this is "no recent telemetry". */
    private const FRESH_MINUTES = 60;

    /** At or above this speed (km/h) the machine counts as travelling. */
    private const TRAVEL_SPEED_KMH = 5.0;

    /**
     * @return array{machines: array<int, array<string, mixed>>, counts: array<string, int>, generated_at: CarbonInterface}
     */
    public function fleetSnapshot(int $teamId): array
    {
        $machines = Machine::query()
            ->where('team_id', $teamId)
            ->with(['latestEngineHoursMetric', 'latestMetric'])
            ->get()
            ->sortBy('name')
            ->values();

        // Up to two hours of recent position rows per machine, one query
        // for the whole fleet: live Bell sends no Speed field, so movement
        // is derived from consecutive positions when the machine's own
        // reading is absent. (Per-machine queries here were an N+1 once
        // already -- see the latestMetric note below.)
        /**
         * @psalm-suppress UnnecessaryVarAnnotation -- phpstan needs it (larastan infers stdClass here)
         *
         * @phpstan-var Collection<int, \Illuminate\Database\Eloquent\Collection<int, MachineMetric>> $recentPositions
         */
        $recentPositions = MachineMetric::query()
            ->whereIn('machine_id', $machines->pluck('id'))
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->where('recorded_at', '>=', now()->subHours(2))
            ->orderByDesc('recorded_at')
            ->orderByDesc('id')
            ->get(['machine_id', 'latitude', 'longitude', 'recorded_at'])
            ->groupBy('machine_id');

        // One open geofence entry per machine (exit_time null = still inside).
        $openEntries = GeofenceEntry::query()
            ->whereIn('machine_id', $machines->pluck('id'))
            ->whereNull('exit_time')
            ->with('geofence')
            ->get()
            ->keyBy('machine_id');

        $rows = [];
        $counts = [
            'loading' => 0, 'dumping' => 0, 'travelling' => 0,
            'idling' => 0, 'parked' => 0, 'no_telemetry' => 0,
        ];

        foreach ($machines as $machine) {
            // Eager-loaded latest row (newest created_at) instead of a
            // per-machine recorded_at-ordered query: sync appends rows, so
            // the newest-created row IS the newest reading in this
            // pipeline, and the old per-machine query was an N+1 running
            // 26 extra queries on every 30-second dispatch poll.
            $metric = $machine->latestMetric;

            $recordedAt = $metric?->recorded_at ?? $metric?->created_at;
            $fresh = $recordedAt !== null
                && $recordedAt->diffInMinutes(now()) <= self::FRESH_MINUTES;

            $entry = $openEntries->get($machine->id);
            $zone = $entry?->geofence;

            $derivedKmh = $this->derivedSpeedKmh($recentPositions->get($machine->id));

            [$state, $basis] = $this->classify($machine, $metric, $fresh, $zone?->type, $derivedKmh);

            $counts[$state]++;

            $rows[] = [
                'machine' => $machine,
                'state' => $state,
                'basis' => $basis,
                'zone' => $zone?->name,
                'speed' => $fresh ? (float) ($metric->speed ?? $derivedKmh ?? 0) : null,
                'latitude' => $machine->last_location_latitude,
                'longitude' => $machine->last_location_longitude,
                'updated_at' => $recordedAt,
            ];
        }

        return [
            'machines' => $rows,
            'counts' => $counts,
            'generated_at' => now(),
        ];
    }

    /**
     * Average speed between the machine's two newest distinct position
     * readings, or null when there is nothing honest to derive from.
     *
     * @param  Collection<int, MachineMetric>|null  $positions
     */
    private function derivedSpeedKmh($positions): ?float
    {
        if ($positions === null || $positions->count() < 2) {
            return null;
        }

        $latest = $positions->first();
        $latestAt = $latest?->recorded_at;

        if ($latest === null || $latestAt === null) {
            return null;
        }

        $previous = $positions->skip(1)->first(
            fn (MachineMetric $row): bool => $row->recorded_at !== null && ! $row->recorded_at->equalTo($latestAt)
        );

        if ($previous === null || $previous->recorded_at === null) {
            return null;
        }

        $hours = $previous->recorded_at->diffInSeconds($latestAt) / 3600.0;

        // Under a minute apart the estimate is GPS noise, not movement.
        if ($hours < 1.0 / 60.0) {
            return null;
        }

        return Geo::distanceKm(
            (float) $previous->latitude,
            (float) $previous->longitude,
            (float) $latest->latitude,
            (float) $latest->longitude,
        ) / $hours;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function classify(Machine $machine, ?MachineMetric $metric, bool $fresh, ?string $zoneType, ?float $derivedKmh = null): array
    {
        if ($metric === null || ! $fresh) {
            return ['no_telemetry', $metric === null
                ? 'This machine has never reported telemetry.'
                : 'Last reading is older than '.self::FRESH_MINUTES.' minutes.'];
        }

        $reported = $metric->speed;
        $speed = $reported ?? 0.0;
        /** @psalm-suppress MixedAssignment */
        $engineRunning = data_get($metric->raw_data, 'engine_running');

        if ($engineRunning === false) {
            return ['parked', 'Engine reported off.'];
        }

        if ($reported !== null && $speed >= self::TRAVEL_SPEED_KMH) {
            return ['travelling', sprintf('Moving at %.0f km/h.', $speed)];
        }

        // The provider sent no speed reading at all: movement derived
        // from consecutive positions is the only honest signal left. A
        // reported speed -- including an affirmative 0 -- always
        // outranks this estimate.
        if ($reported === null && $derivedKmh !== null && $derivedKmh >= self::TRAVEL_SPEED_KMH) {
            return ['travelling', sprintf('Moved ~%.0f km/h between the last two readings.', $derivedKmh)];
        }

        // Stationary with the engine not reported off: the zone type is the
        // only honest way to call it loading or dumping.
        if (in_array($zoneType, ['pit', 'stockpile'], true)) {
            return ['loading', 'Stationary inside a '.$zoneType.' zone.'];
        }

        if ($zoneType === 'dump') {
            return ['dumping', 'Stationary inside a dump zone.'];
        }

        return ['idling', $engineRunning === true
            ? 'Stationary with the engine running.'
            : 'Stationary; engine state not reported.'];
    }
}
