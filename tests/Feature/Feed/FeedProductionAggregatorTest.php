<?php

namespace Tests\Feature\Feed;

use App\Models\FeedItem;
use App\Models\Team;
use App\Services\Feed\FeedProductionAggregator;
use App\Services\OperationalSnapshotService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * The hourly production line: a subtraction over the Production page's own
 * numbers, and silent whenever it cannot be truthful.
 */
class FeedProductionAggregatorTest extends TestCase
{
    use RefreshDatabase;

    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $this->team = Team::factory()->create();
    }

    /**
     * Bind a snapshot stub: fixed per-machine loads_today values, a chosen
     * freshest-telemetry time, and the team's staleness threshold.
     *
     * @param  list<int|null>  $loadsPerMachine
     */
    private function fakeSnapshots(array $loadsPerMachine, ?Carbon $freshestAt = null, int $staleAfter = 3600): void
    {
        $this->app->bind(
            OperationalSnapshotService::class,
            fn (): OperationalSnapshotService => new class($loadsPerMachine, $freshestAt ?? now(), $staleAfter) extends OperationalSnapshotService
            {
                /** @param list<int|null> $loads */
                public function __construct(
                    private readonly array $loads,
                    private readonly Carbon $freshest,
                    private readonly int $staleAfter,
                ) {}

                #[\Override]
                public function forTeam(Team $team): Collection
                {
                    return collect($this->loads)
                        ->mapWithKeys(fn (?int $loads, int $i): array => [$i + 1 => ['loads_today' => $loads]]);
                }

                #[\Override]
                public function teamTelemetryFreshestAt(Team $team): ?Carbon
                {
                    return $this->freshest;
                }

                #[\Override]
                public function staleAfterSeconds(int $teamId): int
                {
                    return $this->staleAfter;
                }
            }
        );
    }

    private function aggregate(): ?FeedItem
    {
        return app(FeedProductionAggregator::class)->publishHourly($this->team);
    }

    public function test_the_first_run_establishes_a_baseline_and_publishes_nothing(): void
    {
        $this->fakeSnapshots([10, 5]);

        $this->assertNull($this->aggregate());
        $this->assertSame(0, FeedItem::withoutTeamFilter()->count());
    }

    public function test_the_delta_since_last_run_is_published_from_the_same_numbers(): void
    {
        $this->fakeSnapshots([10, 5]);
        $this->aggregate(); // baseline: 15

        $this->fakeSnapshots([14, 8]); // now 22 -> +7
        $item = $this->aggregate();

        $this->assertNotNull($item);
        $this->assertSame('Fleet completed 7 loads in the last hour', $item->title);
        $this->assertSame(FeedItem::CATEGORY_PRODUCTION, $item->category);
        $this->assertSame(['loads' => 7, 'fleet_total_today' => 22], [
            'loads' => $item->data['loads'] ?? null,
            'fleet_total_today' => $item->data['fleet_total_today'] ?? null,
        ]);
    }

    public function test_a_quiet_hour_publishes_nothing(): void
    {
        $this->fakeSnapshots([10, 5]);
        $this->aggregate();

        $this->assertNull($this->aggregate(), 'No delta, no post — the feed does not narrate quiet hours.');
    }

    public function test_the_midnight_counter_reset_moves_the_baseline_silently(): void
    {
        $this->fakeSnapshots([40, 30]);
        $this->aggregate(); // baseline 70

        // Counters reset overnight: totals drop. Nothing is published, and
        // the NEXT delta is measured from the new, lower baseline.
        $this->fakeSnapshots([2, 1]);
        $this->assertNull($this->aggregate());

        $this->fakeSnapshots([6, 2]); // 8 - 3 = +5
        $item = $this->aggregate();
        $this->assertSame('Fleet completed 5 loads in the last hour', $item?->title);
    }

    public function test_stale_telemetry_silences_the_headline_even_when_counters_moved(): void
    {
        $this->fakeSnapshots([10, 5]);
        $this->aggregate();

        // Counters moved, but the freshest telemetry is two hours old against
        // a one-hour staleness threshold.
        $this->fakeSnapshots([20, 9], now()->subHours(2), 3600);

        $this->assertNull($this->aggregate(), 'A stale "last hour" headline is the misleading liveness the brief forbids.');
    }

    public function test_a_fleet_with_no_load_counters_stays_silent(): void
    {
        $this->fakeSnapshots([null, null]);

        $this->assertNull($this->aggregate());
        $this->assertNull($this->aggregate());
    }

    public function test_the_hour_key_dedupes_a_double_run_within_the_same_hour(): void
    {
        $this->fakeSnapshots([10, 5]);
        $this->aggregate(); // baseline

        $this->fakeSnapshots([20, 10]);
        $this->assertNotNull($this->aggregate());

        // A second run in the same hour with further movement: the dedupe key
        // for this hour already exists, so nothing extra appears.
        $this->fakeSnapshots([25, 12]);
        $this->assertNull($this->aggregate());

        $this->assertSame(1, FeedItem::withoutTeamFilter()->where('type', 'production.hourly')->count());
    }
}
