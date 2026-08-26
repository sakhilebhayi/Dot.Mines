<?php

namespace App\Services\Guardian;

use App\Support\ApiPayload;
use Illuminate\Support\Facades\Cache;

/**
 * Cache-backed hourly error counter feeding ErrorRateCheck, with two feeds:
 *
 *  1. The exception handler's report hook (bootstrap/app.php) counts every
 *     REPORTED throwable -- including ones whose custom report() suppresses
 *     logging.
 *  2. A MessageLogged listener (CountGuardianLogErrors) counts every
 *     error-level-or-worse LOG record -- the caught-and-logged failures
 *     the report hook never sees. RealtimeEventScheduler logged such a
 *     failure twice a minute for ~17 hours (2026-08-26) while this
 *     counter, then report-hook-only, read zero.
 *
 * A reported exception normally ALSO produces an error log line, so feed 1
 * registers each throwable's object id and feed 2 skips log records whose
 * context carries an already-counted exception. That registry is in-memory,
 * which is why this class MUST be bound as a singleton (AppServiceProvider).
 *
 * Buckets expire on their own; nothing here ever throws -- guardian
 * accounting must not be able to break real error handling or logging.
 */
class ErrorCounter
{
    private const KEY_PREFIX = 'guardian:errors:';

    public const LAST_ERROR_KEY = 'guardian:errors:last';

    /** Log levels that count as errors (PSR-3 names, worst first). */
    private const COUNTED_LEVELS = ['emergency', 'alert', 'critical', 'error'];

    /** Bound so a long-lived worker process cannot grow it forever. */
    private const MAX_TRACKED_EXCEPTIONS = 500;

    /** @var array<int, true> object ids of throwables feed 1 already counted */
    private array $countedExceptionIds = [];

    public function record(\Throwable $e): void
    {
        try {
            $this->rememberCounted($e);
            $this->bump($e::class, $e->getMessage());
        } catch (\Throwable) {
            // Counting must never interfere with real error handling.
        }
    }

    /**
     * Count one log record (feed 2). Non-error levels are ignored; a record
     * whose context carries a throwable already counted by record() is
     * skipped so a reported exception is never counted twice.
     *
     * @param  array<array-key, mixed>  $context
     */
    public function recordLogRecord(string $level, string $message, array $context): void
    {
        try {
            if (! in_array(strtolower($level), self::COUNTED_LEVELS, true)) {
                return;
            }

            /** @psalm-suppress MixedAssignment -- log context values are mixed by nature */
            $exception = $context['exception'] ?? null;

            if ($exception instanceof \Throwable) {
                if (isset($this->countedExceptionIds[spl_object_id($exception)])) {
                    return;
                }
                $this->rememberCounted($exception);
                $this->bump($exception::class, $exception->getMessage());

                return;
            }

            $this->bump('log.'.strtolower($level), $message);
        } catch (\Throwable) {
            // Counting must never interfere with logging.
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

    private function bump(string $class, string $message): void
    {
        $key = self::KEY_PREFIX.now()->format('Y-m-d-H');

        Cache::add($key, 0, 7200);
        Cache::increment($key);

        Cache::put(self::LAST_ERROR_KEY, [
            'class' => $class,
            'message' => mb_substr($message, 0, 500),
            'at' => now()->toISOString(),
        ], 86400);
    }

    private function rememberCounted(\Throwable $e): void
    {
        if (count($this->countedExceptionIds) >= self::MAX_TRACKED_EXCEPTIONS) {
            $this->countedExceptionIds = array_slice($this->countedExceptionIds, 250, null, true);
        }

        $this->countedExceptionIds[spl_object_id($e)] = true;
    }
}
