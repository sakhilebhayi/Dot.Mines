<?php

namespace App\Jobs;

use App\Models\Integration;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * DispatchIntegrationSyncsJob
 *
 * Runs every 5 minutes (scheduled in routes/console.php).
 * Iterates all connected integrations and dispatches SyncIntegrationJob
 * for each one that is due for a sync based on its configured frequency.
 */
class DispatchIntegrationSyncsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 60;

    public function __construct()
    {
        $this->onQueue('integrations');
    }

    public function handle(): void
    {
        $integrations = Integration::where('status', 'connected')->get();

        foreach ($integrations as $integration) {
            if (! $this->isDue($integration)) {
                continue;
            }

            Log::info("DispatchIntegrationSyncsJob: dispatching sync for integration {$integration->id} ({$integration->provider})");
            SyncIntegrationJob::dispatch($integration->id);
        }
    }

    /**
     * Check whether this integration's next sync time has been reached.
     */
    private function isDue(Integration $integration): bool
    {
        $lastSync = $integration->last_sync_at;

        if ($lastSync === null) {
            return true; // Never synced — run immediately
        }

        $config = $integration->config ?? [];
        $frequency = $config['sync_frequency'] ?? 'every_5_minutes';

        $nextSync = match ($frequency) {
            'manual' => Carbon::now()->addCenturies(1), // never auto-run
            'every_5_minutes' => $lastSync->addMinutes(5),
            'every_15_minutes' => $lastSync->addMinutes(15),
            'hourly' => $lastSync->addHour(),
            'every_6_hours' => $lastSync->addHours(6),
            'daily' => $lastSync->addDay(),
            default => $lastSync->addMinutes(5),
        };

        return now()->greaterThanOrEqualTo($nextSync);
    }
}
