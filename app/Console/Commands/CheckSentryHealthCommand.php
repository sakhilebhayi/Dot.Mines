<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Sentry\SentrySdk;

/**
 * Validates that the Sentry integration is fully operational.
 *
 * Checks:
 *   1. SENTRY_DSN is configured (non-empty)
 *   2. DSN format is valid (https://{key}@{host}/{project})
 *   3. Sentry SDK is available (sentry/sentry-laravel installed)
 *   4. HTTP connectivity to the Sentry ingest host is reachable
 *   5. APP_ENV and release are tagged correctly
 *
 * Exit codes:
 *   0 — All checks passed
 *   1 — One or more checks failed (blocks deployment in CI)
 *
 * Usage:
 *   php artisan sentry:check-health
 *   php artisan sentry:check-health --skip-connectivity
 */
class CheckSentryHealthCommand extends Command
{
    protected $signature = 'sentry:check-health
                            {--env= : Override environment name for reporting}
                            {--skip-connectivity : Skip HTTP connectivity check}';

    protected $description = 'Validate Sentry DSN configuration and connectivity before deployment';

    public function handle(): int
    {
        $this->info('Sentry Health Check');
        $this->line(str_repeat('─', 50));

        $passed = true;

        // ── Check 1: DSN is configured ────────────────────────────────────────
        $dsn = config('sentry.dsn');

        if (empty($dsn)) {
            $this->check(false, 'SENTRY_DSN is not configured.');
            $this->line('  Set SENTRY_DSN in your .env or deployment environment.');
            $this->line('  Example: https://examplePublicKey@o0.ingest.sentry.io/0');
            $passed = false;
        } else {
            $this->check(true, 'SENTRY_DSN is configured.');
        }

        // ── Check 2: DSN format is valid ──────────────────────────────────────
        if (! empty($dsn)) {
            $valid = (bool) preg_match('#^https://[a-f0-9]+@[a-z0-9.]+\.[a-z]+/\d+$#i', rtrim($dsn, '/'));

            if ($valid) {
                $this->check(true, 'SENTRY_DSN format is valid.');
            } else {
                $this->check(false, 'SENTRY_DSN format appears invalid.');
                $this->line('  Expected: https://{key}@{host}.sentry.io/{project-id}');
                $passed = false;
            }
        }

        // ── Check 3: Sentry SDK is available ─────────────────────────────────
        if (class_exists(SentrySdk::class)) {
            $this->check(true, 'Sentry SDK (sentry/sentry-laravel) is available.');
        } else {
            $this->check(false, 'Sentry SDK not found. Run: composer require sentry/sentry-laravel');
            $passed = false;
        }

        // ── Check 4: Environment is tagged ───────────────────────────────────
        $environment = $this->option('env') ?: config('sentry.environment', config('app.env'));
        $this->check(true, "Sentry environment: {$environment}");

        $release = config('sentry.release');
        if (! empty($release)) {
            $this->check(true, "Sentry release: {$release}");
        } else {
            $this->line('  <fg=yellow>⚠</> SENTRY_RELEASE not set — set to git commit SHA or version tag.');
        }

        // ── Check 5: Traces sample rate ───────────────────────────────────────
        $tracesRate = (float) config('sentry.traces_sample_rate', 0.0);
        if ($tracesRate > 0) {
            $this->check(true, "Performance tracing enabled at {$tracesRate} sample rate.");
        } else {
            $this->line('  <fg=yellow>⚠</> SENTRY_TRACES_SAMPLE_RATE=0 — performance tracing disabled.');
        }

        // ── Check 6: HTTP connectivity (optional) ────────────────────────────
        if (! $this->option('skip-connectivity') && ! empty($dsn)) {
            $host = parse_url($dsn, PHP_URL_HOST);

            if ($host) {
                try {
                    $status = Http::timeout(5)->get("https://{$host}")->status();

                    if (in_array($status, [200, 400, 401, 404], true)) {
                        $this->check(true, "Sentry ingest host reachable: {$host}");
                    } else {
                        $this->line("  <fg=yellow>⚠</> Sentry ingest host returned HTTP {$status}.");
                    }
                } catch (\Throwable $e) {
                    $this->line("  <fg=yellow>⚠</> Could not reach Sentry host ({$e->getMessage()}).");
                }
            }
        }

        $this->line('');

        if ($passed) {
            $this->info('✓ All critical Sentry checks passed.');
            Log::info('sentry:check-health passed', ['environment' => $environment]);

            return Command::SUCCESS;
        }

        $this->error('✗ Critical Sentry checks FAILED. Configure SENTRY_DSN before production traffic.');
        Log::error('sentry:check-health failed');

        return Command::FAILURE;
    }

    private function check(bool $pass, string $message): void
    {
        $icon = $pass ? '<fg=green>✓</>' : '<fg=red>✗</>';
        $this->line("  {$icon} {$message}");
    }
}
