<?php

namespace App\Services\Guardian;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Cache-backed proof-of-life for the cron scheduler.
 *
 * routes/console.php beats every minute; SchedulerCheck reads the age. A
 * missing or stale beat is how the guardian learns that cron itself died
 * -- the failure mode where every scheduled sync silently stops while the
 * web app keeps serving pages.
 */
final class SchedulerHeartbeat
{
    public const CACHE_KEY = 'guardian:scheduler-heartbeat';

    public static function beat(): void
    {
        Cache::put(self::CACHE_KEY, now()->toISOString(), 3600);
    }

    public static function lastBeatAgeSeconds(): ?int
    {
        $raw = Cache::get(self::CACHE_KEY);

        if (! is_string($raw) || $raw === '') {
            return null;
        }

        try {
            $beatAt = Carbon::parse($raw);
        } catch (\Throwable) {
            return null;
        }

        return max(0, (int) $beatAt->diffInSeconds(now()));
    }
}
