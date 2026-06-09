<?php

namespace App\Jobs;

use App\Services\Integration\BellHistoricalTelemetryService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * SyncBellEngineConditionJob
 *
 * Scheduled every 5 minutes. Pulls EngineCondition, DEFRemaining, and
 * CautionCodes for all known Bell machines. High-frequency polling to catch
 * engine faults and caution code changes quickly. Fires BellEngineWarningDetected
 * when conditions are non-normal.
 *
 * Runs on the `integrations` queue.
 */
class SyncBellEngineConditionJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    /** @var array<int, int> */
    public array $backoff = [15, 30];

    public int $timeout = 90;

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

        $engine = $service->syncSignal('EngineCondition', hours: 0.25);
        $def = $service->syncSignal('DEFRemaining', hours: 0.25);
        $caution = $service->syncSignal('CautionCodes', hours: 0.25);

        Log::info('SyncBellEngineConditionJob completed', [
            'engine' => $engine,
            'def' => $def,
            'caution' => $caution,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('SyncBellEngineConditionJob failed', ['error' => $exception->getMessage()]);
    }

    private function makeService(): ?BellHistoricalTelemetryService
    {
        $baseUrl = config('integrations.bell_historical.base_url', '');

        if (empty($baseUrl)) {
            Log::info('SyncBellEngineConditionJob: bell_historical.base_url not configured – skipping.');

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
