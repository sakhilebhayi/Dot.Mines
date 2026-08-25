<?php

namespace Tests\Feature\Guardian;

use App\Services\Guardian\Checks\CacheCheck;
use App\Services\Guardian\Checks\DatabaseCheck;
use App\Services\Guardian\Checks\QueueCheck;
use App\Services\Guardian\Checks\SchedulerCheck;
use App\Services\Guardian\SchedulerHeartbeat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class CoreChecksTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_check_is_healthy_when_the_database_answers(): void
    {
        $result = app(DatabaseCheck::class)->run();

        $this->assertSame('healthy', $result->status());
        $this->assertArrayHasKey('latency_ms', $result->toArray()['metrics']);
    }

    public function test_cache_check_round_trips_a_value(): void
    {
        $this->assertSame('healthy', app(CacheCheck::class)->run()->status());
    }

    public function test_queue_check_is_healthy_with_an_empty_queue(): void
    {
        $result = app(QueueCheck::class)->run();

        $this->assertSame('healthy', $result->status());
        $this->assertSame(0, $result->toArray()['metrics']['pending_jobs']);
    }

    public function test_queue_check_warns_on_backlog_depth(): void
    {
        config(['guardian.queue.pending_warning' => 2, 'guardian.queue.pending_critical' => 100]);

        $this->insertPendingJobs(3, ageSeconds: 10);

        $this->assertSame('warning', app(QueueCheck::class)->run()->status());
    }

    public function test_queue_check_goes_critical_when_the_oldest_job_is_too_old(): void
    {
        config(['guardian.queue.oldest_critical_seconds' => 900]);

        $this->insertPendingJobs(1, ageSeconds: 1000);

        $result = app(QueueCheck::class)->run();

        $this->assertSame('critical', $result->status());
        $this->assertGreaterThanOrEqual(1000, $result->toArray()['metrics']['oldest_pending_seconds']);
    }

    public function test_queue_check_counts_recent_failed_jobs(): void
    {
        config(['guardian.queue.failed_warning' => 2]);

        for ($i = 0; $i < 3; $i++) {
            DB::table('failed_jobs')->insert([
                'uuid' => (string) Str::uuid(),
                'connection' => 'database',
                'queue' => 'default',
                'payload' => '{}',
                'exception' => 'RuntimeException: boom',
                'failed_at' => now()->subMinutes(10),
            ]);
        }

        $result = app(QueueCheck::class)->run();

        $this->assertSame('warning', $result->status());
        $this->assertSame(3, $result->toArray()['metrics']['failed_last_hour']);
    }

    public function test_scheduler_check_reads_the_heartbeat(): void
    {
        SchedulerHeartbeat::beat();

        $this->assertSame('healthy', app(SchedulerCheck::class)->run()->status());
    }

    public function test_scheduler_check_warns_when_heartbeat_is_stale(): void
    {
        Cache::put(SchedulerHeartbeat::CACHE_KEY, now()->subMinutes(6)->toISOString(), 3600);

        $this->assertSame('warning', app(SchedulerCheck::class)->run()->status());
    }

    public function test_scheduler_check_goes_critical_when_heartbeat_is_very_stale(): void
    {
        Cache::put(SchedulerHeartbeat::CACHE_KEY, now()->subMinutes(20)->toISOString(), 3600);

        $this->assertSame('critical', app(SchedulerCheck::class)->run()->status());
    }

    public function test_scheduler_check_is_unknown_without_a_heartbeat(): void
    {
        Cache::forget(SchedulerHeartbeat::CACHE_KEY);

        $this->assertSame('unknown', app(SchedulerCheck::class)->run()->status());
    }

    private function insertPendingJobs(int $count, int $ageSeconds): void
    {
        for ($i = 0; $i < $count; $i++) {
            DB::table('jobs')->insert([
                'queue' => 'default',
                'payload' => '{}',
                'attempts' => 0,
                'reserved_at' => null,
                'available_at' => now()->subSeconds($ageSeconds)->getTimestamp(),
                'created_at' => now()->subSeconds($ageSeconds)->getTimestamp(),
            ]);
        }
    }
}
