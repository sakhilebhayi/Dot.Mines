<?php

namespace App\Console\Commands;

use App\Services\Integration\BellHistoricalTelemetryService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * BellWatchLocationsCommand
 *
 * Persistent artisan process for high-frequency Bell Equipment GPS polling.
 *
 * Laravel's cron scheduler has a minimum resolution of 1 minute.  For
 * intervals shorter than that (30 s, 15 s, 10 s, 5 s), this command runs
 * as a long-lived process managed by Supervisor — identical to how queue
 * workers operate.
 *
 * INTERVAL ROUTING
 * ─────────────────────────────────────────────────────────────────────────
 * BELL_LOCATION_POLL_SECONDS  │ Mechanism
 * ─────────────────────────────────────────────────────────────────────────
 *  300 (5 min, default)       │ Laravel scheduler  (everyFiveMinutes)
 *  120 (2 min)                │ Laravel scheduler  (everyTwoMinutes)
 *   60 (1 min)                │ Laravel scheduler  (everyMinute)
 *   30 / 15 / 10 / 5          │ THIS command via Supervisor
 * ─────────────────────────────────────────────────────────────────────────
 *
 * For sub-minute polling, add to supervisord.conf:
 *
 *   [program:bell-locations]
 *   command=php /var/www/html/artisan bell:watch-locations
 *   autostart=true
 *   autorestart=true
 *   redirect_stderr=true
 *   stdout_logfile=/var/log/bell-locations.log
 *
 * USAGE
 *   php artisan bell:watch-locations           # persistent loop (Supervisor mode)
 *   php artisan bell:watch-locations --once    # single sync and exit (scheduler uses this)
 *   php artisan bell:watch-locations --interval=10  # override interval at runtime
 */
class BellWatchLocationsCommand extends Command
{
    protected $signature = 'bell:watch-locations
                            {--interval= : Override poll interval in seconds (min 5)}
                            {--once       : Run a single sync cycle and exit}';

    protected $description = 'Poll Bell Equipment Locations API at configurable intervals for near-real-time GPS tracking.';

    /** Distributed-lock key — prevents two concurrent instances. */
    private const LOCK_KEY = 'bell_watch_locations_running';

    private bool $shouldStop = false;

    /**
     * Execute the command.
     */
    public function handle(): int
    {
        $intervalSeconds = $this->resolveInterval();

        // ── Single-run mode (called by the scheduler fallback) ──────────────
        if ($this->option('once')) {
            return $this->runCycle($intervalSeconds) ? Command::SUCCESS : Command::FAILURE;
        }

        // ── Persistent loop mode ────────────────────────────────────────────
        // Acquire a distributed lock so only one instance runs at a time.
        $lockTtl = max(60, $intervalSeconds * 10);
        $lock = Cache::lock(self::LOCK_KEY, $lockTtl);

        if (! $lock->get()) {
            $this->warn('Another bell:watch-locations process is already running. Exiting.');

            return Command::SUCCESS;
        }

        $this->registerSignalHandlers();

        $lookbackSeconds = (int) ceil(
            $intervalSeconds * config('integrations.bell_polling.lookback_multiplier', 2.0)
        );

        $this->info(sprintf(
            'Bell location watcher started — interval: %ds, lookback window: %ds. Press Ctrl+C to stop.',
            $intervalSeconds,
            $lookbackSeconds,
        ));

        try {
            while (! $this->shouldStop) {
                $cycleStart = microtime(true);

                $this->runCycle($intervalSeconds);

                // Sleep for the remainder of the interval, interrupting on SIGTERM/SIGINT.
                $elapsed = microtime(true) - $cycleStart;
                $remaining = max(0, $intervalSeconds - $elapsed);
                $this->sleepInterruptible((int) ceil($remaining));
            }
        } finally {
            $lock->release();
        }

        $this->info('Bell location watcher stopped gracefully.');

        return Command::SUCCESS;
    }

    // ── Private helpers ─────────────────────────────────────────────────────

    /**
     * Resolve the effective poll interval from option → config → default.
     */
    private function resolveInterval(): int
    {
        $raw = $this->option('interval')
            ?? config('integrations.bell_polling.location_interval_seconds', 60);

        return max(5, (int) $raw); // hard floor: 5 seconds
    }

    /**
     * Run one location sync cycle, calculating the lookback window from the interval.
     */
    private function runCycle(int $intervalSeconds): bool
    {
        $service = $this->makeService();

        if ($service === null) {
            return false;
        }

        $multiplier = (float) config('integrations.bell_polling.lookback_multiplier', 2.0);
        $lookbackSeconds = (int) ceil($intervalSeconds * $multiplier);
        // Express in fractional hours; never less than 15 seconds worth.
        $lookbackHours = max(15 / 3600, $lookbackSeconds / 3600);

        try {
            $result = $service->syncSignal('Locations', hours: $lookbackHours);

            $this->line(sprintf(
                '[%s] Locations synced — fetched: %d  inserted: %d  skipped: %d',
                now()->format('H:i:s'),
                $result['fetched'] ?? 0,
                $result['inserted'] ?? 0,
                $result['skipped'] ?? 0,
            ));

            return true;
        } catch (\Throwable $e) {
            $this->error(sprintf('[%s] Sync cycle failed: %s', now()->format('H:i:s'), $e->getMessage()));

            Log::warning('BellWatchLocationsCommand: sync cycle failed', [
                'error' => $e->getMessage(),
                'interval' => $intervalSeconds,
            ]);

            return false;
        }
    }

    /**
     * Sleep in 1-second increments so SIGTERM/SIGINT signals are honoured promptly
     * without blocking for the full interval duration.
     */
    private function sleepInterruptible(int $seconds): void
    {
        for ($i = 0; $i < $seconds; $i++) {
            if ($this->shouldStop) {
                break;
            }

            sleep(1);

            if (function_exists('pcntl_signal_dispatch')) {
                pcntl_signal_dispatch();
            }
        }
    }

    /**
     * Register POSIX signal handlers for graceful shutdown.
     * No-ops on platforms where pcntl is unavailable (e.g. Windows).
     */
    private function registerSignalHandlers(): void
    {
        if (! function_exists('pcntl_signal')) {
            return;
        }

        pcntl_async_signals(true);

        $stop = function (): void {
            $this->shouldStop = true;
            $this->info('Shutdown signal received — finishing current cycle before exiting…');
        };

        pcntl_signal(SIGTERM, $stop);
        pcntl_signal(SIGINT, $stop);
    }

    /**
     * Instantiate BellHistoricalTelemetryService from config, returning null
     * when the Bell API base URL has not been configured.
     */
    private function makeService(): ?BellHistoricalTelemetryService
    {
        $baseUrl = config('integrations.bell_historical.base_url', '');

        if (empty($baseUrl)) {
            $this->warn('BELL_HISTORICAL_BASE_URL is not configured — skipping.');

            return null;
        }

        return new BellHistoricalTelemetryService(
            baseUrl: $baseUrl,
            apiUsername: config('integrations.bell_historical.api_username', ''),
            apiPassword: config('integrations.bell_historical.api_password', ''),
            ssoTokenUrl: config('integrations.bell_sso.token_url', ''),
            clientId: config('integrations.bell_sso.client_id', ''),
            clientSecret: config('integrations.bell_sso.client_secret', ''),
            scope: config('integrations.bell_sso.scope', 'ISO_Exports'),
        );
    }
}
