<?php

namespace App\Services\Guardian;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Cache-backed proof-of-life for the metric ingest path.
 *
 * Since the §19 dedupe, a metric row is only written when the provider's
 * own recorded_at advances -- so max(created_at) measures how often the
 * PROVIDER publishes, not whether OUR pipeline works. This probe is
 * recorded every time syncMachineMetrics() reaches a decision (write OR
 * dedupe-skip), which is the outcome the dedupe hides: the pipeline ran,
 * looked at the snapshot, and did the right thing.
 *
 * Deliberately NOT recorded when the write throws: a pipeline that
 * cannot store rows must not report itself alive.
 */
final class MetricIngestProbe
{
    public const CACHE_KEY = 'guardian:metric-ingest-probe';

    public static function record(): void
    {
        Cache::put(self::CACHE_KEY, now()->toISOString(), 86400);
    }

    public static function ageSeconds(): ?int
    {
        $raw = Cache::get(self::CACHE_KEY);

        if (! is_string($raw) || $raw === '') {
            return null;
        }

        try {
            $recordedAt = Carbon::parse($raw);
        } catch (\Throwable) {
            return null;
        }

        return max(0, (int) $recordedAt->diffInSeconds(now()));
    }
}
