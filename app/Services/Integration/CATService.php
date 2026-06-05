<?php

namespace App\Services\Integration;

use App\Contracts\ManufacturerServiceInterface;
use Exception;

/**
 * Caterpillar VisionLink / Product Link Integration Service
 *
 * Handles integration with Caterpillar VisionLink API
 * Requires dealer authorization and subscription ID
 * Documentation: https://developer.cat.com/api-catalog/visionlink
 */
class CATService extends BaseManufacturerService implements ManufacturerServiceInterface
{
    /**
     * Manufacturer identifier
     */
    protected string $manufacturer = 'cat';

    /**
     * Test connection to CAT VisionLink API
     */
    public function testConnection(): bool
    {
        try {
            // Test with assets endpoint
            $response = $this->makeRequest('GET', '/assets');

            return ! empty($response) && $response['success'] !== false;
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();

            return false;
        }
    }

    /**
     * Fetch machines from CAT VisionLink API
     *
     * @return array<mixed>
     */
    public function fetchMachines(): array
    {
        try {
            $response = $this->makeRequest('GET', '/assets');

            $machines = [];
            if (! empty($response['assets'])) {
                foreach ($response['assets'] as $asset) {
                    $machines[] = $this->parseMachineData($asset);
                }
            }

            return [
                'success' => true,
                'machines' => $machines,
                'count' => count($machines),
            ];
        } catch (Exception $e) {
            $this->logError('Failed to fetch assets', $e);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'machines' => [],
            ];
        }
    }

    /**
     * Fetch location data for a machine
     *
     * @return array<mixed>
     */
    public function fetchLocation(string $machineId): array
    {
        try {
            $response = $this->makeRequest('GET', "/machines/{$machineId}/location");

            return [
                'success' => true,
                'location' => $this->parseLocation($response['data'] ?? []),
            ];
        } catch (Exception $e) {
            $this->logError('Failed to fetch location', $e);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Fetch diagnostics/metrics for a machine
     *
     * @return array<mixed>
     */
    public function fetchMetrics(string $machineId): array
    {
        try {
            // Fetch multiple metric types from VisionLink
            $diagnostics = $this->makeRequest('GET', "/assets/{$machineId}/diagnostics");
            $fuelUsed = $this->makeRequest('GET', "/assets/{$machineId}/fuelUsed");
            $engineHours = $this->makeRequest('GET', "/assets/{$machineId}/engineHours");
            $productivity = $this->makeRequest('GET', "/assets/{$machineId}/productivity");

            $metrics = [];

            // Parse diagnostics
            if (! empty($diagnostics['diagnostics'])) {
                foreach ($diagnostics['diagnostics'] as $diagnostic) {
                    $metrics[] = $this->parseMetric($diagnostic);
                }
            }

            // Add fuel metrics
            if (! empty($fuelUsed['fuelUsed'])) {
                $metrics[] = [
                    'type' => 'fuel_used',
                    'value' => $fuelUsed['fuelUsed']['totalFuelUsed'] ?? 0,
                    'unit' => 'liters',
                    'timestamp' => $fuelUsed['fuelUsed']['timestamp'] ?? now(),
                ];
            }

            // Add engine hours
            if (! empty($engineHours['engineHours'])) {
                $metrics[] = [
                    'type' => 'engine_hours',
                    'value' => $engineHours['engineHours']['totalHours'] ?? 0,
                    'unit' => 'hours',
                    'timestamp' => $engineHours['engineHours']['timestamp'] ?? now(),
                ];
            }

            // Add productivity data
            if (! empty($productivity['productivityData'])) {
                $metrics[] = [
                    'type' => 'productivity',
                    'value' => $productivity['productivityData'],
                    'timestamp' => $productivity['timestamp'] ?? now(),
                ];
            }

            return [
                'success' => true,
                'metrics' => $metrics,
            ];
        } catch (Exception $e) {
            $this->logError('Failed to fetch metrics', $e);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'metrics' => [],
            ];
        }
    }

    /**
     * Fetch alerts for a machine
     *
     * @return array<mixed>
     */
    public function fetchAlerts(string $machineId): array
    {
        try {
            $response = $this->makeRequest('GET', "/machines/{$machineId}/alerts");

            $alerts = [];
            if (! empty($response['data']['alerts'])) {
                foreach ($response['data']['alerts'] as $alert) {
                    $alerts[] = $this->parseAlert($alert);
                }
            }

            return [
                'success' => true,
                'alerts' => $alerts,
            ];
        } catch (Exception $e) {
            $this->logError('Failed to fetch alerts', $e);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'alerts' => [],
            ];
        }
    }

    /**
     * Parse machine data from CAT format
     *
     * @return array<mixed>
     */
    protected function parseMachineData(array $data): array
    {
        return [
            'external_id' => $data['id'] ?? $data['machine_id'] ?? null,
            'name' => $data['name'] ?? $data['asset_name'] ?? 'Unknown Machine',
            'model' => $data['model'] ?? $data['model_name'] ?? 'Unknown Model',
            'manufacturer' => 'Caterpillar',
            'status' => $this->parseStatus($data['status'] ?? 'unknown'),
            'location' => $this->parseLocation($data['location'] ?? []),
            'last_heartbeat' => $data['last_heartbeat'] ?? null,
            'specifications' => [
                'type' => $data['type'] ?? 'heavy_equipment',
                'model_code' => $data['model_code'] ?? null,
                'serial_number' => $data['serial_number'] ?? null,
                'year_manufactured' => $data['year_manufactured'] ?? null,
                'operating_weight' => $data['operating_weight'] ?? null,
                'bucket_capacity' => $data['bucket_capacity'] ?? null,
            ],
        ];
    }

    /**
     * Parse location data from CAT format
     *
     * @return array<mixed>
     */
    protected function parseLocation(array $data): array
    {
        return [
            'latitude' => $data['latitude'] ?? $data['lat'] ?? 0,
            'longitude' => $data['longitude'] ?? $data['lon'] ?? 0,
            'altitude' => $data['altitude'] ?? 0,
            'accuracy' => $data['accuracy'] ?? 0,
            'timestamp' => $data['timestamp'] ?? now()->toIso8601String(),
        ];
    }

    /**
     * Parse telemetry/metric data from CAT format
     *
     * @return array<mixed>
     */
    protected function parseMetric(array $data): array
    {
        return [
            'type' => $data['name'] ?? $data['type'] ?? 'unknown',
            'value' => $data['value'] ?? 0,
            'unit' => $data['unit'] ?? '',
            'timestamp' => $data['timestamp'] ?? now()->toIso8601String(),
            'tags' => [
                'sensor_id' => $data['sensor_id'] ?? null,
                'system' => $data['system'] ?? null,
            ],
        ];
    }

    /**
     * Parse alert data from CAT format
     *
     * @return array<mixed>
     */
    protected function parseAlert(array $data): array
    {
        return [
            'external_id' => $data['id'] ?? $data['alert_id'] ?? null,
            'type' => $this->mapAlertType($data['type'] ?? $data['code'] ?? 'unknown'),
            'priority' => $this->mapAlertPriority($data['severity'] ?? $data['priority'] ?? 'medium'),
            'message' => $data['message'] ?? $data['description'] ?? 'Alert from CAT',
            'timestamp' => $data['timestamp'] ?? $data['created_at'] ?? now()->toIso8601String(),
            'acknowledged' => $data['acknowledged'] ?? false,
            'raw_data' => $data,
        ];
    }

    /**
     * Map CAT status to standard status
     */
    protected function parseStatus(string $status): string
    {
        $statusMap = [
            'on' => 'active',
            'off' => 'inactive',
            'idle' => 'idle',
            'operating' => 'active',
            'working' => 'active',
            'stopped' => 'stopped',
            'maintenance' => 'maintenance',
            'fault' => 'error',
            'error' => 'error',
        ];

        return $statusMap[strtolower($status)] ?? 'unknown';
    }

    /**
     * Fetch machine details from CAT API
     *
     * @return array<mixed>
     */
    public function fetchMachineDetails(string $machineId): array
    {
        // Return location and metrics as a composite detail view
        $location = $this->fetchLocation($machineId);

        return [
            'location' => $location['location'] ?? [],
            'success' => $location['success'] ?? false,
        ];
    }

    /**
     * Fetch machine location
     */
    public function fetchMachineLocation(string $machineId): ?array
    {
        try {
            $result = $this->fetchLocation($machineId);

            return ($result['location'] ?? null) ?? null;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Fetch machine metrics
     *
     * @return array<mixed>
     */
    public function fetchMachineMetrics(string $machineId): array
    {
        try {
            $result = $this->fetchMetrics($machineId);

            return $result['metrics'] ?? [];
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Fetch machine alerts
     *
     * @return array<mixed>
     */
    public function fetchMachineAlerts(string $machineId): array
    {
        try {
            $result = $this->fetchAlerts($machineId);

            return $result['alerts'] ?? [];
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Fetch comprehensive machine data
     *
     * @return array<mixed>
     */
    public function fetchMachineData(string $machineId): array
    {
        return [
            'details' => $this->fetchMachineDetails($machineId),
            'location' => $this->fetchMachineLocation($machineId),
            'metrics' => $this->fetchMachineMetrics($machineId),
            'alerts' => $this->fetchMachineAlerts($machineId),
        ];
    }

    /**
     * Get the manufacturer name
     */
    public function getManufacturer(): string
    {
        return $this->manufacturer;
    }

    /**
     * Get API error if any occurred
     */
    public function getLastError(): ?string
    {
        return $this->lastError;
    }
}
