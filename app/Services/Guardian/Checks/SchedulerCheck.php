<?php

namespace App\Services\Guardian\Checks;

use App\Services\Guardian\CheckResult;
use App\Services\Guardian\Contracts\GuardianCheck;
use App\Services\Guardian\SchedulerHeartbeat;
use App\Support\ApiPayload;

/**
 * Cron scheduler proof-of-life probe (reads SchedulerHeartbeat).
 */
class SchedulerCheck implements GuardianCheck
{
    #[\Override]
    public function key(): string
    {
        return 'scheduler';
    }

    #[\Override]
    public function run(): CheckResult
    {
        $ageSeconds = SchedulerHeartbeat::lastBeatAgeSeconds();

        if ($ageSeconds === null) {
            return CheckResult::unknown('No scheduler heartbeat recorded yet.');
        }

        $metrics = ['heartbeat_age_seconds' => $ageSeconds];

        if ($ageSeconds >= ApiPayload::int(config('guardian.scheduler.critical_seconds'), 900)) {
            return CheckResult::critical('Scheduler heartbeat is very stale -- cron appears down.', $metrics);
        }

        if ($ageSeconds >= ApiPayload::int(config('guardian.scheduler.warning_seconds'), 300)) {
            return CheckResult::warning('Scheduler heartbeat is stale.', $metrics);
        }

        return CheckResult::healthy('Scheduler beating.', $metrics);
    }
}
