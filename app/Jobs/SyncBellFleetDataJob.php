<?php

namespace App\Jobs;

use App\Services\Integration\BellIso15143Service;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * SyncBellFleetDataJob
 *
 * Scheduled every 15 minutes via routes/console.php.
 * Instantiates BellIso15143Service from config and triggers the full sync cycle.
 */
class SyncBellFleetDataJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct()
    {
        $this->onQueue('integrations');
    }

    /**
     * Execute the Bell ISO15143-3 fleet sync.
     */
    public function handle(): void
    {
        $apiUrl = config('integrations.bell_iso15143.api_url', '');
        $apiUsername = config('integrations.bell_iso15143.api_username', '');
        $apiPassword = config('integrations.bell_iso15143.api_password', '');
        $ssoTokenUrl = config('integrations.bell_sso.token_url', '');
        $clientId = config('integrations.bell_sso.client_id', '');
        $clientSecret = config('integrations.bell_sso.client_secret', '');
        $scope = config('integrations.bell_sso.scope', 'ISO_Exports');

        if (empty($apiUrl)) {
            Log::warning('SyncBellFleetDataJob: bell_iso15143.api_url is not configured – skipping.');

            return;
        }

        $service = new BellIso15143Service(
            $apiUrl,
            $apiUsername,
            $apiPassword,
            $ssoTokenUrl,
            $clientId,
            $clientSecret,
            $scope,
        );

        $result = $service->sync();

        if ($result['success']) {
            Log::info('SyncBellFleetDataJob completed', [
                'processed' => $result['processed'],
                'inserted' => $result['inserted'],
                'updated' => $result['updated'],
            ]);
        } else {
            Log::error('SyncBellFleetDataJob failed', [
                'error' => $result['error'] ?? 'unknown',
            ]);
        }
    }
}
