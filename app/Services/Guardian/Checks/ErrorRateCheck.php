<?php

namespace App\Services\Guardian\Checks;

use App\Services\Guardian\CheckResult;
use App\Services\Guardian\Contracts\GuardianCheck;
use App\Services\Guardian\ErrorCounter;
use App\Support\ApiPayload;

/**
 * Application exception-rate probe (reads ErrorCounter's hourly buckets).
 */
class ErrorRateCheck implements GuardianCheck
{
    /**
     * @psalm-suppress PossiblyUnusedMethod -- resolved by the container
     * (app(ErrorRateCheck::class) from GuardianHealthController), which
     * psalm cannot see.
     */
    public function __construct(private readonly ErrorCounter $counter) {}

    #[\Override]
    public function key(): string
    {
        return 'error_rate';
    }

    #[\Override]
    public function run(): CheckResult
    {
        $thisHour = $this->counter->countForHour(now());
        $prevHour = $this->counter->countForHour(now()->subHour());

        $metrics = [
            'errors_this_hour' => $thisHour,
            'errors_prev_hour' => $prevHour,
            'last_error' => $this->counter->lastError(),
        ];

        if ($thisHour >= ApiPayload::int(config('guardian.errors.critical_per_hour'), 50)) {
            return CheckResult::critical('Exception rate is critical.', $metrics);
        }

        if ($thisHour >= ApiPayload::int(config('guardian.errors.warning_per_hour'), 10)) {
            return CheckResult::warning('Exception rate is elevated.', $metrics);
        }

        return CheckResult::healthy('Exception rate normal.', $metrics);
    }
}
