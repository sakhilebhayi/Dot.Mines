<?php

namespace App\Services\Guardian;

use App\Support\ApiPayload;
use Illuminate\Support\Facades\Cache;

/**
 * Cache-backed hourly exception counter feeding ErrorRateCheck.
 *
 * Wired into the exception handler's report hook (bootstrap/app.php), so
 * every reported throwable increments the current hour's bucket without
 * any log parsing. Buckets expire on their own; never throws -- guardian
 * accounting must not be able to break real error handling.
 */
class ErrorCounter
{
    private const KEY_PREFIX = 'guardian:errors:';

    public const LAST_ERROR_KEY = 'guardian:errors:last';

    public function record(\Throwable $e): void
    {
        try {
            $key = self::KEY_PREFIX.now()->format('Y-m-d-H');

            Cache::add($key, 0, 7200);
            Cache::increment($key);

            Cache::put(self::LAST_ERROR_KEY, [
                'class' => $e::class,
                'message' => mb_substr($e->getMessage(), 0, 500),
                'at' => now()->toISOString(),
            ], 86400);
        } catch (\Throwable) {
            // Counting must never interfere with real error handling.
        }
    }

    public function countForHour(\DateTimeInterface $hour): int
    {
        return ApiPayload::int(Cache::get(self::KEY_PREFIX.$hour->format('Y-m-d-H')));
    }

    /**
     * @return array{class: string, message: string, at: string}|null
     */
    public function lastError(): ?array
    {
        $stored = ApiPayload::assoc(Cache::get(self::LAST_ERROR_KEY));

        $class = ApiPayload::str($stored['class'] ?? null);
        $message = ApiPayload::str($stored['message'] ?? null);
        $at = ApiPayload::str($stored['at'] ?? null);

        if ($class === '' || $at === '') {
            return null;
        }

        return ['class' => $class, 'message' => $message, 'at' => $at];
    }
}
