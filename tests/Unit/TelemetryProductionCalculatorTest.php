<?php

namespace Tests\Unit;

use App\Services\Integration\TelemetryProductionCalculator;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;

/**
 * Direct tests for the pure production math extracted in refactor R2.
 */
class TelemetryProductionCalculatorTest extends TestCase
{
    private TelemetryProductionCalculator $calculator;

    protected function setUp(): void
    {
        $this->calculator = new TelemetryProductionCalculator;
    }

    public function test_counter_reset_clamps_to_zero_and_carried_baseline_is_honoured(): void
    {
        $days = [
            '2026-06-01' => ['first_ts' => 'a', 'last_ts' => 'b', 'first_value' => 120.0, 'last_value' => 150.0, 'units' => null],
            '2026-06-02' => ['first_ts' => 'c', 'last_ts' => 'd', 'first_value' => 5.0, 'last_value' => 20.0, 'units' => null],
        ];

        $deltas = $this->calculator->dailyDeltas($days, 100.0);

        $this->assertSame(50.0, $deltas['2026-06-01']['delta'], 'First day baselines on the carried value.');
        $this->assertSame(0.0, $deltas['2026-06-02']['delta'], 'A counter reset must never invent negative production.');
    }

    public function test_readings_bucket_into_local_calendar_days(): void
    {
        // 22:30 UTC on June 1 is 00:30 June 2 in Johannesburg.
        $days = $this->calculator->groupCumulativeReadingsByDay(
            [
                ['timestamp' => '2026-06-01T10:00:00Z', 'value' => 10],
                ['timestamp' => '2026-06-01T22:30:00Z', 'value' => 25],
            ],
            'Africa/Johannesburg',
            Carbon::parse('2026-06-01T00:00:00Z'),
            Carbon::parse('2026-06-03T00:00:00Z'),
        );

        $this->assertSame(['2026-06-01', '2026-06-02'], array_keys($days));
        $this->assertSame(10.0, $days['2026-06-01']['last_value']);
        $this->assertSame(25.0, $days['2026-06-02']['first_value']);
    }

    public function test_a_snapshot_at_exact_local_midnight_closes_the_previous_day(): void
    {
        // Bell emits its cumulative counters once a day at 22:00Z, which is
        // exactly 00:00 SAST. That snapshot is the CLOSING value of the day
        // that just ended -- booking it to the new day would shift every
        // day's production one day late.
        $days = $this->calculator->groupCumulativeReadingsByDay(
            [
                ['timestamp' => '2026-08-20T22:00:00Z', 'value' => 16461],
                ['timestamp' => '2026-08-21T22:00:00Z', 'value' => 16491],
            ],
            'Africa/Johannesburg',
            Carbon::parse('2026-08-19T00:00:00Z'),
            Carbon::parse('2026-08-22T12:00:00Z'),
        );

        $this->assertSame(['2026-08-20', '2026-08-21'], array_keys($days));

        $deltas = $this->calculator->dailyDeltas($days, null);

        $this->assertSame(30.0, $deltas['2026-08-21']['delta'], "Aug 21's 30 loads must book to Aug 21, not Aug 22.");
    }

    public function test_payload_units_convert_to_tonnes(): void
    {
        $this->assertSame(1.5, $this->calculator->payloadToTonnes(1500.0, 'kilogram'));
        $this->assertSame(3.0, $this->calculator->payloadToTonnes(3.0, 'tonnes'));
        $this->assertEqualsWithDelta(0.907, $this->calculator->payloadToTonnes(2000.0, 'pounds'), 0.001);
        $this->assertSame(2.0, $this->calculator->payloadToTonnes(2000.0, null), 'Bell default is kilograms.');
        $this->assertSame(0.0, $this->calculator->payloadToTonnes(null, 'kg'));
    }
}
