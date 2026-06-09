<?php

namespace App\Jobs;

use App\Services\Integration\BellHistoricalTelemetryService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * SyncBellFuelJob
 *
 * Scheduled every hour. Pulls CumulativeFuelUsed and FuelUsedInThePreceding24Hours
 * for all known Bell machines, storing results in bell_equipment_fuel_usage_history
 * and bell_fuel_levels. Fires BellFuelLowDetected when fuel falls below 20%.
 *
 * Runs on the `integrations` queue.
 */
class SyncBellFuelJob implements ShouldBeUnique, ShouldQueue
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

        $fuel = $service->syncSignal('CumulativeFuelUsed', hours: 1);
        $fuel24h = $service->syncSignal('FuelUsedInThePreceding24Hours', hours: 1);
        $fuelLevel = $service->syncSignal('FuelRemainingRatio', hours: 1);

        Log::info('SyncBellFuelJob completed', [
            'fuel' => $fuel,
            'fuel_24h' => $fuel24h,
            'fuel_level' => $fuelLevel,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('SyncBellFuelJob failed', ['error' => $exception->getMessage()]);
    }

    private function makeService(): ?BellHistoricalTelemetryService
    {
        $baseUrl = config('integrations.bell_historical.base_url', '');

        if (empty($baseUrl)) {
            Log::info('SyncBellFuelJob: bell_historical.base_url not configured – skipping.');

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
