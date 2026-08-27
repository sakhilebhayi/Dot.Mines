<?php

namespace App\Http\Controllers;

use App\Services\Guardian\Checks\CacheCheck;
use App\Services\Guardian\Checks\DatabaseCheck;
use App\Services\Guardian\Checks\ErrorRateCheck;
use App\Services\Guardian\Checks\IntegrationSyncCheck;
use App\Services\Guardian\Checks\ProductionFreshnessCheck;
use App\Services\Guardian\Checks\ProviderDataFreshnessCheck;
use App\Services\Guardian\Checks\QueueCheck;
use App\Services\Guardian\Checks\SchedulerCheck;
use App\Services\Guardian\Checks\TelemetryIngestionCheck;
use App\Services\Guardian\Contracts\GuardianCheck;
use App\Services\Guardian\GuardianHealthReport;
use Illuminate\Http\JsonResponse;

/**
 * Machine-readable production health report for the Dot.Brain guardian.
 *
 * Complements the human/probe-facing /health and /up/realtime endpoints:
 * this one speaks the cross-platform dot-guardian/v1 contract (per-check
 * healthy/warning/critical/unknown) and covers the "app is up but the data
 * stopped moving" class of failure that a liveness probe cannot see.
 * Always answers 200 when authenticated -- the body is the signal, so the
 * caller can distinguish "endpoint unreachable" from "checks failing".
 */
class GuardianHealthController extends Controller
{
    /** @var list<class-string<GuardianCheck>> */
    private const CHECKS = [
        DatabaseCheck::class,
        CacheCheck::class,
        QueueCheck::class,
        SchedulerCheck::class,
        ErrorRateCheck::class,
        IntegrationSyncCheck::class,
        TelemetryIngestionCheck::class,
        ProviderDataFreshnessCheck::class,
        ProductionFreshnessCheck::class,
    ];

    public function __invoke(): JsonResponse
    {
        $checks = array_map(
            static function (string $class): GuardianCheck {
                $check = app($class);

                if (! $check instanceof GuardianCheck) {
                    throw new \RuntimeException("{$class} is not a GuardianCheck.");
                }

                return $check;
            },
            self::CHECKS,
        );

        return response()->json((new GuardianHealthReport($checks))->toArray());
    }
}
