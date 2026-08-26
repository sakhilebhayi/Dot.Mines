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
 * this check verifies the outcome -- that rows are actually being WRITTEN
 * -- so a sync that "succeeds" while storing nothing still surfaces.
 *
 * Freshness is judged on created_at (when we stored the row), not on
 * recorded_at (when the machine took the reading). Those are different
 * questions: a machine parked overnight legitimately has a hours-old
 * reading, and judging platform health by it reported CRITICAL on
 * production (2026-08-26) one minute after 26 rows landed cleanly. The
 * reading lag is still published as a metric, because it is a useful
 * FLEET-ACTIVITY signal -- it is just not a verdict on this platform.
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

        $newestIngestedAt = MachineMetric::query()->max('created_at');

        if ($newestIngestedAt === null) {
            return CheckResult::unknown('No telemetry has ever been ingested.');
        }

        $ageSeconds = max(0, (int) Carbon::parse((string) $newestIngestedAt)->diffInSeconds(now()));

        $newestRecordedAt = MachineMetric::query()->max('recorded_at');
        $readingAgeSeconds = $newestRecordedAt === null
            ? null
            : max(0, (int) Carbon::parse((string) $newestRecordedAt)->diffInSeconds(now()));

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
            'newest_ingest_age_seconds' => $ageSeconds,
            'newest_reading_age_seconds' => $readingAgeSeconds,
            'baseline_interval_seconds' => $baselineInterval,
            // Kept under its original name so anything already reading this
            // metric (dashboards, the guardian's archived incidents) keeps
            // working; it now tracks ingestion, which is what it always
            // claimed to measure.
            'newest_metric_age_seconds' => $ageSeconds,
        ];

        $warningX = ApiPayload::float(config('guardian.freshness.warning_multiplier'), 2.0);
        $criticalX = ApiPayload::float(config('guardian.freshness.critical_multiplier'), 4.0);

        if ($ageSeconds >= (float) $baselineInterval * $criticalX) {
            return CheckResult::critical('Telemetry ingestion has stopped -- no rows written.', $metrics);
        }

        if ($ageSeconds >= (float) $baselineInterval * $warningX) {
            return CheckResult::warning('Telemetry ingestion is lagging -- rows are being written late.', $metrics);
        }

        return CheckResult::healthy('Telemetry flowing.', $metrics);
    }
}
