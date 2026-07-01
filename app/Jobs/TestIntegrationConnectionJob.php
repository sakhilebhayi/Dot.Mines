<?php

namespace App\Jobs;

use App\Models\Integration;
use App\Services\Integration\AdapterRegistry;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * TestIntegrationConnectionJob
 *
 * Dispatched immediately after a user adds or edits an integration.
 * Calls the adapter's testConnection() and updates the integration status.
 * The result is broadcast back to the Livewire component via a session flash
 * (Livewire polls for status change via polling or a dedicated refresh event).
 */
class TestIntegrationConnectionJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 60;

    public function __construct(private readonly int $integrationId)
    {
        $this->onQueue('integrations');
    }

    public function handle(AdapterRegistry $registry): void
    {
        $integration = Integration::find($this->integrationId);

        if ($integration === null) {
            return;
        }

        $integration->update(['status' => 'testing']);

        try {
            $adapter = $registry->resolve($integration->provider);
            $result = $adapter->testConnection($integration->credentials ?? []);

            $integration->update([
                'status' => $result['success'] ? 'connected' : 'disconnected',
                'machines_count' => $result['machines_found'] ?? $integration->machines_count,
                'last_error' => $result['success'] ? null : ($result['message'] ?? 'Test failed'),
                'last_sync_status' => $result['success'] ? 'success' : 'failed',
            ]);

            Log::info("TestIntegrationConnectionJob: integration {$this->integrationId} test ".($result['success'] ? 'passed' : 'failed'));
        } catch (\Throwable $e) {
            $integration->update([
                'status' => 'disconnected',
                'last_error' => $e->getMessage(),
            ]);

            Log::error("TestIntegrationConnectionJob: integration {$this->integrationId} exception", [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
