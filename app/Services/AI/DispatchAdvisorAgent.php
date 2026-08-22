<?php

namespace App\Services\AI;

use App\Models\Geofence;
use App\Models\GeofenceEntry;
use App\Models\Team;

/**
 * Dispatch Advisor AI Agent
 *
 * Flags active queue imbalances between geofences of the same type (e.g.
 * two loading pits, two dump sites) so a machine can be actively rerouted
 * to the shorter queue -- rather than the passive, historical route-shape
 * advice RouteAdvisorAgent already gives.
 *
 * "Queue" here is machines currently inside a geofence with no exit_time
 * recorded yet (GeofenceEntry::exit_time is null), which is the only
 * concept of dwell/queue time this codebase actually records -- there is
 * no cycle_time/queue_time column on machines despite what older docs
 * claimed.
 */
class DispatchAdvisorAgent
{
    /** Minimum queue-count gap between two geofences of the same type before it's worth recommending a reroute. */
    private const MIN_QUEUE_GAP = 2;

    /** How far back to look for entries still open (no exit_time) to count as "currently queued". */
    private const OPEN_ENTRY_WINDOW_HOURS = 4;

    /** How far back to average historical dwell time for context on the recommendation. */
    private const DWELL_HISTORY_DAYS = 7;

    /**
     * @return array<string, mixed>
     */
    public function analyze(Team $team): array
    {
        $recommendations = [];
        $insights = [];

        $geofencesByAreaAndType = Geofence::where('team_id', $team->id)
            ->where('status', 'active')
            ->get()
            ->groupBy(fn (Geofence $g) => $g->mine_area_id.'|'.$g->type);

        foreach ($geofencesByAreaAndType as $group) {
            if ($group->count() < 2) {
                // Nothing to compare a queue against within this mine area/type.
                continue;
            }

            $stats = $group->map(fn (Geofence $g) => [
                'geofence' => $g,
                'queue' => $this->currentQueueCount($g),
                'avg_dwell_minutes' => $this->averageDwellMinutes($g),
            ]);

            $busiest = $stats->sortByDesc('queue')->first();
            $quietest = $stats->sortBy('queue')->first();

            if ($busiest['geofence']?->id === $quietest['geofence']?->id) {
                continue;
            }

            $gap = $busiest['queue'] - $quietest['queue'];

            if ($gap >= self::MIN_QUEUE_GAP) {
                $recommendations[] = [
                    'category' => 'dispatch',
                    'priority' => $gap >= self::MIN_QUEUE_GAP * 2 ? 'high' : 'medium',
                    'title' => "Queue Imbalance: {$busiest['geofence']?->name} vs {$quietest['geofence']?->name}",
                    'description' => "{$busiest['geofence']->name} currently has {$busiest['queue']} machines queued versus {$quietest['queue']} at {$quietest['geofence']->name}, both {$busiest['geofence']?->type} points in the same mine area.",
                    'proposed_action' => "Reroute the next available machine from {$busiest['geofence']->name} to {$quietest['geofence']->name} to close the {$gap}-machine queue gap.",
                    'confidence_score' => 0.75,
                    'estimated_efficiency_gain' => min(50, $gap * 8),
                    'related_mine_area_id' => $busiest['geofence']?->mine_area_id,
                    'data' => [
                        'busiest_geofence_id' => $busiest['geofence']->id,
                        'busiest_queue' => $busiest['queue'],
                        'busiest_avg_dwell_minutes' => $busiest['avg_dwell_minutes'],
                        'quietest_geofence_id' => $quietest['geofence']->id,
                        'quietest_queue' => $quietest['queue'],
                        'quietest_avg_dwell_minutes' => $quietest['avg_dwell_minutes'],
                        'queue_gap' => $gap,
                    ],
                    'impact_analysis' => [
                        'recommended_action' => "Send the next dispatched machine to {$quietest['geofence']->name} instead of {$busiest['geofence']->name}.",
                    ],
                ];

                if ($busiest['avg_dwell_minutes'] !== null && $busiest['avg_dwell_minutes'] > 0) {
                    $insights[] = [
                        'type' => 'anomaly',
                        'category' => 'dispatch',
                        'severity' => $gap >= self::MIN_QUEUE_GAP * 2 ? 'warning' : 'info',
                        'title' => "Queue Building at {$busiest['geofence']->name}",
                        'description' => "{$busiest['queue']} machines currently queued, {$gap} more than {$quietest['geofence']->name}.",
                        'data' => [
                            'geofence_id' => $busiest['geofence']->id,
                            'queue' => $busiest['queue'],
                            'avg_dwell_minutes' => $busiest['avg_dwell_minutes'],
                        ],
                    ];
                }
            }
        }

        return [
            'recommendations' => $recommendations,
            'insights' => $insights,
        ];
    }

    private function currentQueueCount(Geofence $geofence): int
    {
        return GeofenceEntry::where('geofence_id', $geofence->id)
            ->whereNull('exit_time')
            ->where('entry_time', '>=', now()->subHours(self::OPEN_ENTRY_WINDOW_HOURS))
            ->count();
    }

    private function averageDwellMinutes(Geofence $geofence): ?float
    {
        $entries = GeofenceEntry::where('geofence_id', $geofence->id)
            ->whereNotNull('exit_time')
            ->where('entry_time', '>=', now()->subDays(self::DWELL_HISTORY_DAYS))
            ->get(['entry_time', 'exit_time']);

        if ($entries->isEmpty()) {
            return null;
        }

        $totalMinutes = $entries->sum(fn ($e) => $e->entry_time->diffInMinutes($e->exit_time));

        return round($totalMinutes / $entries->count(), 2);
    }
}
