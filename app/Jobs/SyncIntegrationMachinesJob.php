<?php

namespace App\Jobs;

use App\Models\Integration;
use App\Services\Integration\IntegrationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncIntegrationMachinesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected Integration $integration;

    public int $tries = 3;

    public int $timeout = 300;

    public array $backoff = [60, 300, 900]; // 1 min, 5 mins, 15 mins

    /**
     * Create a new job instance.
     */
    public function __construct(Integration $integration)
    {
        $this->integration = $integration;
    }

    /**
     * Execute the job.
     */
    public function handle(IntegrationService $integrationService): void
    {
        Log::info('Starting machine sync for integration', [
            'integration_id' => $this->integration->id,
            'provider' => $this->integration->provider,
        ]);

        try {
            // Check if integration is connected
            if ($this->integration->status !== 'connected') {
                Log::warning('Integration not connected, skipping sync', [
                    'integration_id' => $this->integration->id,
                ]);

                return;
            }

            // Perform sync
            $result = $integrationService->syncMachines($this->integration);

            // Update integration with sync status
            $this->integration->update([
                'last_sync_at' => now(),
                'last_sync_status' => $result['success'] ? 'success' : 'failed',
            ]);

            if ($result['success']) {
                // Refresh last_synced_at (and record count) for every
                // stream this integration already established during
                // connect() -- never invent a stream that isn't already in
                // capabilities, a scheduled run only refreshes what
                // connect() already confirmed was real.
                $streams = $this->integration->sync_streams ?? [];
                $now = now()->toIso8601String();
                $count = $result['count'] ?? 0;

                foreach ($this->integration->capabilities ?? [] as $capability) {
                    $streams[$capability] = [
                        'status' => 'active',
                        'last_synced_at' => $now,
                        'records' => $count,
                    ];
                }

                $this->integration->update(['sync_streams' => $streams]);

                Log::info('Machine sync completed successfully', [
                    'integration_id' => $this->integration->id,
                    'machines_synced' => $count,
                ]);
            } else {
                Log::error('Machine sync failed', [
                    'integration_id' => $this->integration->id,
                    'error' => $result['error'] ?? 'Unknown error',
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('Exception during machine sync', [
                'integration_id' => $this->integration->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Re-throw to trigger retry
            throw $e;
        }
    }

    /**
     * Handle job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Machine sync job failed after retries', [
            'integration_id' => $this->integration->id,
            'error' => $exception->getMessage(),
        ]);

        // Update integration status
        $this->integration->update([
            'status' => 'error',
            'last_sync_status' => 'failed',
        ]);
    }
}
