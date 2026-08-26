<?php

namespace App\Services\Guardian;

use App\Models\Integration;
use App\Models\Machine;
use App\Support\ApiPayload;
use Illuminate\Database\Eloquent\Collection;

/**
 * Is the fleet actually working right now?
 *
 * Data freshness only means something while machines are running. A parked
 * fleet produces no readings, and reporting that as a fault every night
 * trains people to ignore the alert on the night it matters.
 *
 * Quiet is derived from the fleet's own state rather than a clock: a fixed
 * window cannot know that one mine runs three shifts and another runs one,
 * and it would need a timezone per team to be even that wrong. An operator
 * who genuinely wants clock hours can still set them (guardian.quiet_hours
 * .window), but nothing depends on their doing so.
 *
 * The load-bearing guard is trustworthiness: an idle fleet only excuses
 * stale data when the sync feeding it is DEMONSTRABLY healthy. Otherwise a
 * broken sync would leave every machine looking idle -- because nothing is
 * updating them -- and quietly explain away its own failure.
 */
class FleetActivity
{
    /**
     * True when no machine is running and the syncs that would tell us
     * otherwise are working.
     */
    public function isQuiet(): bool
    {
        if (! $this->syncsAreTrustworthy()) {
            return false;
        }

        if ($this->withinConfiguredQuietWindow()) {
            return true;
        }

        $machines = Machine::query()->count();

        if ($machines === 0) {
            return false; // Nothing to reason about; let the checks speak.
        }

        return Machine::query()->where('status', 'active')->count() === 0;
    }

    public function describe(): string
    {
        return $this->withinConfiguredQuietWindow()
            ? 'Inside the configured quiet window, so no new readings are expected.'
            : 'No machines are running, so no new readings are expected while the fleet is idle.';
    }

    /**
     * A machine's status is only meaningful if something recent set it. If
     * the last sync failed, or is well past its own interval, the fleet's
     * apparent idleness is just as likely to be the absence of updates.
     */
    private function syncsAreTrustworthy(): bool
    {
        /**
         * @psalm-suppress UnnecessaryVarAnnotation -- phpstan needs it (larastan infers stdClass here)
         *
         * @phpstan-var Collection<int, Integration> $integrations
         */
        $integrations = Integration::query()
            ->whereIn('status', ['connected', 'active'])
            ->get();

        if ($integrations->isEmpty()) {
            return false;
        }

        return $integrations->every(function (Integration $integration): bool {
            if ($integration->last_sync_status === 'failed') {
                return false;
            }

            $lastSyncAt = $integration->last_sync_at;

            if ($lastSyncAt === null) {
                return false;
            }

            $interval = ApiPayload::int(
                config("integrations.manufacturers.{$integration->provider}.sync_interval"),
                ApiPayload::int(config('integrations.jobs.machines_sync_interval'), 300),
            );

            return $lastSyncAt->diffInSeconds(now()) <= $interval * 2;
        });
    }

    /**
     * Optional and off by default: an explicit "HH:MM-HH:MM" window for
     * operators who would rather state their quiet hours than have them
     * inferred. Supports a window that crosses midnight.
     */
    private function withinConfiguredQuietWindow(): bool
    {
        $window = ApiPayload::str(config('guardian.quiet_hours.window'));

        if ($window === '' || ! preg_match('/^(\d{2}:\d{2})-(\d{2}:\d{2})$/', $window, $matches)) {
            return false;
        }

        $now = now(ApiPayload::str(config('guardian.quiet_hours.timezone'), ApiPayload::str(config('app.timezone'), 'UTC')))->format('H:i');
        [, $from, $until] = $matches;

        return $from <= $until
            ? ($now >= $from && $now < $until)
            : ($now >= $from || $now < $until); // crosses midnight
    }
}
