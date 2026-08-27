<?php

namespace App\Services\Guardian;

use App\Models\Integration;
use App\Models\MachineMetric;
use App\Support\ApiPayload;
use Illuminate\Support\Carbon;

/**
 * Shared questions about the provider side of telemetry: how often the
 * connected manufacturers say they publish, and when a reading last
 * actually advanced. Used by the checks that must separate "our pipeline
 * broke" from "the provider stopped publishing".
 */
final class ProviderTelemetry
{
    /**
     * The slowest connected provider's declared sync interval -- faster
     * providers only make data fresher, never staler.
     */
    public static function baselineIntervalSeconds(): int
    {
        $default = ApiPayload::int(config('integrations.jobs.machines_sync_interval'), 300);

        return ApiPayload::int(
            Integration::query()
                ->whereIn('status', ['connected', 'active'])
                ->pluck('provider')
                ->map(fn (string $provider): int => ApiPayload::int(
                    config("integrations.manufacturers.{$provider}.sync_interval"),
                    $default,
                ))
                ->max(),
            $default,
        );
    }

    /**
     * Age of the newest reading BY THE PROVIDER'S OWN CLOCK (recorded_at),
     * or null when nothing has ever been stored.
     */
    public static function newestReadingAgeSeconds(): ?int
    {
        /** @psalm-suppress MixedAssignment -- query-builder aggregates are untyped */
        $newestRecordedAt = MachineMetric::query()->max('recorded_at');

        if ($newestRecordedAt === null) {
            return null;
        }

        return max(0, (int) Carbon::parse((string) $newestRecordedAt)->diffInSeconds(now()));
    }
}
