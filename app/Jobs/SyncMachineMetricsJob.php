<?php

namespace App\Jobs;

use App\Models\Machine;
use App\Services\Integration\IntegrationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncMachineMetricsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected Machine $machine;

    public int $tries = 2;

    public int $timeout = 60;

    // Matches the sibling sync jobs (SyncIntegrationMachinesJob,
    // MachineLocationUpdateJob, MachineStatusMonitoringJob), which all
    // already had an explicit backoff -- this one didn't, so its 2 retries
    // fired back-to-back instead of giving a transient failure time to clear.
    public array $backoff = [30, 120]; // 30s, 2 mins

    /**
     * Create a new job instance.
     */
    public function __construct(Machine $machine)
    {
        $this->machine = $machine;
    }

    /**
     * Execute the job.
     */
    public function handle(IntegrationService $integrationService): void
    {
        Log::info('Starting metrics sync for machine', [
            'machine_id' => $this->machine->id,
            'machine_name' => $this->machine->name,
        ]);

        try {
            // Ensure model queries are scoped to the machine's team in queue context
            app()->instance('current_team_id', $this->machine->team_id);

            // Get the integration for this machine
            $integration = $this->machine->team->integrations()
                ->where('provider', $this->machine->manufacturer)
                ->first();

            if (! $integration || $integration->status !== 'connected') {
                Log::warning('Integration not available or not connected', [
                    'machine_id' => $this->machine->id,
                ]);

                return;
            }

            // Get service and fetch metrics
            $service = $integrationService->getServiceForIntegration($integration);
            if (! $service) {
                return;
            }

            // Machine has no 'external_id' column -- 'manufacturer_id' is the
            // real fillable field for "ID from manufacturer system".
            $metrics = $service->fetchMachineMetrics($this->machine->manufacturer_id);

            if (! empty($metrics)) {
                // team_id is NOT NULL on machine_metrics and isn't filled
                // automatically by the relationship.
                $this->machine->metrics()->create(array_merge($metrics, [
                    'team_id' => $this->machine->team_id,
                ]));

                Log::info('Machine metrics synced successfully', [
                    'machine_id' => $this->machine->id,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('Exception during metrics sync', [
                'machine_id' => $this->machine->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        } finally {
            if (app()->bound('current_team_id')) {
                app()->forgetInstance('current_team_id');
            }
        }
    }
}
