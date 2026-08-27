<?php

namespace App\Jobs;

use App\Events\MachineOffline;
use App\Models\Integration;
use App\Models\Machine;
use App\Services\Integration\IntegrationService;
use App\Support\ApiPayload;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
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
                ApiPayload::strings($machines->pluck('manufacturer_id')->all())
            );

            if (empty($statuses)) {
                Log::debug('No status data received from integration', [
                    'integration_id' => $this->integration->id,
                ]);

                // Still run the timeout sweep: hearing nothing from the
                // provider is exactly when silent machines accumulate.
                $this->checkForTimedOutMachines();

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
    /**
     * Determine machine status based on integration data.
     *
     * This job owns CONNECTIVITY only. Activity truth (active vs idle)
     * is written by the sync from engine state; the snapshot presence
     * signal that feeds this job says "connected", never "working" --
     * the /Fleet snapshot lists the whole fleet, parked or hauling, so
     * treating its 'active' as an activity claim would show every
     * machine as operating around the clock.
     *
     * @param  array<string, mixed>  $status
     */
    private function determineStatus(array $status, Machine $machine): string
    {
        // An explicit offline from the provider is always believed.
        if (isset($status['online']) && ! $status['online']) {
            return 'offline';
        }

        if (is_string($status['status'] ?? null) && strtolower((string) $status['status']) === 'offline') {
            return 'offline';
        }

        // Liveness: has ANYTHING been heard inside the provider's own
        // cadence window? Judged on position OR telemetry -- a stationary
        // machine keeps reporting counters under a frozen GPS timestamp.
        // The old rule here was a hard-coded 5 minutes against location
        // only, which is unsatisfiable on a 15-minute provider: it
        // declared the whole fleet offline between every sync and pinned
        // the Active/Idle/Maintenance cards at zero.
        $heardAt = $machine->lastHeardAt();

        if ($heardAt === null || $heardAt->diffInSeconds(now()) > $this->offlineAfterSeconds()) {
            return 'offline';
        }

        // Alive. A machine coming back from offline revives as idle --
        // never invented as active -- and the next sync restores the
        // real engine state.
        return $machine->status === 'offline' ? 'idle' : $machine->status;
    }

    /**
     * Nothing heard for twice the integration's declared cadence means
     * offline -- the same "2x its own interval" convention the guardian's
     * sync-freshness check uses. For Bell (900s) that is 30 minutes.
     */
    private function offlineAfterSeconds(): int
    {
        return 2 * $this->integration->syncIntervalSeconds();
    }

    /**
     * Check for machines that haven't reported in a while and mark them offline.
     */
    private function checkForTimedOutMachines(): void
    {
        try {
            // Machines we HAVE heard from before, from which nothing --
            // no position, no telemetry -- has arrived within the
            // provider's cadence window. Never-heard machines are left
            // alone; silence cannot time out what never spoke.
            $cutoff = now()->subSeconds($this->offlineAfterSeconds());

            $timedOutMachines = Machine::where('integration_id', $this->integration->id)
                ->where('status', '!=', 'offline')
                ->where(function (Builder $query): void {
                    $query->whereNotNull('last_location_update')
                        ->orWhereHas('metrics');
                })
                ->where(function (Builder $query) use ($cutoff): void {
                    $query->whereNull('last_location_update')
                        ->orWhere('last_location_update', '<', $cutoff);
                })
                ->whereDoesntHave('metrics', function (Builder $query) use ($cutoff): void {
                    $query->where('recorded_at', '>=', $cutoff);
                })
                ->get();

            foreach ($timedOutMachines as $machine) {
                $machine->update(['status' => 'offline']);

                event(new MachineOffline(
                    machine: $machine,
                    reason: 'No telemetry heard within the provider sync window',
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
