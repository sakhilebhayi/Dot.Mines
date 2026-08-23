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
    #[\Override]
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
     */
    /** @return array<string, mixed> */
    #[\Override]
    public function fetchMachines(): array
    {
        try {
            $response = $this->makeRequest('GET', '/assets');

            $machines = [];
            if (! empty($response['assets'])) {
                $rows2 = self::rowsOf(data_get($response, 'assets'));
                foreach ($rows2 as $asset) {
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
     * @return array<string, mixed>
     */
    public function fetchLocation(string $machineId): array
    {
        try {
            $response = $this->makeRequest('GET', "/machines/{$machineId}/location");

            return [
                'success' => true,
                'location' => $this->parseLocation(self::payloadArray($response['data'] ?? null)),
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
     */
    /** @return array<string, mixed> */
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
                $rows3 = self::rowsOf(data_get($diagnostics, 'diagnostics'));
                foreach ($rows3 as $diagnostic) {
                    $metrics[] = $this->parseMetric($diagnostic);
                }
            }

            // Add fuel metrics
            if (! empty($fuelUsed['fuelUsed'])) {
                $metrics[] = [
                    'type' => 'fuel_used',
                    'value' => data_get($fuelUsed, 'fuelUsed.totalFuelUsed') ?? 0,
                    'unit' => 'liters',
                    'timestamp' => data_get($fuelUsed, 'fuelUsed.timestamp') ?? now(),
                ];
            }

            // Add engine hours
            if (! empty($engineHours['engineHours'])) {
                $metrics[] = [
                    'type' => 'engine_hours',
                    'value' => data_get($engineHours, 'engineHours.totalHours') ?? 0,
                    'unit' => 'hours',
                    'timestamp' => data_get($engineHours, 'engineHours.timestamp') ?? now(),
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
     */
    /** @return array<string, mixed> */
    public function fetchAlerts(string $machineId): array
    {
        try {
            $response = $this->makeRequest('GET', "/machines/{$machineId}/alerts");

            $alerts = [];
            if (! empty($response['data']['alerts'])) {
                $rows4 = self::rowsOf(data_get($response, 'data.alerts'));
                foreach ($rows4 as $alert) {
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
     */
    /** @param array<string, mixed> $data
     * @return array<string, mixed> */
    #[\Override]
    protected function parseMachineData(array $data): array
    {
        return [
            'external_id' => $data['id'] ?? $data['machine_id'] ?? null,
            'name' => $data['name'] ?? $data['asset_name'] ?? 'Unknown Machine',
            'model' => $data['model'] ?? $data['model_name'] ?? 'Unknown Model',
            'manufacturer' => 'Caterpillar',
            'status' => $this->parseStatus(self::str($data['status'] ?? null, 'unknown')),
            'location' => $this->parseLocation(self::payloadArray($data['location'] ?? null)),
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
     * @return array<string, mixed>
     */
    #[\Override]
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
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
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
     */
    #[\Override]
    protected function parseAlert(array $data): array
    {
        return [
            // Missing statuses default to 'active', never the legacy 'new'
            // (not in the alerts.status enum) -- same invariant as the Base
            // normaliser, pinned by ManufacturerAlertsShapeTest.
            'status' => $data['status'] ?? 'active',
            'external_id' => $data['id'] ?? $data['alert_id'] ?? null,
            'type' => $this->mapAlertType(self::str($data['type'] ?? $data['code'] ?? null, 'unknown')),
            'priority' => $this->mapAlertPriority(self::str($data['severity'] ?? $data['priority'] ?? null, 'medium')),
            'message' => $data['message'] ?? $data['description'] ?? 'Alert from CAT',
            'timestamp' => $data['timestamp'] ?? $data['created_at'] ?? now()->toIso8601String(),
            'acknowledged' => $data['acknowledged'] ?? false,
            'raw_data' => $data,
        ];
    }

    /**
     * Map CAT status to standard status
     */
    #[\Override]
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
     */
    /** @return array<string, mixed> */
    #[\Override]
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
    #[\Override]
    public function fetchMachineLocation(string $machineId): ?array
    {
        try {
            $result = $this->fetchLocation($machineId);

            return is_array($result['location'] ?? null) ? self::payloadArray($result['location']) : null;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Fetch machine metrics
     */
    #[\Override]
    public function fetchMachineMetrics(string $machineId): array
    {
        try {
            $result = $this->fetchMetrics($machineId);

            // fetchMetrics() builds a list of {type, value, unit, timestamp}
            // readings, not the flat MachineMetric-column shape the sync
            // pipeline expects -- see normalizeMetricsForStorage().
            /** @var list<array<string, mixed>> $readings */
            $readings = array_values(array_filter((array) data_get($result, 'metrics', []), 'is_array'));

            return $this->normalizeMetricsForStorage($readings);
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Fetch machine alerts
     */
    #[\Override]
    public function fetchMachineAlerts(string $machineId): array
    {
        try {
            $result = $this->fetchAlerts($machineId);

            return self::rowsOf($result['alerts'] ?? null);
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Get API error if any occurred
     */
    #[\Override]
    public function getLastError(): ?string
    {
        return $this->lastError;
    }
}
