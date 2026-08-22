<?php

namespace App\Jobs;

use App\Events\MachineOffline;
use App\Models\Integration;
use App\Models\Machine;
use App\Services\Integration\IntegrationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class MachineStatusMonitoringJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected Integration $integration;

    public int $tries = 2;

    public int $timeout = 60;

    /** @var list<int> */
    public array $backoff = [30, 120]; // 30s, 2 mins

    /**
     * Create a new job instance.
     */
    public function __construct(Integration $integration)
    {
        $this->integration = $integration;
        $this->onQueue('status');
    }

    /**
     * Execute the job - monitors machine connectivity status
     * and broadcasts offline/online state changes in real-time.
     */
    /**
     * One queued instance per integration: these are idempotent
     * polling jobs, so a backlog of duplicates (996 piled up on
     * 2026-08-21 while named queues went undrained) adds only
     * redundant API calls. Duplicate dispatches are dropped while
     * one is already queued.
     *
     * @psalm-suppress PossiblyUnusedMethod -- invoked by Laravel's unique-job middleware
     */
    public function uniqueId(): string
    {
        return (string) $this->integration->id;
    }

    public function handle(IntegrationService $integrationService): void
    {
        Log::info('Starting machine status monitoring job', [
            'integration_id' => $this->integration->id,
            'provider' => $this->integration->provider,
        ]);

        try {
            // Ensure model queries are scoped to the integration's team in queue context
            app()->instance('current_team_id', $this->integration->team_id);

            // Verify integration is connected
            if ($this->integration->status !== 'connected') {
                Log::warning('Integration not connected, skipping status monitoring', [
                    'integration_id' => $this->integration->id,
                ]);

                return;
            }

            // Get all machines for this integration
            $machines = Machine::where('integration_id', $this->integration->id)
                ->get();

            if ($machines->isEmpty()) {
                Log::debug('No machines found for integration', [
                    'integration_id' => $this->integration->id,
                ]);

                return;
            }

            // Fetch machine statuses from the integration provider
            $statuses = $integrationService->getMachineStatuses(
                $this->integration,
                $machines->pluck('manufacturer_id')->toArray()
            );

            if (empty($statuses)) {
                Log::debug('No status data received from integration', [
                    'integration_id' => $this->integration->id,
                ]);

                return;
            }

            $statusChanges = 0;

            // Check each machine's status
            foreach ($statuses as $status) {
                $machine = $machines->firstWhere('manufacturer_id', $status['manufacturer_id'] ?? null);

                if (! $machine) {
                    continue;
                }

                // Determine current status
                $newStatus = $this->determineStatus($status, $machine);

                // Check for status change
                if ($machine->status !== $newStatus) {
                    $oldStatus = $machine->status;

                    // Update machine status
                    $machine->update(['status' => $newStatus]);

                    // If going offline, broadcast immediately
                    if ($newStatus === 'offline') {
                        event(new MachineOffline(
                            machine: $machine,
                            reason: 'No connectivity',
                            lastLocation: ($machine->last_location_latitude !== null && $machine->last_location_latitude !== 0.0) && ($machine->last_location_longitude !== null && $machine->last_location_longitude !== 0.0) ? [
                                'latitude' => $machine->last_location_latitude,
                                'longitude' => $machine->last_location_longitude,
                            ] : null
                        ));

                        Log::info('Machine went offline', [
                            'machine_id' => $machine->id,
                            'previous_status' => $oldStatus,
                        ]);
                    } elseif ($oldStatus === 'offline' && $newStatus !== 'offline') {
                        // Machine came back online
                        Log::info('Machine came back online', [
                            'machine_id' => $machine->id,
                            'new_status' => $newStatus,
                        ]);
                    }

                    $statusChanges++;
                }
            }

            // Check for machines that haven't reported in a while
            $this->checkForTimedOutMachines();

            Log::info('Machine status monitoring completed', [
                'integration_id' => $this->integration->id,
                'status_changes' => $statusChanges,
            ]);

        } catch (\Throwable $e) {
            Log::error('Machine status monitoring job failed', [
                'integration_id' => $this->integration->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        } finally {
            if (app()->bound('current_team_id')) {
                app()->forgetInstance('current_team_id');
            }
        }
    }

    /**
     * Determine machine status based on integration data.
     *
     * @param  array<string, mixed>  $status
     */
    private function determineStatus(array $status, Machine $machine): string
    {
        // Check if integration reports the machine as offline/disconnected
        if (isset($status['online']) && ! $status['online']) {
            return 'offline';
        }

        if (isset($status['status']) && strtolower($status['status']) === 'offline') {
            return 'offline';
        }

        // Check if location update is too old (haven't heard from machine in 5+ minutes)
        if ($machine->last_location_update) {
            $minutesSinceUpdate = $machine->last_location_update->diffInMinutes(now());

            if ($minutesSinceUpdate > 5) {
                return 'offline';
            }
        }

        // Use status from integration if provided
        if (isset($status['status'])) {
            $statusMap = [
                'active' => 'active',
                'idle' => 'idle',
                'maintenance' => 'maintenance',
                'offline' => 'offline',
                'standby' => 'idle',
                'stopped' => 'idle',
            ];

            $normalizedStatus = $statusMap[strtolower($status['status'])] ?? 'active';

            // Only mark as offline if specifically indicated
            if ($normalizedStatus !== 'offline') {
                return $normalizedStatus;
            }
        }

        // Default: keep previous status if no definitive status provided
        // But mark as offline if location is stale
        if ($machine->last_location_update) {
            $minutesSinceUpdate = $machine->last_location_update->diffInMinutes(now());
            if ($minutesSinceUpdate > 5) {
                return 'offline';
            }
        }

        return $machine->status ?? 'active';
    }

    /**
     * Check for machines that haven't reported in a while and mark them offline.
     */
    private function checkForTimedOutMachines(): void
    {
        try {
            // Find machines that haven't updated location in more than 5 minutes
            $timedOutMachines = Machine::where('integration_id', $this->integration->id)
                ->where('status', '!=', 'offline')
                ->whereNotNull('last_location_update')
                ->where('last_location_update', '<', now()->subMinutes(5))
                ->get();

            foreach ($timedOutMachines as $machine) {
                $machine->update(['status' => 'offline']);

                event(new MachineOffline(
                    machine: $machine,
                    reason: 'No location update for 5+ minutes',
                    lastLocation: ($machine->last_location_latitude !== null && $machine->last_location_latitude !== 0.0) && ($machine->last_location_longitude !== null && $machine->last_location_longitude !== 0.0) ? [
                        'latitude' => $machine->last_location_latitude,
                        'longitude' => $machine->last_location_longitude,
                    ] : null,
                ));

                Log::info('Machine marked offline due to timeout', [
                    'machine_id' => $machine->id,
                    'last_location_update' => $machine->last_location_update,
                ]);
            }

        } catch (\Throwable $e) {
            Log::error('Error checking for timed out machines', [
                'integration_id' => $this->integration->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Machine status monitoring job permanently failed', [
            'integration_id' => $this->integration->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
