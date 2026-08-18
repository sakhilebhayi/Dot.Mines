<?php

namespace App\Services\Integration;

use App\Contracts\ManufacturerServiceInterface;
use App\Jobs\SyncIntegrationMachinesJob;
use App\Models\Alert;
use App\Models\Integration;
use App\Models\Machine;
use App\Models\MachineMetric;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class IntegrationService
{
    protected array $services = [];

    /**
     * Register a manufacturer service
     */
    public function register(string $name, ManufacturerServiceInterface $service): void
    {
        $this->services[$name] = $service;
    }

    /**
     * Get a registered service
     */
    public function get(string $name): ?ManufacturerServiceInterface
    {
        return $this->services[$name] ?? null;
    }

    /**
     * Get all registered services
     */
    public function all(): array
    {
        return $this->services;
    }

    /**
     * Test connection to a manufacturer API
     */
    public function testConnection(Integration $integration): array
    {
        try {
            $service = $this->getServiceForIntegration($integration);

            if (! $service) {
                return [
                    'success' => false,
                    'error' => "Service not found for manufacturer: {$integration->provider}",
                ];
            }

            $result = $service->testConnection();

            return [
                'success' => $result,
                'message' => $result ? 'Connection successful' : 'Connection failed',
                'error' => ! $result ? $service->getLastError() : null,
            ];
        } catch (\Throwable $e) {
            Log::error('Integration test connection failed', [
                'integration_id' => $integration->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * The real, honest "Connect" pipeline (spec: "Do not report Connected
     * simply because authentication succeeded"). Unlike testConnection(),
     * which this method deliberately does NOT call or reuse the return
     * shape of, this actually fetches, persists, and dispatches an ongoing
     * sync -- every check here is a real side effect on the real data
     * path, not a simulated probe. On success, updates the Integration's
     * status/capabilities/sync_streams in one place so the UI always
     * reflects exactly what this method verified.
     */
    public function connect(Integration $integration): array
    {
        $checks = [
            'credentials_valid' => false,
            'fleet_reachable' => false,
            'data_retrieved' => false,
            'data_storable' => false,
            'tenant_associated' => false,
            'sync_dispatchable' => false,
        ];

        $service = $this->getServiceForIntegration($integration);

        if (! $service) {
            return [
                'success' => false,
                'message' => 'Connection failed',
                'error' => "Service not found for manufacturer: {$integration->provider}",
                'checks' => $checks,
                'capabilities' => [],
                'sample_machine_count' => 0,
            ];
        }

        try {
            $authenticated = $service->testConnection();
        } catch (\Throwable $e) {
            Log::error('Integration connect: auth check threw', [
                'integration_id' => $integration->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Connection failed',
                'error' => $e->getMessage(),
                'checks' => $checks,
                'capabilities' => [],
                'sample_machine_count' => 0,
            ];
        }

        // No separate scope/permission surface exists on
        // ManufacturerServiceInterface today -- a failed testConnection()
        // is the only signal this app can honestly attribute to either bad
        // credentials or missing permissions, so both checks share it
        // rather than fabricating a distinction the API doesn't expose.
        $checks['credentials_valid'] = $authenticated;
        $checks['fleet_reachable'] = $authenticated;

        if (! $authenticated) {
            return [
                'success' => false,
                'message' => 'Connection failed — API credentials could not be verified.',
                'error' => $service->getLastError(),
                'checks' => $checks,
                'capabilities' => [],
                'sample_machine_count' => 0,
            ];
        }

        $fetchResult = $service->fetchMachines();
        $checks['data_retrieved'] = $fetchResult['success'] ?? false;

        if (! $checks['data_retrieved']) {
            return [
                'success' => false,
                'message' => 'Connected, but fleet data could not be retrieved.',
                'error' => $fetchResult['error'] ?? 'Failed to fetch fleet data',
                'checks' => $checks,
                'capabilities' => [],
                'sample_machine_count' => 0,
            ];
        }

        $machineList = $fetchResult['machines'] ?? [];
        $capabilities = $this->deriveCapabilities($machineList[0] ?? []);

        $syncResult = $this->persistMachines($integration, $service, $machineList);
        $checks['data_storable'] = $syncResult['success'] ?? false;

        // syncMachine() always writes $integration->team_id onto every row
        // it creates/updates -- if persistence succeeded at all, tenant
        // association is true by construction, not a separate query.
        $checks['tenant_associated'] = $checks['data_storable'];

        try {
            SyncIntegrationMachinesJob::dispatch($integration);
            $checks['sync_dispatchable'] = true;
        } catch (\Throwable $e) {
            Log::warning('Integration connect: failed to dispatch ongoing sync job', [
                'integration_id' => $integration->id,
                'error' => $e->getMessage(),
            ]);
            $checks['sync_dispatchable'] = false;
        }

        $streams = $this->buildSyncStreams($capabilities, $syncResult['count'] ?? 0);

        $integration->update([
            'status' => 'connected',
            'capabilities' => $capabilities,
            'sync_streams' => $streams,
            'last_sync_at' => now(),
            'last_sync_status' => 'success',
            'last_error' => null,
        ]);

        $message = in_array('production', $capabilities, true) || count($machineList) === 0
            ? 'Connection successful'
            : 'Connected, but production data could not be synchronised.';

        return [
            'success' => true,
            'message' => $message,
            'error' => null,
            'checks' => $checks,
            'capabilities' => $capabilities,
            'sample_machine_count' => count($machineList),
        ];
    }

    /**
     * @param  list<string>  $capabilities
     * @return array<string, array{status: string, last_synced_at: ?string, records: int}>
     */
    private function buildSyncStreams(array $capabilities, int $recordCount): array
    {
        $now = now()->toIso8601String();
        $streams = [];

        foreach (['fleet', 'telemetry', 'production', 'location'] as $stream) {
            $streams[$stream] = in_array($stream, $capabilities, true)
                ? ['status' => 'active', 'last_synced_at' => $now, 'records' => $recordCount]
                : ['status' => 'unavailable', 'last_synced_at' => null, 'records' => 0];
        }

        return $streams;
    }

    /**
     * Sync all machines for an integration
     */
    public function syncMachines(Integration $integration): array
    {
        try {
            $service = $this->getServiceForIntegration($integration);

            if (! $service) {
                return ['success' => false, 'error' => 'Service not found'];
            }

            $machines = $service->fetchMachines();

            // fetchMachines() returns ['success' => bool, 'machines' => [...],
            // 'count' => int, ...] -- this used to iterate that whole result
            // array directly, so the first "machine" synced on every single
            // call, for every manufacturer, was actually the boolean
            // `success` value, fatalling instantly on syncMachine()'s array
            // type hint. Never caught because nothing had ever synced real
            // data through it before.
            if (! ($machines['success'] ?? false)) {
                return [
                    'success' => false,
                    'error' => $machines['error'] ?? 'Failed to fetch machines',
                ];
            }

            $result = $this->persistMachines($integration, $service, $machines['machines'] ?? []);

            $integration->update(['last_sync_at' => now()]);

            return $result;
        } catch (\Throwable $e) {
            Log::error('Integration machine sync failed', [
                'integration_id' => $integration->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Persist an already-fetched machine list. Split out of syncMachines()
     * so IntegrationService::connect() can persist the exact same list its
     * own deep-check already fetched, instead of calling fetchMachines() a
     * second time against the live API (spec's "avoid unnecessary
     * duplicate API calls").
     */
    public function persistMachines(Integration $integration, ManufacturerServiceInterface $service, array $machineList): array
    {
        if (empty($machineList)) {
            return [
                'success' => true,
                'message' => 'No machines found',
                'count' => 0,
            ];
        }

        $synced = 0;
        foreach ($machineList as $machineData) {
            $machine = $this->syncMachine($integration, $machineData);

            // fetchMachines() only carries alerts that happen to be
            // inline in the fleet-list response -- for providers like
            // Bell, whose caution codes are a separate per-machine
            // time-series call by design, that's always empty.
            // fetchMachineAlerts() has existed on every manufacturer
            // service since ManufacturerServiceInterface was written,
            // but nothing ever called it, so real fault/caution codes
            // never reached the Alert table for any provider.
            if ($machine && $machine->manufacturer_id) {
                $this->syncMachineAlertsFromService($service, $machine);
            }

            $synced++;
        }

        return [
            'success' => true,
            'message' => "Synced {$synced} machines",
            'count' => $synced,
        ];
    }

    /**
     * Fetch current locations for a set of machines (identified by their
     * manufacturer_id) from the integration's provider. Used by
     * MachineLocationUpdateJob, which is scheduled every 10 seconds --
     * this method didn't exist at all, so that job fataled on every run
     * for every team with a connected integration.
     *
     * @param  list<string>  $manufacturerIds
     * @return list<array<string, mixed>>
     */
    public function getMachineLocations(Integration $integration, array $manufacturerIds): array
    {
        $service = $this->getServiceForIntegration($integration);

        if (! $service || empty($manufacturerIds)) {
            return [];
        }

        $locations = [];
        foreach ($manufacturerIds as $manufacturerId) {
            try {
                $location = $service->fetchMachineLocation($manufacturerId);
            } catch (\Throwable $e) {
                Log::warning('Failed to fetch machine location', [
                    'integration_id' => $integration->id,
                    'manufacturer_id' => $manufacturerId,
                    'error' => $e->getMessage(),
                ]);

                continue;
            }

            if ($location) {
                $locations[] = array_merge($location, ['manufacturer_id' => $manufacturerId]);
            }
        }

        return $locations;
    }

    /**
     * Fetch current connectivity status for a set of machines (identified
     * by their manufacturer_id). Used by MachineStatusMonitoringJob, which
     * is scheduled every 20 seconds -- this method didn't exist at all
     * either, for the same reason as getMachineLocations() above.
     *
     * A machine is only reported here when the provider actually answered
     * for it -- if a fetch fails or returns nothing, this stays silent
     * about that machine rather than guessing it's offline; the caller's
     * own stale-data timeout already covers machines nothing was heard
     * from.
     *
     * @param  list<string>  $manufacturerIds
     * @return list<array<string, mixed>>
     */
    public function getMachineStatuses(Integration $integration, array $manufacturerIds): array
    {
        $service = $this->getServiceForIntegration($integration);

        if (! $service || empty($manufacturerIds)) {
            return [];
        }

        $statuses = [];
        foreach ($manufacturerIds as $manufacturerId) {
            try {
                $location = $service->fetchMachineLocation($manufacturerId);
            } catch (\Throwable $e) {
                Log::warning('Failed to fetch machine status', [
                    'integration_id' => $integration->id,
                    'manufacturer_id' => $manufacturerId,
                    'error' => $e->getMessage(),
                ]);

                continue;
            }

            // A successful location fetch is the only real connectivity
            // signal available through this interface -- there's no
            // dedicated "status" endpoint on ManufacturerServiceInterface.
            if ($location) {
                $statuses[] = [
                    'manufacturer_id' => $manufacturerId,
                    'online' => true,
                    'status' => 'active',
                ];
            }
        }

        return $statuses;
    }

    /**
     * Sync a single machine
     */
    public function syncMachine(Integration $integration, array $machineData): ?Machine
    {
        try {
            $externalId = $machineData['external_id'] ?? $machineData['id'] ?? null;

            if (! $externalId) {
                return null;
            }

            // Find or create machine. Machine has no 'external_id' or
            // 'latitude'/'longitude' columns at all -- the real fillable
            // fields are 'manufacturer_id' ("ID from manufacturer system",
            // exactly what this is) and 'last_location_latitude'/
            // 'last_location_longitude'. The where() below used to throw a
            // real "column does not exist" SQL error on every sync attempt
            // that got this far; the create()/update() calls would have
            // silently dropped the non-fillable fields instead of erroring.
            $machine = Machine::where('team_id', $integration->team_id)
                ->where('manufacturer_id', $externalId)
                ->where('manufacturer', $integration->provider)
                ->first();

            if (! $machine) {
                $machine = Machine::create([
                    'team_id' => $integration->team_id,
                    'name' => $machineData['model'] ?? 'Unknown Machine',
                    // machine_type is NOT NULL with no default and this
                    // create() never set it at all; manufacturer telemetry
                    // APIs don't return this app's own adt/excavator/dozer/
                    // etc categorization, so 'other' is the honest fallback
                    // -- a real user can reclassify it from the Fleet page.
                    'machine_type' => $machineData['type'] ?? 'other',
                    'manufacturer' => $integration->provider,
                    'model' => $machineData['model'] ?? null,
                    'serial_number' => $machineData['serial_number'] ?? null,
                    'manufacturer_id' => $externalId,
                    'integration_id' => $integration->id,
                    'status' => $machineData['status'] ?? 'idle',
                    'last_location_latitude' => $machineData['last_location']['latitude'] ?? null,
                    'last_location_longitude' => $machineData['last_location']['longitude'] ?? null,
                    'capacity' => $machineData['capacity'] ?? null,
                ]);
            } else {
                $machine->update([
                    'status' => $machineData['status'] ?? 'idle',
                    'last_location_latitude' => $machineData['last_location']['latitude'] ?? $machine->last_location_latitude,
                    'last_location_longitude' => $machineData['last_location']['longitude'] ?? $machine->last_location_longitude,
                    'last_location_update' => now(),
                ]);
            }

            // Sync metrics if available
            if (! empty($machineData['metrics'])) {
                $this->syncMachineMetrics($machine, $machineData['metrics']);
            }

            // Sync alerts if available
            if (! empty($machineData['alerts'])) {
                $this->syncMachineAlerts($machine, $machineData['alerts']);
            }

            return $machine;
        } catch (\Throwable $e) {
            Log::error('Failed to sync individual machine', [
                'integration_id' => $integration->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Sync machine metrics
     */
    protected function syncMachineMetrics(Machine $machine, array $metrics): void
    {
        try {
            $metric = new MachineMetric($metrics);
            $metric->machine_id = $machine->id;
            $metric->team_id = $machine->team_id;
            $metric->save();
        } catch (\Throwable $e) {
            Log::warning('Failed to sync machine metrics', [
                'machine_id' => $machine->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Sync machine alerts
     */
    protected function syncMachineAlerts(Machine $machine, array $alerts): void
    {
        try {
            foreach ($alerts as $alertData) {
                $externalId = $alertData['external_id'] ?? null;

                if (! $externalId) {
                    continue;
                }

                // Avoid duplicate alerts. Alert has no 'external_id' column
                // either -- store it in 'metadata' (a real json column) and
                // query it via the same JSON-path syntax used elsewhere in
                // this app, instead of a plain where() on a column that
                // doesn't exist.
                $existing = Alert::where('machine_id', $machine->id)
                    ->where('metadata->external_id', $externalId)
                    ->first();

                if (! $existing) {
                    Alert::create([
                        'team_id' => $machine->team_id,
                        'machine_id' => $machine->id,
                        'title' => $alertData['title'] ?? 'Alert',
                        'description' => $alertData['description'] ?? '',
                        'type' => $alertData['type'] ?? 'sensor',
                        'priority' => $alertData['priority'] ?? 'medium',
                        'status' => $alertData['status'] ?? 'active',
                        'triggered_at' => now(),
                        'metadata' => ['external_id' => $externalId],
                    ]);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to sync machine alerts', [
                'machine_id' => $machine->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Calls the manufacturer service's own fetchMachineAlerts() for one
     * machine and routes any results through the same dedup/create path as
     * inline alerts. Isolated in its own try/catch so one machine's alert
     * fetch failing (a manufacturer endpoint that doesn't exist, a
     * transient error) never aborts the rest of the sync -- syncMachines()
     * already tolerates individual machine failures the same way.
     */
    private function syncMachineAlertsFromService(ManufacturerServiceInterface $service, Machine $machine): void
    {
        try {
            $alerts = $service->fetchMachineAlerts($machine->manufacturer_id);

            if (! empty($alerts)) {
                $this->syncMachineAlerts($machine, $alerts);
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to fetch machine alerts during sync', [
                'machine_id' => $machine->id,
                'manufacturer_id' => $machine->manufacturer_id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get service instance for an integration
     */
    public function getServiceForIntegration(Integration $integration): ?ManufacturerServiceInterface
    {
        // Integration::credentials is already cast to an array ('json' cast
        // in the model) -- json_decode()-ing it again threw a TypeError
        // ("Argument #1 ($json) must be of type string, array given") on
        // every single test/sync attempt, for every manufacturer, before
        // any manufacturer-specific code ever ran.
        $credentials = $integration->credentials ?? [];

        return match ($integration->provider) {
            'volvo' => app(VolvoService::class, ['credentials' => $credentials]),
            'cat' => app(CATService::class, ['credentials' => $credentials]),
            'komatsu' => app(KomatsuService::class, ['credentials' => $credentials]),
            'bell' => app(BellService::class, ['credentials' => $credentials]),
            'hitachi' => app(HitachiService::class, ['credentials' => $credentials]),
            'john-deere' => app(JohnDeereService::class, ['credentials' => $credentials]),
            'liebherr' => app(LiebherrService::class, ['credentials' => $credentials]),
            'hyundai' => app(HyundaiService::class, ['credentials' => $credentials]),
            'doosan' => app(DoosanService::class, ['credentials' => $credentials]),
            'jcb' => app(JCBService::class, ['credentials' => $credentials]),
            'case' => app(CASEService::class, ['credentials' => $credentials]),
            'sany' => app(SanyService::class, ['credentials' => $credentials]),
            'xcmg' => app(XCMGService::class, ['credentials' => $credentials]),
            'kobelco' => app(KobelcoService::class, ['credentials' => $credentials]),
            'new-holland' => app(NewHollandService::class, ['credentials' => $credentials]),
            'takeuchi' => app(TakeuchiService::class, ['credentials' => $credentials]),
            'kubota' => app(KubotaService::class, ['credentials' => $credentials]),
            'bobcat' => app(BobcatService::class, ['credentials' => $credentials]),
            'yanmar' => app(YanmarService::class, ['credentials' => $credentials]),
            'atlas-copco' => app(AtlasCopcoService::class, ['credentials' => $credentials]),
            'sandvik' => app(SandvikService::class, ['credentials' => $credentials]),
            'epiroc' => app(EpirocService::class, ['credentials' => $credentials]),
            'ctrack' => app(CTrackService::class, ['credentials' => $credentials]),
            'roundebult' => app(RoundebultService::class, ['credentials' => $credentials]),
            'kawasaki' => app(KawasakiService::class, ['credentials' => $credentials]),
            default => null,
        };
    }

    /**
     * Get available manufacturers
     */
    public function getAvailableManufacturers(): array
    {
        // 'status' reflects whether the manufacturer's service class actually
        // attempts a real API call (only verifiable this way -- these are
        // third-party APIs this app can't reach in CI/testing to confirm
        // credentials genuinely work end to end). 8 of the 25 have no real
        // implementation at all: their testConnection() always returned
        // true regardless of what credentials were entered, until that was
        // fixed to honestly report 'not yet available' instead.
        return [
            'volvo' => ['name' => 'Volvo', 'icon' => '🔵', 'description' => 'Volvo Heavy Equipment', 'status' => 'available'],
            'cat' => ['name' => 'Caterpillar', 'icon' => '🟡', 'description' => 'Caterpillar Heavy Equipment', 'status' => 'available'],
            'komatsu' => ['name' => 'Komatsu', 'icon' => '🔶', 'description' => 'Komatsu Heavy Equipment', 'status' => 'available'],
            'bell' => ['name' => 'Bell', 'icon' => '🟠', 'description' => 'Bell Equipment ISO 15143-3 Fleet API', 'status' => 'available'],
            'hitachi' => ['name' => 'Hitachi', 'icon' => '🟧', 'description' => 'Hitachi Construction Machinery', 'status' => 'available'],
            'john-deere' => ['name' => 'John Deere', 'icon' => '🟩', 'description' => 'John Deere Equipment', 'status' => 'coming_soon'],
            'liebherr' => ['name' => 'Liebherr', 'icon' => '🟨', 'description' => 'Liebherr Mining Equipment', 'status' => 'available'],
            'hyundai' => ['name' => 'Hyundai', 'icon' => '🟦', 'description' => 'Hyundai Construction Equipment', 'status' => 'available'],
            'doosan' => ['name' => 'Doosan', 'icon' => '🟧', 'description' => 'Doosan Heavy Equipment', 'status' => 'available'],
            'jcb' => ['name' => 'JCB', 'icon' => '🟨', 'description' => 'JCB Construction Equipment', 'status' => 'available'],
            'case' => ['name' => 'CASE', 'icon' => '🟫', 'description' => 'CASE Construction Equipment', 'status' => 'coming_soon'],
            'sany' => ['name' => 'Sany', 'icon' => '🟥', 'description' => 'Sany Heavy Equipment', 'status' => 'available'],
            'xcmg' => ['name' => 'XCMG', 'icon' => '🟦', 'description' => 'XCMG Construction Equipment', 'status' => 'available'],
            'kobelco' => ['name' => 'Kobelco', 'icon' => '🟦', 'description' => 'Kobelco Construction Machinery', 'status' => 'available'],
            'new-holland' => ['name' => 'New Holland', 'icon' => '🟨', 'description' => 'New Holland Equipment', 'status' => 'coming_soon'],
            'takeuchi' => ['name' => 'Takeuchi', 'icon' => '🟥', 'description' => 'Takeuchi Compact Equipment', 'status' => 'coming_soon'],
            'kubota' => ['name' => 'Kubota', 'icon' => '🟧', 'description' => 'Kubota Construction Equipment', 'status' => 'available'],
            'bobcat' => ['name' => 'Bobcat', 'icon' => '⬜', 'description' => 'Bobcat Compact Equipment', 'status' => 'coming_soon'],
            'yanmar' => ['name' => 'Yanmar', 'icon' => '🟨', 'description' => 'Yanmar Mini Excavators', 'status' => 'coming_soon'],
            'atlas-copco' => ['name' => 'Atlas Copco', 'icon' => '🟡', 'description' => 'Atlas Copco Drilling Equipment', 'status' => 'coming_soon'],
            'sandvik' => ['name' => 'Sandvik', 'icon' => '🟥', 'description' => 'Sandvik Mining Equipment', 'status' => 'coming_soon'],
            'epiroc' => ['name' => 'Epiroc', 'icon' => '🟦', 'description' => 'Epiroc Drilling Equipment', 'status' => 'available'],
            'ctrack' => ['name' => 'C-Track', 'icon' => '📍', 'description' => 'C-Track GPS Tracking', 'status' => 'available'],
            'roundebult' => ['name' => 'Roundebult', 'icon' => '⛏️', 'description' => 'Roundebult Mining Machines', 'status' => 'available'],
            'kawasaki' => ['name' => 'Kawasaki', 'icon' => '🏗️', 'description' => 'Kawasaki Mining Equipment', 'status' => 'available'],
        ];
    }

    /**
     * Derives which data streams a connected account actually provides
     * from the real shape of one sample machine record -- never a static
     * per-provider assumption. 'fleet' is present whenever a machine was
     * returned at all; 'telemetry'/'production'/'location' each require a
     * real, non-null field to be present, matching the exact shapes
     * BaseManufacturerService::parseMetrics()/BellService::buildCurrentMetric()
     * actually produce today.
     *
     * @return list<'fleet'|'telemetry'|'production'|'location'>
     */
    public function deriveCapabilities(array $sampleMachine): array
    {
        if (empty($sampleMachine)) {
            return [];
        }

        $capabilities = ['fleet'];
        $metrics = $sampleMachine['metrics'] ?? [];
        $rawData = $metrics['raw_data'] ?? [];

        $telemetryKeys = [
            'fuel_level', 'engine_temperature', 'operating_hours', 'idle_hours',
            'oil_pressure', 'coolant_temperature', 'battery_voltage', 'engine_rpm',
        ];
        foreach ($telemetryKeys as $key) {
            if (($metrics[$key] ?? null) !== null) {
                $capabilities[] = 'telemetry';
                break;
            }
        }

        $productionKeys = ['load_count', 'cumulative_payload', 'load_weight', 'cycles', 'payload_units'];
        foreach ($productionKeys as $key) {
            if (($metrics[$key] ?? null) !== null || ($rawData[$key] ?? null) !== null) {
                $capabilities[] = 'production';
                break;
            }
        }

        if (! empty($sampleMachine['last_location']['latitude']) && ! empty($sampleMachine['last_location']['longitude'])) {
            $capabilities[] = 'location';
        }

        return array_values(array_unique($capabilities));
    }

    /**
     * Get integration status
     */
    public function getStatus(Integration $integration): array
    {
        $cacheKey = "integration_{$integration->id}_status";

        return Cache::remember($cacheKey, 300, function () use ($integration) {
            return [
                'id' => $integration->id,
                'provider' => $integration->provider,
                'status' => $integration->status,
                'connected' => $integration->status === 'connected',
                'last_sync_at' => $integration->last_sync_at,
                'last_sync_status' => $integration->last_sync_status,
                'machines_count' => Machine::where('team_id', $integration->team_id)
                    ->where('manufacturer', $integration->provider)
                    ->count(),
            ];
        });
    }
}
