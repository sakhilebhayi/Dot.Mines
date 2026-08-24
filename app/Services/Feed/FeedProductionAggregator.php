<?php

namespace App\Services\Feed;

use App\Models\FeedItem;
use App\Models\Team;
use App\Services\OperationalSnapshotService;
use Illuminate\Support\Facades\Cache;

/**
 * The hourly production line in the feed: "Fleet completed N loads in the
 * last hour" -- computed the only honest way available.
 *
 * The numbers come from OperationalSnapshotService, the SAME counter
 * arithmetic the Production and Machine Detail pages read; this class adds
 * nothing but a subtraction. It remembers the fleet's loads_today total from
 * its previous run (cache) and publishes the delta. No delta, no post --
 * the feed does not narrate quiet hours. A negative delta is the midnight
 * counter reset, so the baseline moves silently and nothing is published,
 * rather than inventing a negative hour.
 *
 * Honesty guard: if the fleet's freshest telemetry is older than the team's
 * own staleness threshold, nothing is published even when the counters
 * moved -- a stale "last hour" headline is exactly the misleading liveness
 * the brief forbids.
 */
class FeedProductionAggregator
{
    public function publishHourly(Team $team): ?FeedItem
    {
        $snapshots = app(OperationalSnapshotService::class);
        $freshestAt = $snapshots->teamTelemetryFreshestAt($team);

        if ($freshestAt === null) {
            return null; // no telemetry at all -- nothing truthful to say
        }

        $loadsNow = $this->fleetLoadsToday($snapshots, $team);

        if ($loadsNow === null) {
            return null;
        }

        $cacheKey = 'feed.loads.baseline.'.$team->id;
        $baseline = Cache::get($cacheKey);
        Cache::put($cacheKey, $loadsNow, now()->addDay());

        if (! is_numeric($baseline)) {
            return null; // first run: establish the baseline, publish nothing
        }

        $delta = $loadsNow - (int) $baseline;

        if ($delta <= 0) {
            return null; // quiet hour, or the midnight counter reset
        }

        if ($freshestAt->diffInSeconds(now()) > $snapshots->staleAfterSeconds($team->id)) {
            return null; // counters moved but the data is stale -- say nothing
        }

        return app(FeedPublisher::class)->publish([
            'team_id' => $team->id,
            'category' => FeedItem::CATEGORY_PRODUCTION,
            'type' => 'production.hourly',
            'title' => 'Fleet completed '.$delta.' '.($delta === 1 ? 'load' : 'loads').' in the last hour',
            'body' => 'Counted from machine load counters — the same numbers the Production page shows.',
            'action_url' => route('production'),
            'data' => ['loads' => $delta, 'fleet_total_today' => $loadsNow],
            'dedupe_key' => 'production:'.$team->id.':'.now()->format('Y-m-d-H'),
            'occurred_at' => $freshestAt,
        ]);
    }

    /**
     * The fleet's loads_today sum, or null when no machine reports one.
     */
    private function fleetLoadsToday(OperationalSnapshotService $snapshots, Team $team): ?int
    {
        $total = null;

        foreach ($snapshots->forTeam($team) as $snapshot) {
            /** @psalm-suppress MixedAssignment -- snapshot values are untyped */
            $loads = $snapshot['loads_today'] ?? null;

            if (is_numeric($loads)) {
                $total = ($total ?? 0) + (int) $loads;
            }
        }

        return $total;
    }
}
