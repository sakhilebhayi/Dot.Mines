<?php

namespace App\Services\Guardian\Checks;

use App\Models\Integration;
use App\Services\Guardian\CheckResult;
use App\Services\Guardian\Contracts\GuardianCheck;
use App\Support\ApiPayload;
use Illuminate\Database\Eloquent\Collection;

/**
 * Manufacturer-integration sync recency probe.
 *
 * Compares each connected integration's last sync against its OWN declared
 * interval (config integrations.manufacturers.*.sync_interval -- Bell 900s,
 * most others 300s): beyond 2x the data is late, beyond 4x it has
 * effectively stopped. Also surfaces per-stream failures from sync_streams
 * -- the "fleet syncs fine but production silently fails" case. This is the
 * detector for "the page loads but production data stopped updating".
 */
class IntegrationSyncCheck implements GuardianCheck
{
    #[\Override]
    public function key(): string
    {
        return 'integration_sync';
    }

    #[\Override]
    public function run(): CheckResult
    {
        /**
         * @psalm-suppress UnnecessaryVarAnnotation -- phpstan needs it (larastan infers stdClass here)
         *
         * @phpstan-var Collection<int, Integration> $integrations */
        $integrations = Integration::query()
            ->whereIn('status', ['connected', 'active'])
            ->get();

        if ($integrations->isEmpty()) {
            return CheckResult::unknown('No connected integrations to monitor.');
        }

        $warningX = ApiPayload::float(config('guardian.freshness.warning_multiplier'), 2.0);
        $criticalX = ApiPayload::float(config('guardian.freshness.critical_multiplier'), 4.0);

        $worst = CheckResult::healthy();
        $rows = [];

        foreach ($integrations as $integration) {
            $interval = $this->syncIntervalSeconds($integration);

            $lastSyncAt = $integration->last_sync_at ?? $integration->created_at;
            $lagSeconds = max(0, (int) $lastSyncAt->diffInSeconds(now()));

            $failedStreams = [];

            /** @psalm-suppress MixedAssignment -- assoc() values are mixed by design */
            foreach (ApiPayload::assoc($integration->sync_streams) as $stream => $detail) {
                if (is_array($detail) && ($detail['status'] ?? null) === 'failed') {
                    $failedStreams[] = $stream;
                }
            }

            $status = CheckResult::HEALTHY;

            if ($lagSeconds >= (float) $interval * $criticalX) {
                $status = CheckResult::CRITICAL;
            } elseif ($lagSeconds >= (float) $interval * $warningX || $failedStreams !== [] || $integration->last_sync_status === 'failed') {
                $status = CheckResult::WARNING;
            }

            $rows[] = [
                'integration_id' => $integration->id,
                'provider' => $integration->provider,
                'team_id' => $integration->team_id,
                'status' => $status,
                'lag_seconds' => $lagSeconds,
                'interval_seconds' => $interval,
                'failed_streams' => $failedStreams,
            ];

            $candidate = match ($status) {
                CheckResult::CRITICAL => CheckResult::critical(),
                CheckResult::WARNING => CheckResult::warning(),
                default => CheckResult::healthy(),
            };

            if ($candidate->isWorseThan($worst)) {
                $worst = $candidate;
            }
        }

        $metrics = ['integrations' => $rows];

        return match ($worst->status()) {
            CheckResult::CRITICAL => CheckResult::critical('At least one integration sync has stopped.', $metrics),
            CheckResult::WARNING => CheckResult::warning('At least one integration sync is overdue or failing.', $metrics),
            default => CheckResult::healthy('All integration syncs are current.', $metrics),
        };
    }

    private function syncIntervalSeconds(Integration $integration): int
    {
        $interval = ApiPayload::int(
            config("integrations.manufacturers.{$integration->provider}.sync_interval"),
            0,
        );

        if ($interval > 0) {
            return $interval;
        }

        return ApiPayload::int(config('integrations.jobs.machines_sync_interval'), 300);
    }
}
