<?php

namespace App\Services\Guardian\Checks;

use App\Models\Integration;
use App\Services\Guardian\CheckResult;
use App\Services\Guardian\Contracts\GuardianCheck;
use App\Services\Guardian\FleetActivity;
use App\Services\Guardian\ProviderTelemetry;
use App\Support\ApiPayload;

/**
 * Provider-feed freshness probe: is the MANUFACTURER still publishing?
 *
 * The signal TelemetryIngestionCheck used to conflate with pipeline
 * failure: machines are working, our sync runs and succeeds, but the
 * provider's snapshot readings stop advancing (observed live 2026-08-27:
 * Bell stalled ~1h against its declared 15-minute cadence). That is real
 * and worth surfacing -- but it is the provider's fault, not ours, so it
 * gets its own check key (its own guardian signature) and never proposes
 * a redeploy, which cannot fix an upstream feed.
 *
 * Warning, never critical: a stalled provider feed does not make this
 * platform unhealthy, and paging sev2 for it teaches people to ignore
 * the page that matters.
 */
class ProviderDataFreshnessCheck implements GuardianCheck
{
    /**
     * @psalm-suppress PossiblyUnusedMethod -- resolved by the container
     * from GuardianHealthController's check registry.
     */
    public function __construct(private readonly FleetActivity $fleet) {}

    #[\Override]
    public function key(): string
    {
        return 'provider_data_freshness';
    }

    #[\Override]
    public function run(): CheckResult
    {
        $connected = Integration::query()
            ->whereIn('status', ['connected', 'active'])
            ->exists();

        if (! $connected) {
            return CheckResult::unknown('No connected integrations -- no provider telemetry expected.');
        }

        $readingAge = ProviderTelemetry::newestReadingAgeSeconds();

        if ($readingAge === null) {
            return CheckResult::unknown('No provider readings have ever been stored.');
        }

        $baseline = ProviderTelemetry::baselineIntervalSeconds();
        $quiet = $this->fleet->isQuiet();

        $metrics = [
            'newest_reading_age_seconds' => $readingAge,
            'baseline_interval_seconds' => $baseline,
            'fleet_quiet' => $quiet,
        ];

        // Parked machines legitimately stop reporting; FleetActivity only
        // reports quiet when the syncs that would prove otherwise are
        // themselves healthy, so this cannot excuse a failing sync.
        if ($quiet) {
            return CheckResult::healthy($this->fleet->describe(), $metrics);
        }

        $criticalX = ApiPayload::float(config('guardian.freshness.critical_multiplier'), 4.0);

        if ($readingAge >= (float) $baseline * $criticalX) {
            return CheckResult::warning(
                'Provider telemetry has stalled -- machines are working but the provider feed has published no new readings. '
                .'Not a platform fault: check the provider portal or contact the manufacturer.',
                $metrics,
            );
        }

        return CheckResult::healthy('Provider telemetry advancing.', $metrics);
    }
}
