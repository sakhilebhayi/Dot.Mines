<?php

namespace App\Services\Guardian\Checks;

use App\Models\Integration;
use App\Models\MachineMetric;
use App\Services\Guardian\CheckResult;
use App\Services\Guardian\Contracts\GuardianCheck;
use App\Support\ApiPayload;
use Illuminate\Support\Carbon;

/**
 * Telemetry ingestion probe: is fresh machine data actually LANDING?
 *
 * IntegrationSyncCheck trusts what the sync pipeline says about itself;
 * this check verifies the outcome -- the age of the newest machine_metrics
 * row -- so a sync that "succeeds" while writing nothing still surfaces.
 */
class TelemetryIngestionCheck implements GuardianCheck
{
    #[\Override]
    public function key(): string
    {
        return 'telemetry_ingestion';
    }

    #[\Override]
    public function run(): CheckResult
    {
        $providers = Integration::query()
            ->whereIn('status', ['connected', 'active'])
            ->pluck('provider');

        if ($providers->isEmpty()) {
            return CheckResult::unknown('No connected integrations -- no telemetry expected.');
        }

        $newestRecordedAt = MachineMetric::query()->max('recorded_at');

        if ($newestRecordedAt === null) {
            return CheckResult::unknown('No telemetry has ever been ingested.');
        }

        $ageSeconds = max(0, (int) Carbon::parse((string) $newestRecordedAt)->diffInSeconds(now()));

        // Judge freshness against the SLOWEST connected provider's declared
        // interval -- faster providers only make data fresher, never staler.
        $baselineInterval = ApiPayload::int(
            $providers
                ->map(fn (string $provider): int => ApiPayload::int(
                    config("integrations.manufacturers.{$provider}.sync_interval"),
                    ApiPayload::int(config('integrations.jobs.machines_sync_interval'), 300),
                ))
                ->max(),
            300,
        );

        $metrics = [
            'newest_metric_age_seconds' => $ageSeconds,
            'baseline_interval_seconds' => $baselineInterval,
        ];

        $warningX = ApiPayload::float(config('guardian.freshness.warning_multiplier'), 2.0);
        $criticalX = ApiPayload::float(config('guardian.freshness.critical_multiplier'), 4.0);

        if ($ageSeconds >= (float) $baselineInterval * $criticalX) {
            return CheckResult::critical('Telemetry ingestion has stopped.', $metrics);
        }

        if ($ageSeconds >= (float) $baselineInterval * $warningX) {
            return CheckResult::warning('Telemetry ingestion is lagging.', $metrics);
        }

        return CheckResult::healthy('Telemetry flowing.', $metrics);
    }
}
