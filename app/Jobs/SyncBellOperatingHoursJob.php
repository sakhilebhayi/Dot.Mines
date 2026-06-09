<?php

namespace App\Jobs;

use App\Services\Integration\BellHistoricalTelemetryService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * SyncBellOperatingHoursJob
 *
 * Scheduled every hour. Pulls CumulativeOperatingHours and CumulativeIdleHours
 * for all known Bell machines. Data feeds maintenance scheduling, machine
 * utilisation KPIs, and the production intelligence layer.
 *
 * Runs on the `integrations` queue.
 */
class SyncBellOperatingHoursJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    /** @var array<int, int> */
    public array $backoff = [60, 180];

    public int $timeout = 180;

    public function __construct()
    {
        $this->onQueue('integrations');
    }

    public function handle(): void
    {
        $service = $this->makeService();

        if ($service === null) {
            return;
        }

        $hours = $service->syncSignal('CumulativeOperatingHours', hours: 1);
        $idle = $service->syncSignal('CumulativeIdleHours', hours: 1);
        $regen = $service->syncSignal('CumulativeActiveRegenerationHours', hours: 1);
        $distance = $service->syncSignal('Distance', hours: 1);

        Log::info('SyncBellOperatingHoursJob completed', [
            'operating_hours' => $hours,
            'idle_hours' => $idle,
            'regen_hours' => $regen,
            'distance' => $distance,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('SyncBellOperatingHoursJob failed', ['error' => $exception->getMessage()]);
    }

    private function makeService(): ?BellHistoricalTelemetryService
    {
        $baseUrl = config('integrations.bell_historical.base_url', '');

        if (empty($baseUrl)) {
            Log::info('SyncBellOperatingHoursJob: bell_historical.base_url not configured – skipping.');

            return null;
        }

        return new BellHistoricalTelemetryService(
            $baseUrl,
            config('integrations.bell_historical.api_username', ''),
            config('integrations.bell_historical.api_password', ''),
            config('integrations.bell_sso.token_url', ''),
            config('integrations.bell_sso.client_id', ''),
            config('integrations.bell_sso.client_secret', ''),
            config('integrations.bell_sso.scope', 'ISO_Exports'),
        );
    }
}
