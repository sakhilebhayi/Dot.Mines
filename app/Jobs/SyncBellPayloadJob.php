<?php

namespace App\Jobs;

use App\Services\Integration\BellHistoricalTelemetryService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * SyncBellPayloadJob
 *
 * Scheduled every 15 minutes. Pulls CumulativePayloadTotals and CumulativeLoadCount
 * for all known Bell machines. Data feeds production intelligence, dispatch
 * optimisation, and revenue analytics.
 *
 * Runs on the `integrations` queue.
 */
class SyncBellPayloadJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    /** @var array<int, int> */
    public array $backoff = [30, 90];

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

        $payload = $service->syncSignal('CumulativePayloadTotals', hours: 0.5);
        $loads = $service->syncSignal('CumulativeLoadCount', hours: 0.5);

        Log::info('SyncBellPayloadJob completed', [
            'payload' => $payload,
            'loads' => $loads,
        ]);

        // Immediately bridge today's intraday load/payload data into production_records
        // so the Production page reflects current data without waiting for the nightly run.
        SyncBellProductionRecordsJob::dispatch()->onQueue('default');
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('SyncBellPayloadJob failed', ['error' => $exception->getMessage()]);
    }

    private function makeService(): ?BellHistoricalTelemetryService
    {
        $baseUrl = config('integrations.bell_historical.base_url', '');

        if (empty($baseUrl)) {
            Log::info('SyncBellPayloadJob: bell_historical.base_url not configured – skipping.');

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
