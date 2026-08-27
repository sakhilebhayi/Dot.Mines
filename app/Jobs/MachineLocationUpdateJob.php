<?php

namespace App\Jobs;

use App\Events\MachineLocationUpdated;
use App\Models\Integration;
use App\Models\Machine;
use App\Services\Integration\IntegrationService;
use App\Support\ApiPayload;
use App\Support\Geo;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class MachineLocationUpdateJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected Integration $integration;

    public int $tries = 3;

    public int $timeout = 120;

    /** @var list<int> */
    public array $backoff = [30, 90, 300]; // 30s, 90s, 5 mins

    /**
     * Create a new job instance.
     */
    public function __construct(Integration $integration)
    {
        $this->integration = $integration;
        // Queue on the high-priority queue for real-time responsiveness
        $this->onQueue('locations');
    }

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
        Log::info('Starting location update job', [
            'integration_id' => $this->integration->id,
            'provider' => $this->integration->provider,
        ]);

        try {
            // Ensure model queries are scoped to the integration's team in queue context
            app()->instance('current_team_id', $this->integration->team_id);

            // Verify integration is connected
            if ($this->integration->status !== 'connected') {
                Log::warning('Integration not connected, skipping location update', [
                    'integration_id' => $this->integration->id,
                ]);

                return;
            }

            // Get all active machines for this integration
            $machines = Machine::where('integration_id', $this->integration->id)
                ->where('status', '!=', 'offline')
                ->get();

            if ($machines->isEmpty()) {
                Log::info('No active machines found for integration', [
                    'integration_id' => $this->integration->id,
                ]);

                return;
            }

            // Fetch locations from the integration provider
            $locations = $integrationService->getMachineLocations(
                $this->integration,
                ApiPayload::strings($machines->pluck('manufacturer_id')->all())
            );

            if (empty($locations)) {
                Log::debug('No location data received from integration', [
                    'integration_id' => $this->integration->id,
                ]);

                return;
            }

            // Process each location update
            $broadcastCount = 0;
            foreach ($locations as $location) {
                $machine = $machines->firstWhere('manufacturer_id', $location['manufacturer_id'] ?? null);

                if (! $machine) {
                    continue;
                }

                // Check if location has actually changed
                $hasChanged = $this->hasLocationChanged($machine, $location);

                if (! $hasChanged) {
                    continue;
                }

                // Update machine location. The timestamp is the PROVIDER's
                // reading time (carried in the batched snapshot payload),
                // never the job's run time -- same honesty rule as
                // IntegrationService::syncMachine(): a machine that stopped
                // reporting must visibly age, not look perpetually fresh.
                // No status write here: the locations feed carries no
                // status key, so the old `?? 'active'` default fired on
                // every update and painted the whole parked fleet active
                // every 20 seconds. Status is owned by the sync (engine
                // truth) and the monitoring job (connectivity) -- this
                // job owns coordinates.
                $machine->update([
                    'last_location_latitude' => $location['latitude'] ?? null,
                    'last_location_longitude' => $location['longitude'] ?? null,
                    'last_location_update' => isset($location['timestamp'])
                        ? Carbon::parse((string) $location['timestamp'])
                        : now(),
                ]);

                // Broadcast the update in real-time
                event(new MachineLocationUpdated(
                    machine: $machine,
                    location: [
                        'latitude' => $location['latitude'] ?? null,
                        'longitude' => $location['longitude'] ?? null,
                        'accuracy' => $location['accuracy'] ?? null,
                        'heading' => $location['heading'] ?? null,
                        'speed' => $location['speed'] ?? null,
                        'altitude' => $location['altitude'] ?? null,
                        'source' => 'integration',
                    ]
                ));

                $broadcastCount++;

                Log::debug('Broadcasted machine location update', [
                    'machine_id' => $machine->id,
                    'latitude' => $location['latitude'],
                    'longitude' => $location['longitude'],
                ]);
            }

            // Dispatch speed monitoring job after location updates
            RouteSpeedMonitoringJob::dispatch();

            // A fully successful run clears any earlier failure message --
            // otherwise last_error from a transient outage sticks forever
            // next to status=connected (observed live: a stale "Location
            // update failed" from days earlier shadowing a healthy sync)
            // and the Integration Manager keeps telling the admin the API
            // is broken while data flows normally.
            if ($this->integration->last_error !== null) {
                $this->integration->update([
                    'last_error' => null,
                    'last_error_at' => null,
                ]);
            }

            Log::info('Location update job completed', [
                'integration_id' => $this->integration->id,
                'machines_updated' => $broadcastCount,
                'total_locations' => count($locations),
            ]);

        } catch (\Throwable $e) {
            Log::error('Location update job failed', [
                'integration_id' => $this->integration->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Rethrow to trigger retry mechanism
            throw $e;
        } finally {
            // Clear the injected team context to avoid leakage into other jobs
            if (app()->bound('current_team_id')) {
                app()->forgetInstance('current_team_id');
            }
        }
    }

    /**
     * Determine if location has meaningfully changed.
     * Prevents unnecessary broadcasts if machine hasn't moved significantly.
     *
     * @param  array<string, mixed>  $newLocation
     */
    private function hasLocationChanged(Machine $machine, array $newLocation): bool
    {
        // Always update if no previous location
        if (($machine->last_location_latitude === null || $machine->last_location_latitude === 0.0) || ($machine->last_location_longitude === null || $machine->last_location_longitude === 0.0)) {
            return true;
        }

        // Calculate distance using Haversine formula
        $distance = Geo::distanceKm(
            $machine->last_location_latitude,
            $machine->last_location_longitude,
            (float) (is_numeric($newLocation['latitude'] ?? null) ? $newLocation['latitude'] : 0),
            (float) (is_numeric($newLocation['longitude'] ?? null) ? $newLocation['longitude'] : 0)
        );

        // Only broadcast if moved more than 5 meters
        $significantDistance = $distance > 0.005; // ~5 meters

        // Also check if it's been more than 5 minutes since last update
        $lastUpdate = $machine->last_location_update;
        $significantTime = $lastUpdate === null || $lastUpdate->diffInMinutes(now()) >= 5;

        return $significantDistance || $significantTime;
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Location update job permanently failed', [
            'integration_id' => $this->integration->id,
            'error' => $exception->getMessage(),
        ]);

        // Mark integration as having issues. last_error is exposed directly
        // via Api\IntegrationController::show() and the Integration Manager
        // UI -- it used to store the raw exception message verbatim, which
        // can include third-party API response bodies or internal details.
        // The real message is already logged above for us.
        $this->integration->update([
            'last_error' => 'Location update failed. Check the integration credentials and try syncing again.',
            'last_error_at' => now(),
        ]);
    }
}
