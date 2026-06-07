<?php

namespace App\Jobs;

use App\Services\Integration\BellHistoricalTelemetryService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * SyncBellHistoricalDataJob
 *
 * Scheduled every hour via routes/console.php.
 * Calls all Bell Equipment historical REST API endpoints for each known machine:
 *   GET /Fleet/Equipment/{id}/{Signal}/{startDateUTC}/{endDateUTC}
 *
 * Signals: Locations, CumulativeOperatingHours, CumulativeFuelUsed,
 *   FuelUsedInThePreceding24Hours, CumulativeIdleHours, FuelRemainingRatio,
 *   CumulativeLoadCount, CumulativePayloadTotals, CautionCodes, DEFRemaining,
 *   EngineCondition, CumulativeActiveRegenerationHours, Distance.
 *
 * Authenticates via Bell SSO (OAuth2 Password Credentials / Basic Auth header).
 * Complements SyncBellFleetDataJob (ISO15143-3 snapshot, every 15 min).
 */
class SyncBellHistoricalDataJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct()
    {
        $this->onQueue('integrations');
    }

    public function handle(): void
    {
        $baseUrl = config('integrations.bell_historical.base_url', '');
        $username = config('integrations.bell_historical.api_username', '');
        $password = config('integrations.bell_historical.api_password', '');
        $ssoTokenUrl = config('integrations.bell_sso.token_url', '');
        $clientId = config('integrations.bell_sso.client_id', '');
        $clientSecret = config('integrations.bell_sso.client_secret', '');
        $scope = config('integrations.bell_sso.scope', 'ISO_Exports');

        if (empty($baseUrl)) {
            Log::info('SyncBellHistoricalDataJob: bell_historical.base_url not configured – skipping.');

            return;
        }

        $service = new BellHistoricalTelemetryService(
            $baseUrl,
            $username,
            $password,
            $ssoTokenUrl,
            $clientId,
            $clientSecret,
            $scope,
        );

        $result = $service->syncHistoricalData(hours: 1);

        Log::info('SyncBellHistoricalDataJob completed', [
            'fetched' => $result['fetched'],
            'inserted' => $result['inserted'],
            'skipped' => $result['skipped'],
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('SyncBellHistoricalDataJob permanently failed', [
            'error' => $exception->getMessage(),
        ]);
    }
}
