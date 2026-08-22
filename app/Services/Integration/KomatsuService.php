<?php

namespace App\Services\Integration;

use Exception;

/**
 * Komatsu KOMTRAX Integration Service
 *
 * Handles integration with Komatsu KOMTRAX API
 * Requires customer ID and Komatsu representative approval
 * Contact: Komatsu representative for API access
 */
class KomatsuService extends BaseManufacturerService
{
    /**
     * Manufacturer identifier
     */
    protected string $manufacturer = 'komatsu';

    /**
     * Test connection to Komatsu KOMTRAX API
     */
    #[\Override]
    public function testConnection(): bool
    {
        try {
            // Test with machines endpoint
            $response = $this->makeRequest('GET', '/api/v2/machines');

            return ! empty($response) && $response['success'] !== false;
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();

            return false;
        }
    }

    /**
     * Fetch machines from Komatsu KOMTRAX API
     */
    /** @return array<string, mixed> */
    #[\Override]
    public function fetchMachines(): array
    {
        try {
            $response = $this->makeRequest('GET', '/api/v2/machines');

            $machines = [];
            if (! empty($response['machines'])) {
                $rows22 = data_get($response, 'machines');
                /** @var list<array<string, mixed>> $rows22 */
                $rows22 = is_array($rows22) ? array_values(array_filter($rows22, 'is_array')) : [];
                foreach ($rows22 as $machine) {
                    $machines[] = $this->parseMachineData($machine);
                }
            }

            return [
                'success' => true,
                'machines' => $machines,
                'count' => count($machines),
            ];
        } catch (Exception $e) {
            $this->logError('Failed to fetch machines', $e);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'machines' => [],
            ];
        }
    }

    /**
     * Fetch location data for equipment
     *
     * @return array<string, mixed>
     */
    public function fetchLocation(string $machineId): array
    {
        try {
            $response = $this->makeRequest('GET', "/api/v2/machines/{$machineId}/location");

            return [
                'success' => true,
                'location' => $this->parseLocation($response),
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
     * Fetch performance/metrics for equipment
     */
    /** @return array<string, mixed> */
    public function fetchMetrics(string $machineId): array
    {
        try {
            // Fetch multiple metric types from KOMTRAX
            $operatingHours = $this->makeRequest('GET', "/api/v2/machines/{$machineId}/operating-hours");
            $fuelConsumption = $this->makeRequest('GET', "/api/v2/machines/{$machineId}/fuel-consumption");
            $workingModes = $this->makeRequest('GET', "/api/v2/machines/{$machineId}/working-modes");
            $status = $this->makeRequest('GET', "/api/v2/machines/{$machineId}/status");

            $metrics = [];

            // Add operating hours
            if (! empty($operatingHours['operatingHours'])) {
                $metrics[] = [
                    'type' => 'operating_hours',
                    'value' => data_get($operatingHours, 'operatingHours.total') ?? 0,
                    'unit' => 'hours',
                    'timestamp' => $operatingHours['timestamp'] ?? now(),
                ];
            }

            // Add fuel consumption
            if (! empty($fuelConsumption['fuelConsumption'])) {
                $metrics[] = [
                    'type' => 'fuel_consumption',
                    'value' => data_get($fuelConsumption, 'fuelConsumption.total') ?? 0,
                    'unit' => 'liters',
                    'timestamp' => $fuelConsumption['timestamp'] ?? now(),
                ];
            }

            // Add working modes
            if (! empty($workingModes['workingModes'])) {
                $metrics[] = [
                    'type' => 'working_mode',
                    'value' => data_get($workingModes, 'workingModes.current') ?? 'unknown',
                    'timestamp' => $workingModes['timestamp'] ?? now(),
                ];
            }

            // Add machine status
            if (! empty($status['status'])) {
                $metrics[] = [
                    'type' => 'machine_status',
                    'value' => $status['status'],
                    'timestamp' => $status['timestamp'] ?? now(),
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
     * Fetch alerts/cautions for equipment
     */
    /** @return array<string, mixed> */
    public function fetchAlerts(string $machineId): array
    {
        try {
            // KOMTRAX uses 'cautions' instead of 'alerts'
            $response = $this->makeRequest('GET', "/api/v2/machines/{$machineId}/cautions");

            $alerts = [];
            if (! empty($response['cautions'])) {
                $rows23 = data_get($response, 'cautions');
                /** @var list<array<string, mixed>> $rows23 */
                $rows23 = is_array($rows23) ? array_values(array_filter($rows23, 'is_array')) : [];
                foreach ($rows23 as $caution) {
                    $alerts[] = $this->parseAlert($caution);
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
     * Parse equipment data from Komatsu format
     */
    /** @param array<string, mixed> $data
     * @return array<string, mixed> */
    #[\Override]
    protected function parseMachineData(array $data): array
    {
        return [
            'external_id' => $data['id'] ?? $data['asset_id'] ?? null,
            'name' => $data['name'] ?? $data['asset_name'] ?? 'Unknown Equipment',
            'model' => $data['model'] ?? $data['model_name'] ?? 'Unknown Model',
            'manufacturer' => 'Komatsu',
            'status' => $this->parseStatus(is_string($data['status'] ?? null) ? $data['status'] : 'unknown'),
            'location' => $this->parseLocation(is_array($data['position'] ?? null) ? $data['position'] : []),
            'last_heartbeat' => $data['last_heartbeat'] ?? $data['last_update'] ?? null,
            'specifications' => [
                'type' => $data['type'] ?? 'heavy_equipment',
                'model_code' => $data['model_code'] ?? null,
                'serial_number' => $data['serial_number'] ?? null,
                'year_manufactured' => $data['year_manufactured'] ?? null,
                'engine_model' => $data['engine_model'] ?? null,
                'operation_hours' => $data['operation_hours'] ?? null,
            ],
        ];
    }

    /**
     * Parse location data from Komatsu format
     *
     * @return array<string, mixed>
     */
    #[\Override]
    protected function parseLocation(array $data): array
    {
        return [
            'latitude' => $data['latitude'] ?? $data['lat'] ?? 0,
            'longitude' => $data['longitude'] ?? $data['lng'] ?? 0,
            'altitude' => $data['altitude'] ?? $data['elevation'] ?? 0,
            'accuracy' => $data['gps_accuracy'] ?? $data['accuracy'] ?? 0,
            'timestamp' => $data['timestamp'] ?? now()->toIso8601String(),
        ];
    }

    /**
     * Parse alert/notification data from Komatsu format
     */
    #[\Override]
    protected function parseAlert(array $data): array
    {
        return [
            // Missing statuses default to 'active', never the legacy 'new'
            // (not in the alerts.status enum) -- same invariant as the Base
            // normaliser, pinned by ManufacturerAlertsShapeTest.
            'status' => $data['status'] ?? 'active',
            'external_id' => $data['id'] ?? $data['notification_id'] ?? null,
            'type' => $this->mapAlertType($data['type'] ?? $data['category'] ?? 'unknown'),
            'priority' => $this->mapAlertPriority($data['level'] ?? $data['priority'] ?? 'medium'),
            'message' => $data['message'] ?? $data['description'] ?? 'Notification from Komatsu',
            'timestamp' => $data['timestamp'] ?? $data['created_at'] ?? now()->toIso8601String(),
            'acknowledged' => $data['acknowledged'] ?? false,
            'raw_data' => $data,
        ];
    }

    /**
     * Map Komatsu status to standard status
     */
    #[\Override]
    protected function parseStatus(string $status): string
    {
        $statusMap = [
            'online' => 'active',
            'offline' => 'inactive',
            'idle' => 'idle',
            'running' => 'active',
            'operating' => 'active',
            'maintenance' => 'maintenance',
            'standby' => 'idle',
            'error' => 'error',
            'unavailable' => 'stopped',
        ];

        return $statusMap[strtolower($status)] ?? 'unknown';
    }

    /**
     * Fetch machine details from Komatsu API
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

            $location = $result['location'] ?? null;

            return is_array($location) ? $location : null;
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

            $items = $result['alerts'] ?? [];

            return is_array($items) ? array_values(array_filter($items, 'is_array')) : [];
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
