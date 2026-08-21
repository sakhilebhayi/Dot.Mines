<?php

namespace Tests\Unit;

use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

/**
 * Shared hosting has no resident queue worker, so routes/console.php
 * schedules a per-minute `queue:work --stop-when-empty` drain. It must be
 * registered, and it must only actually run when the queue driver is
 * `database` -- under the `sync` driver there is nothing to drain and the
 * command would burn a scheduler slot every minute for nothing.
 */
class QueueDrainScheduleTest extends TestCase
{
    private function drainEvent(): ?object
    {
        foreach (app(Schedule::class)->events() as $event) {
            if (str_contains((string) $event->command, 'queue:work')) {
                return $event;
            }
        }

        return null;
    }

    public function test_queue_drain_is_scheduled_every_minute_without_stacking(): void
    {
        $event = $this->drainEvent();

        $this->assertNotNull($event, 'routes/console.php must schedule a queue:work drain.');
        $this->assertSame('* * * * *', $event->expression);
        $this->assertStringContainsString('--stop-when-empty', (string) $event->command);
        $this->assertStringContainsString('--max-time=50', (string) $event->command, 'Each drain must end inside the minute so cron ticks never stack.');
    }

    public function test_queue_drain_only_runs_on_the_database_driver(): void
    {
        $event = $this->drainEvent();
        $this->assertNotNull($event);

        config()->set('queue.default', 'database');
        $this->assertTrue($event->filtersPass(app()));

        config()->set('queue.default', 'sync');
        $this->assertFalse($event->filtersPass(app()));
    }
}
