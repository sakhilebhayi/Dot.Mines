<?php

namespace App\Services\Integration;

use App\Contracts\ManufacturerServiceInterface;
use App\Models\Alert;
use App\Models\Integration;
use App\Models\Machine;
use App\Models\MachineMetric;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class IntegrationService
{
    /** @var array<string, mixed> */
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
     *
     * @return array<mixed>
     */
    public function all(): array
    {
        return $this->services;
    }

    /**
     * Test connection to a manufacturer API
     *
     * @return array<mixed>
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
        } catch (\Exception $e) {
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
     * Sync all machines for an integration
     *
     * @return array<mixed>
     */
    public function syncMachines(Integration $integration): array
    {
        try {
            $service = $this->getServiceForIntegration($integration);

            if (! $service) {
                return ['success' => false, 'error' => 'Service not found'];
            }

            $machines = $service->fetchMachines();

            if (empty($machines)) {
                return [
                    'success' => true,
                    'message' => 'No machines found',
                    'count' => 0,
                ];
            }

            $synced = 0;
            foreach ($machines as $machineData) {
                $this->syncMachine($integration, $machineData);
                $synced++;
            }

            $integration->update(['last_sync_at' => now()]);

            return [
                'success' => true,
                'message' => "Synced {$synced} machines",
                'count' => $synced,
            ];
        } catch (\Exception $e) {
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
     * Sync a single machine
     */
    /** @param array<string, mixed> $machineData */
    public function syncMachine(Integration $integration, array $machineData): ?Machine
    {
        try {
            $externalId = $machineData['external_id'] ?? $machineData['id'] ?? null;

            if (! $externalId) {
                return null;
            }

            // Find or create machine
            $machine = Machine::where('team_id', $integration->team_id)
                ->where('external_id', $externalId)
                ->where('manufacturer', $integration->provider)
                ->first();

            if (! $machine) {
                $machine = Machine::create([
                    'team_id' => $integration->team_id,
                    'name' => $machineData['model'] ?? 'Unknown Machine',
                    'manufacturer' => $integration->provider,
                    'model' => $machineData['model'] ?? null,
                    'serial_number' => $machineData['serial_number'] ?? null,
                    'external_id' => $externalId,
                    'status' => $machineData['status'] ?? 'idle',
                    'latitude' => $machineData['last_location']['latitude'] ?? null,
                    'longitude' => $machineData['last_location']['longitude'] ?? null,
                    'capacity' => $machineData['capacity'] ?? null,
                ]);
            } else {
                $machine->update([
                    'status' => $machineData['status'] ?? 'idle',
                    'latitude' => $machineData['last_location']['latitude'] ?? $machine->latitude,
                    'longitude' => $machineData['last_location']['longitude'] ?? $machine->longitude,
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
        } catch (\Exception $e) {
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
    /** @param array<mixed> $metrics */
    protected function syncMachineMetrics(Machine $machine, array $metrics): void
    {
        try {
            $metric = new MachineMetric($metrics);
            $metric->machine_id = $machine->id;
            $metric->team_id = $machine->team_id;
            $metric->save();
        } catch (\Exception $e) {
            Log::warning('Failed to sync machine metrics', [
                'machine_id' => $machine->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Sync machine alerts
     */
    /** @param array<mixed> $alerts */
    protected function syncMachineAlerts(Machine $machine, array $alerts): void
    {
        try {
            foreach ($alerts as $alertData) {
                $externalId = $alertData['external_id'] ?? null;

                if (! $externalId) {
                    continue;
                }

                // Avoid duplicate alerts
                $existing = Alert::where('machine_id', $machine->id)
                    ->where('external_id', $externalId)
                    ->first();

                if (! $existing) {
                    Alert::create([
                        'team_id' => $machine->team_id,
                        'machine_id' => $machine->id,
                        'title' => $alertData['title'] ?? 'Alert',
                        'description' => $alertData['description'] ?? '',
                        'type' => $alertData['type'] ?? 'sensor',
                        'priority' => $alertData['priority'] ?? 'medium',
                        'status' => $alertData['status'] ?? 'new',
                        'external_id' => $externalId,
                    ]);
                }
            }
        } catch (\Exception $e) {
            Log::warning('Failed to sync machine alerts', [
                'machine_id' => $machine->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get service instance for an integration
     */
    protected function getServiceForIntegration(Integration $integration): ?ManufacturerServiceInterface
    {
        $credentials = is_string($integration->credentials) ? (json_decode($integration->credentials, true) ?? []) : [];

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
     *
     * @return array<mixed>
     */
    public function getAvailableManufacturers(): array
    {
        return [
            'volvo' => ['name' => 'Volvo', 'icon' => '🔵', 'description' => 'Volvo Heavy Equipment', 'status' => 'available'],
            'cat' => ['name' => 'Caterpillar', 'icon' => '🟡', 'description' => 'Caterpillar Heavy Equipment', 'status' => 'available'],
            'komatsu' => ['name' => 'Komatsu', 'icon' => '🔶', 'description' => 'Komatsu Heavy Equipment', 'status' => 'available'],
            'bell' => ['name' => 'Bell', 'icon' => '🟠', 'description' => 'Bell Haul Trucks', 'status' => 'available'],
            'hitachi' => ['name' => 'Hitachi', 'icon' => '🟧', 'description' => 'Hitachi Construction Machinery', 'status' => 'available'],
            'john-deere' => ['name' => 'John Deere', 'icon' => '🟩', 'description' => 'John Deere Equipment', 'status' => 'available'],
            'liebherr' => ['name' => 'Liebherr', 'icon' => '🟨', 'description' => 'Liebherr Mining Equipment', 'status' => 'available'],
            'hyundai' => ['name' => 'Hyundai', 'icon' => '🟦', 'description' => 'Hyundai Construction Equipment', 'status' => 'available'],
            'doosan' => ['name' => 'Doosan', 'icon' => '🟧', 'description' => 'Doosan Heavy Equipment', 'status' => 'available'],
            'jcb' => ['name' => 'JCB', 'icon' => '🟨', 'description' => 'JCB Construction Equipment', 'status' => 'available'],
            'case' => ['name' => 'CASE', 'icon' => '🟫', 'description' => 'CASE Construction Equipment', 'status' => 'available'],
            'sany' => ['name' => 'Sany', 'icon' => '🟥', 'description' => 'Sany Heavy Equipment', 'status' => 'available'],
            'xcmg' => ['name' => 'XCMG', 'icon' => '🟦', 'description' => 'XCMG Construction Equipment', 'status' => 'available'],
            'kobelco' => ['name' => 'Kobelco', 'icon' => '🟦', 'description' => 'Kobelco Construction Machinery', 'status' => 'available'],
            'new-holland' => ['name' => 'New Holland', 'icon' => '🟨', 'description' => 'New Holland Equipment', 'status' => 'available'],
            'takeuchi' => ['name' => 'Takeuchi', 'icon' => '🟥', 'description' => 'Takeuchi Compact Equipment', 'status' => 'available'],
            'kubota' => ['name' => 'Kubota', 'icon' => '🟧', 'description' => 'Kubota Construction Equipment', 'status' => 'available'],
            'bobcat' => ['name' => 'Bobcat', 'icon' => '⬜', 'description' => 'Bobcat Compact Equipment', 'status' => 'available'],
            'yanmar' => ['name' => 'Yanmar', 'icon' => '🟨', 'description' => 'Yanmar Mini Excavators', 'status' => 'available'],
            'atlas-copco' => ['name' => 'Atlas Copco', 'icon' => '🟡', 'description' => 'Atlas Copco Drilling Equipment', 'status' => 'available'],
            'sandvik' => ['name' => 'Sandvik', 'icon' => '🟥', 'description' => 'Sandvik Mining Equipment', 'status' => 'available'],
            'epiroc' => ['name' => 'Epiroc', 'icon' => '🟦', 'description' => 'Epiroc Drilling Equipment', 'status' => 'available'],
            'ctrack' => ['name' => 'C-Track', 'icon' => '📍', 'description' => 'C-Track GPS Tracking', 'status' => 'available'],
            'roundebult' => ['name' => 'Roundebult', 'icon' => '⛏️', 'description' => 'Roundebult Mining Machines', 'status' => 'available'],
            'kawasaki' => ['name' => 'Kawasaki', 'icon' => '🏗️', 'description' => 'Kawasaki Mining Equipment', 'status' => 'available'],
        ];
    }

    /**
     * Get integration status
     *
     * @return array<mixed>
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

    /**
     * Get machine locations from the integration provider
     *
     * @param  array<mixed>  $manufacturerIds
     * @return array<int, array<string, mixed>>
     */
    public function getMachineLocations(Integration $integration, array $manufacturerIds): array
    {
        $service = $this->getServiceForIntegration($integration);

        if (! $service) {
            return [];
        }

        return $service->fetchMachines();
    }

    /**
     * Get machine statuses from the integration provider
     *
     * @param  array<mixed>  $manufacturerIds
     * @return array<int, array<string, mixed>>
     */
    public function getMachineStatuses(Integration $integration, array $manufacturerIds): array
    {
        $service = $this->getServiceForIntegration($integration);

        if (! $service) {
            return [];
        }

        return $service->fetchMachines();
    }
}
