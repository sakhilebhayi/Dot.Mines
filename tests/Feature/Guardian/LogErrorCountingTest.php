<?php

namespace Tests\Feature\Guardian;

use App\Services\Guardian\Checks\ErrorRateCheck;
use App\Services\Guardian\ErrorCounter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Feed 2 of the guardian error counter: caught-and-logged failures.
 * RealtimeEventScheduler logged an error twice a minute for ~17 hours
 * (2026-08-26) while the report-hook-only counter read zero -- these tests
 * pin that error-level log writes now count, non-errors don't, and a
 * reported exception (which is also logged by the handler) counts once.
 */
class LogErrorCountingTest extends TestCase
{
    use RefreshDatabase;

    private function counter(): ErrorCounter
    {
        return app(ErrorCounter::class);
    }

    public function test_the_counter_is_a_singleton(): void
    {
        $this->assertSame($this->counter(), $this->counter());
    }

    public function test_an_error_log_write_counts(): void
    {
        Log::error('Failed to schedule alert generation', ['error' => 'Unknown column']);

        $this->assertSame(1, $this->counter()->countForHour(now()));

        $last = $this->counter()->lastError();
        $this->assertNotNull($last);
        $this->assertSame('log.error', $last['class']);
        $this->assertStringContainsString('alert generation', $last['message']);
    }

    public function test_critical_and_worse_levels_count(): void
    {
        Log::critical('disk on fire');
        Log::alert('still on fire');
        Log::emergency('everything on fire');

        $this->assertSame(3, $this->counter()->countForHour(now()));
    }

    public function test_warning_and_below_do_not_count(): void
    {
        Log::warning('Bell integration: API error', ['status' => 405]);
        Log::info('sync finished');
        Log::debug('noise');

        $this->assertSame(0, $this->counter()->countForHour(now()));
    }

    public function test_a_reported_exception_counts_exactly_once_despite_also_being_logged(): void
    {
        report(new \RuntimeException('reported and then logged by the handler'));

        $this->assertSame(1, $this->counter()->countForHour(now()));
    }

    public function test_dedupe_holds_when_both_feeds_see_the_same_exception(): void
    {
        // Force both paths explicitly (the previous test could pass
        // trivially if the test-env handler skipped its log write): the
        // report hook counts it, then the handler-style log write with the
        // same throwable in context must be skipped.
        $exception = new \RuntimeException('seen by both feeds');

        $this->counter()->record($exception);
        Log::error($exception->getMessage(), ['exception' => $exception]);

        $this->assertSame(1, $this->counter()->countForHour(now()));
    }

    public function test_a_logged_but_never_reported_exception_still_counts(): void
    {
        Log::error('caught upstream', ['exception' => new \RuntimeException('never reported')]);

        $this->assertSame(1, $this->counter()->countForHour(now()));

        $last = $this->counter()->lastError();
        $this->assertNotNull($last);
        $this->assertSame(\RuntimeException::class, $last['class']);
    }

    public function test_log_fed_errors_move_the_error_rate_check(): void
    {
        config(['guardian.errors.warning_per_hour' => 2, 'guardian.errors.critical_per_hour' => 100]);

        Log::error('failure one');
        Log::error('failure two');

        $result = app(ErrorRateCheck::class)->run();

        $this->assertSame('warning', $result->status());
        $this->assertSame(2, $result->toArray()['metrics']['errors_this_hour']);
    }
}
