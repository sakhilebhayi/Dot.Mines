<?php

namespace App\Jobs;

use App\Services\Integration\BellHistoricalTelemetryService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * SyncBellLocationsJob
 *
 * Scheduled every 5 minutes. Pulls the Bell Locations endpoint for all known
 * machines covering the last 10-minute window, storing results in
 * bell_equipment_location_history and firing BellLocationUpdated events.
 *
 * Runs on the `integrations` queue.
 */
class SyncBellLocationsJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    /** @var array<int, int> */
    public array $backoff = [30, 60];

    public int $timeout = 120;

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

        $result = $service->syncSignal('Locations', hours: 0.25); // 15-min lookback for 5-min job

        Log::info('SyncBellLocationsJob completed', $result);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('SyncBellLocationsJob failed', ['error' => $exception->getMessage()]);
    }

    private function makeService(): ?BellHistoricalTelemetryService
    {
        $baseUrl = config('integrations.bell_historical.base_url', '');

        if (empty($baseUrl)) {
            Log::info('SyncBellLocationsJob: bell_historical.base_url not configured – skipping.');

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
