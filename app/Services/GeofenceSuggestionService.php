<?php

namespace App\Services;

use App\Models\Geofence;
use App\Models\MachineMetric;
use App\Models\Team;
use Carbon\Carbon;

/**
 * Candidate operational zones derived from the fleet's OWN GPS history
 * (brief §9/§10): places where machines repeatedly dwell with the engine
 * running are loading areas, dump points, workshops -- the real operational
 * layout revealing itself. Nothing here creates a zone: every suggestion is
 * a proposal the user confirms (and names, and types) through the normal
 * geofence form, because the data cannot know WHAT a hotspot is, only that
 * machines keep working there.
 *
 * Bell's cumulative load counters only tick at the daily rollover (verified
 * live 2026-08-22), so per-load positions are not derivable -- dwell
 * clustering is the honest signal this data actually supports.
 */
class GeofenceSuggestionService
{
    /** Grid cell edge in degrees (~65 m at this latitude). */
    private const CELL_DEGREES = 0.0006;

    /** A cluster needs at least this many dwell readings to be suggested. */
    private const MIN_READINGS = 5;

    /** ...spread over at least this many distinct days. */
    private const MIN_DAYS = 2;

    /** Below this speed (km/h) a reading counts as dwelling. */
    private const DWELL_SPEED_KMH = 2.0;

    /** Suggestions this close (degrees, ~150 m) to an existing geofence centre are dropped. */
    private const EXISTING_ZONE_CLEARANCE = 0.0015;

    /** Half-width (degrees, ~45 m) of the proposed square boundary. */
    private const PROPOSED_HALF_WIDTH = 0.0004;

    /**
     * @return list<array{center_latitude: float, center_longitude: float, readings: int, machines: int, days: int, coordinates: list<array{lat: float, lng: float}>}>
     */
    public function suggestForTeam(Team $team, int $days = 14, int $limit = 5): array
    {
        $metrics = MachineMetric::query()
            ->where('team_id', $team->id)
            ->where('recorded_at', '>=', Carbon::now()->subDays($days))
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->where(function ($query) {
                $query->whereNull('speed')->orWhere('speed', '<', self::DWELL_SPEED_KMH);
            })
            ->get(['machine_id', 'latitude', 'longitude', 'speed', 'recorded_at', 'raw_data']);

        /** @var array<string, array{lat_sum: float, lng_sum: float, count: int, machines: array<int, true>, days: array<string, true>}> $cells */
        $cells = [];

        foreach ($metrics as $metric) {
            // Engine off = parked for the night, not working a zone.
            if (data_get($metric->raw_data, 'engine_running') !== true) {
                continue;
            }

            $lat = (float) $metric->latitude;
            $lng = (float) $metric->longitude;
            $key = floor($lat / self::CELL_DEGREES).':'.floor($lng / self::CELL_DEGREES);

            $cells[$key] ??= ['lat_sum' => 0.0, 'lng_sum' => 0.0, 'count' => 0, 'machines' => [], 'days' => []];
            $cells[$key]['lat_sum'] += $lat;
            $cells[$key]['lng_sum'] += $lng;
            $cells[$key]['count']++;
            $cells[$key]['machines'][(int) $metric->machine_id] = true;
            $cells[$key]['days'][$metric->recorded_at?->toDateString() ?? 'unknown'] = true;
        }

        $existingCenters = Geofence::where('team_id', $team->id)
            ->get(['center_latitude', 'center_longitude'])
            ->map(fn (Geofence $geofence): array => [(float) $geofence->center_latitude, (float) $geofence->center_longitude]);

        $suggestions = [];

        foreach ($cells as $cell) {
            if ($cell['count'] < self::MIN_READINGS || count($cell['days']) < self::MIN_DAYS) {
                continue;
            }

            $centerLat = $cell['lat_sum'] / $cell['count'];
            $centerLng = $cell['lng_sum'] / $cell['count'];

            $nearExisting = $existingCenters->contains(
                fn (array $center): bool => abs($center[0] - $centerLat) < self::EXISTING_ZONE_CLEARANCE
                    && abs($center[1] - $centerLng) < self::EXISTING_ZONE_CLEARANCE
            );

            if ($nearExisting) {
                continue;
            }

            $suggestions[] = [
                'center_latitude' => round($centerLat, 6),
                'center_longitude' => round($centerLng, 6),
                'readings' => $cell['count'],
                'machines' => count($cell['machines']),
                'days' => count($cell['days']),
                'coordinates' => [
                    ['lat' => round($centerLat - self::PROPOSED_HALF_WIDTH, 6), 'lng' => round($centerLng - self::PROPOSED_HALF_WIDTH, 6)],
                    ['lat' => round($centerLat - self::PROPOSED_HALF_WIDTH, 6), 'lng' => round($centerLng + self::PROPOSED_HALF_WIDTH, 6)],
                    ['lat' => round($centerLat + self::PROPOSED_HALF_WIDTH, 6), 'lng' => round($centerLng + self::PROPOSED_HALF_WIDTH, 6)],
                    ['lat' => round($centerLat + self::PROPOSED_HALF_WIDTH, 6), 'lng' => round($centerLng - self::PROPOSED_HALF_WIDTH, 6)],
                ],
            ];
        }

        usort($suggestions, fn (array $a, array $b): int => $b['readings'] <=> $a['readings']);

        return array_slice($suggestions, 0, $limit);
    }
}
