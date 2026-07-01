<?php

namespace App\Jobs;

use App\Models\BellEquipment;
use App\Models\Integration;
use App\Models\IntegrationSyncLog;
use App\Models\Machine;
use App\Services\Integration\AdapterRegistry;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * SyncIntegrationJob
 *
 * Fetches the fleet snapshot for one integration row, upserts machines
 * scoped to that team, and writes an IntegrationSyncLog entry.
 *
 * Dispatched by DispatchIntegrationSyncsJob every 5 minutes.
 */
class SyncIntegrationJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 180;

    /** @var array<int> */
    public array $backoff = [60, 300];

    public function __construct(private readonly int $integrationId)
    {
        $this->onQueue('integrations');
    }

    public function handle(AdapterRegistry $registry): void
    {
        $integration = Integration::find($this->integrationId);

        if ($integration === null) {
            Log::info("SyncIntegrationJob: integration {$this->integrationId} not found — skipping.");

            return;
        }

        if ($integration->status === 'disconnected') {
            return;
        }

        $log = IntegrationSyncLog::create([
            'integration_id' => $integration->id,
            'team_id' => $integration->team_id,
            'started_at' => now(),
            'status' => 'running',
            'machines_synced' => 0,
            'records_inserted' => 0,
        ]);

        try {
            $adapter = $registry->resolve($integration->provider);
            $credentials = $integration->credentials ?? [];

            $fleet = $adapter->fetchFleet($credentials);

            $synced = 0;
            foreach ($fleet as $machineData) {
                if ($this->upsertMachine($integration->team_id, $integration->id, $machineData)) {
                    $synced++;
                }
            }

            $integration->update([
                'last_sync_at' => now(),
                'last_sync_status' => 'success',
                'last_error' => null,
                'machines_count' => $synced,
                'status' => 'connected',
            ]);

            $log->update([
                'finished_at' => now(),
                'status' => 'success',
                'machines_synced' => $synced,
            ]);
        } catch (\Throwable $e) {
            Log::error("SyncIntegrationJob: integration {$this->integrationId} failed", [
                'error' => $e->getMessage(),
            ]);

            $integration->update([
                'last_sync_status' => 'failed',
                'last_error' => $e->getMessage(),
            ]);

            $log->update([
                'finished_at' => now(),
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Upsert a machine row scoped to the given team.
     * Never touches machines belonging to a different team.
     *
     * @param  array<string, mixed>  $data
     */
    private function upsertMachine(int $teamId, int $integrationId, array $data): bool
    {
        $externalId = trim($data['external_id'] ?? '');
        if ($externalId === '') {
            return false;
        }

        /** @var Machine|null $machine */
        $machine = Machine::where('team_id', $teamId)
            ->where('external_id', $externalId)
            ->first();

        $telemetryDate = ! empty($data['telemetry_date'])
            ? Carbon::parse($data['telemetry_date'])
            : null;

        $attributes = [
            'team_id' => $teamId,
            'integration_id' => $integrationId,
            'external_id' => $externalId,
            'name' => $data['name'] ?? $externalId,
            'machine_type' => $data['machine_type'] ?? 'other',
            'model' => $data['model'] ?? null,
            'manufacturer' => $data['manufacturer'] ?? null,
            'serial_number' => $data['serial_number'] ?? null,
            'last_location_latitude' => $data['latitude'] ?? null,
            'last_location_longitude' => $data['longitude'] ?? null,
            'last_location_update' => $telemetryDate ?? ($data['latitude'] ? now() : null),
            'operating_hours' => $data['operating_hours'] ?? null,
            'last_seen_at' => now(),
            'status' => ($data['engine_running'] ?? false) ? 'active' : 'idle',
        ];

        if ($machine === null) {
            $machine = Machine::create($attributes);
        } else {
            $machine->update($attributes);
        }

        // Link the machine to its BellEquipment row so that telemetry data
        // (fuel %, load count, DEF level, caution codes) is accessible on the
        // machine detail page and via BellTeamInsightsService.
        $bellKey = $data['_bell_equipment_key'] ?? null;
        if ($bellKey !== null) {
            BellEquipment::where('equipment_key', $bellKey)
                ->where(fn ($q) => $q->whereNull('machine_id')
                    ->orWhere('machine_id', $machine->id)
                )
                ->update([
                    'machine_id' => $machine->id,
                    'machine_matched_at' => now(),
                ]);
        }

        return true;
    }
}
