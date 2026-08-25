<?php

namespace App\Services\Guardian\Checks;

use App\Services\Guardian\CheckResult;
use App\Services\Guardian\Contracts\GuardianCheck;
use App\Support\ApiPayload;
use Illuminate\Support\Facades\DB;

/**
 * Database-queue backlog and failure-rate probe.
 *
 * Production drains the database queue from the scheduler's cron tick, so
 * a growing jobs table or an old available_at means the drain stopped (or
 * cannot keep up) -- exactly the failure that once piled 996 jobs onto
 * unserviced queues while every page kept loading.
 */
class QueueCheck implements GuardianCheck
{
    #[\Override]
    public function key(): string
    {
        return 'queue';
    }

    #[\Override]
    public function run(): CheckResult
    {
        try {
            $pending = DB::table('jobs')->count();

            /** @psalm-suppress MixedAssignment -- query-builder aggregates are untyped */
            $oldestAvailableAt = DB::table('jobs')
                ->whereNull('reserved_at')
                ->min('available_at');

            $oldestPendingSeconds = $oldestAvailableAt === null
                ? 0
                : max(0, now()->getTimestamp() - (int) $oldestAvailableAt);

            $failedLastHour = DB::table('failed_jobs')
                ->where('failed_at', '>=', now()->subHour())
                ->count();
        } catch (\Throwable $e) {
            return CheckResult::unknown('Queue tables unreadable.', [
                'error' => $e::class,
            ]);
        }

        $metrics = [
            'pending_jobs' => $pending,
            'oldest_pending_seconds' => $oldestPendingSeconds,
            'failed_last_hour' => $failedLastHour,
        ];

        if (
            $pending >= ApiPayload::int(config('guardian.queue.pending_critical'), 500)
            || $oldestPendingSeconds >= ApiPayload::int(config('guardian.queue.oldest_critical_seconds'), 900)
            || $failedLastHour >= ApiPayload::int(config('guardian.queue.failed_critical'), 20)
        ) {
            return CheckResult::critical('Queue backlog or failure rate is critical.', $metrics);
        }

        if (
            $pending >= ApiPayload::int(config('guardian.queue.pending_warning'), 100)
            || $oldestPendingSeconds >= ApiPayload::int(config('guardian.queue.oldest_warning_seconds'), 300)
            || $failedLastHour >= ApiPayload::int(config('guardian.queue.failed_warning'), 5)
        ) {
            return CheckResult::warning('Queue backlog or failure rate is elevated.', $metrics);
        }

        return CheckResult::healthy('Queue draining normally.', $metrics);
    }
}
