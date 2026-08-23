<?php

namespace App\Services;

use App\Models\GeofenceEntry;
use App\Models\Machine;
use App\Models\MachineMetric;
use Carbon\CarbonInterface;

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

            [$state, $basis] = $this->classify($machine, $metric, $fresh, $zone?->type);

            $counts[$state]++;

            $rows[] = [
                'machine' => $machine,
                'state' => $state,
                'basis' => $basis,
                'zone' => $zone?->name,
                'speed' => $fresh ? (float) ($metric->speed ?? 0) : null,
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
     * @return array{0: string, 1: string}
     */
    private function classify(Machine $machine, ?MachineMetric $metric, bool $fresh, ?string $zoneType): array
    {
        if ($metric === null || ! $fresh) {
            return ['no_telemetry', $metric === null
                ? 'This machine has never reported telemetry.'
                : 'Last reading is older than '.self::FRESH_MINUTES.' minutes.'];
        }

        $speed = (float) ($metric->speed ?? 0);
        /** @psalm-suppress MixedAssignment */
        $engineRunning = data_get($metric->raw_data, 'engine_running');

        if ($engineRunning === false) {
            return ['parked', 'Engine reported off.'];
        }

        if ($speed >= self::TRAVEL_SPEED_KMH) {
            return ['travelling', sprintf('Moving at %.0f km/h.', $speed)];
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
