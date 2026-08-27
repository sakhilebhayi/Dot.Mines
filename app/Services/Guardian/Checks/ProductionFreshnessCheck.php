<?php

namespace App\Services\Guardian\Checks;

use App\Models\Integration;
use App\Services\Guardian\CheckResult;
use App\Services\Guardian\Contracts\GuardianCheck;
use App\Services\Guardian\FleetActivity;
use App\Services\Guardian\ProviderTelemetry;
use App\Support\ApiPayload;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Production-data freshness probe: are production counters still moving?
 *
 * The classic silent failure -- the Production page renders fine while the
 * numbers underneath stopped updating. Watches the newest write to
 * production_records for teams whose integrations actually observed a
 * "production" stream; wall-clock thresholds (not sync-interval multiples)
 * because production rows legitimately update less often than telemetry.
 */
class ProductionFreshnessCheck implements GuardianCheck
{
    /**
     * @psalm-suppress PossiblyUnusedMethod -- resolved by the container
     * from GuardianHealthController's check registry.
     */
    public function __construct(private readonly FleetActivity $fleet) {}

    #[\Override]
    public function key(): string
    {
        return 'production_freshness';
    }

    #[\Override]
    public function run(): CheckResult
    {
        /**
         * @psalm-suppress UnnecessaryVarAnnotation -- phpstan needs it (larastan infers stdClass here)
         *
         * @phpstan-var Collection<int, Integration> $connected */
        $connected = Integration::query()
            ->whereIn('status', ['connected', 'active'])
            ->get();

        $productionCapable = $connected
            ->filter(fn (Integration $integration): bool => $integration->hasCapability('production'));

        if ($productionCapable->isEmpty()) {
            return CheckResult::unknown('No production-capable integrations connected.');
        }

        /** @psalm-suppress MixedAssignment -- query-builder aggregates are untyped */
        $newestUpdatedAt = DB::table('production_records')
            ->whereIn('team_id', $productionCapable->pluck('team_id')->unique()->all())
            ->max('updated_at');

        if ($newestUpdatedAt === null) {
            return CheckResult::unknown('No production records have ever been written.');
        }

        $ageSeconds = max(0, (int) Carbon::parse((string) $newestUpdatedAt)->diffInSeconds(now()));

        $quiet = $this->fleet->isQuiet();
        $metrics = [
            'newest_production_write_age_seconds' => $ageSeconds,
            'fleet_quiet' => $quiet,
        ];

        // Production only accrues while machines move; see the note in
        // TelemetryIngestionCheck for why this cannot mask a failing sync.
        if ($quiet) {
            return CheckResult::healthy($this->fleet->describe(), $metrics);
        }

        // Production is calculated FROM provider telemetry. When the
        // provider's own readings have stalled (ProviderDataFreshnessCheck
        // surfaces that), frozen production is the starved input, not a
        // platform fault -- and the redeploy runbook cannot feed it.
        $readingAge = ProviderTelemetry::newestReadingAgeSeconds();
        $freshnessCriticalX = ApiPayload::float(config('guardian.freshness.critical_multiplier'), 4.0);

        if ($readingAge !== null
            && $readingAge >= (float) ProviderTelemetry::baselineIntervalSeconds() * $freshnessCriticalX) {
            $metrics['newest_reading_age_seconds'] = $readingAge;

            return CheckResult::unknown(
                'Provider telemetry is stale; production freshness cannot be judged while its input starves.',
                $metrics,
            );
        }

        if ($ageSeconds >= ApiPayload::int(config('guardian.freshness.production_critical_seconds'), 21600)) {
            return CheckResult::critical('Production data has stopped updating.', $metrics);
        }

        if ($ageSeconds >= ApiPayload::int(config('guardian.freshness.production_warning_seconds'), 7200)) {
            return CheckResult::warning('Production data is going stale.', $metrics);
        }

        return CheckResult::healthy('Production data updating.', $metrics);
    }
}
