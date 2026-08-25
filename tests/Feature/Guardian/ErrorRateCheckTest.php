<?php

namespace Tests\Feature\Guardian;

use App\Services\Guardian\Checks\ErrorRateCheck;
use App\Services\Guardian\ErrorCounter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ErrorRateCheckTest extends TestCase
{
    use RefreshDatabase;

    public function test_healthy_when_no_errors_recorded(): void
    {
        $result = app(ErrorRateCheck::class)->run();

        $this->assertSame('healthy', $result->status());
        $this->assertSame(0, $result->toArray()['metrics']['errors_this_hour']);
    }

    public function test_counts_recorded_exceptions_in_the_current_hour(): void
    {
        $counter = app(ErrorCounter::class);
        $counter->record(new \RuntimeException('first'));
        $counter->record(new \RuntimeException('second'));

        $result = app(ErrorRateCheck::class)->run();

        $this->assertSame(2, $result->toArray()['metrics']['errors_this_hour']);
        $this->assertSame(\RuntimeException::class, $result->toArray()['metrics']['last_error']['class']);
    }

    public function test_warns_past_the_hourly_warning_threshold(): void
    {
        config(['guardian.errors.warning_per_hour' => 2, 'guardian.errors.critical_per_hour' => 100]);

        $counter = app(ErrorCounter::class);
        $counter->record(new \RuntimeException('a'));
        $counter->record(new \RuntimeException('b'));

        $this->assertSame('warning', app(ErrorRateCheck::class)->run()->status());
    }

    public function test_goes_critical_past_the_hourly_critical_threshold(): void
    {
        config(['guardian.errors.warning_per_hour' => 1, 'guardian.errors.critical_per_hour' => 3]);

        $counter = app(ErrorCounter::class);

        foreach (range(1, 3) as $i) {
            $counter->record(new \RuntimeException("error {$i}"));
        }

        $this->assertSame('critical', app(ErrorRateCheck::class)->run()->status());
    }

    public function test_reported_exceptions_reach_the_counter_through_the_handler_hook(): void
    {
        report(new \RuntimeException('reported via the global hook'));

        $result = app(ErrorRateCheck::class)->run();

        $this->assertGreaterThanOrEqual(1, $result->toArray()['metrics']['errors_this_hour']);
    }
}
