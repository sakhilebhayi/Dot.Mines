<?php

namespace App\Services\Integration;

use Exception;

/**
 * Volvo CareTrack Integration Service
 *
 * Handles integration with Volvo CareTrack API (api.volvoce.com)
 * Uses OAuth 2.0 Client Credentials for authentication
 * Documentation: https://developer.volvoce.com/caretrack-api
 */
class VolvoService extends BaseManufacturerService
{
    /**
     * Manufacturer identifier
     */
    protected string $manufacturer = 'volvo';

    /**
     * Test connection to Volvo CareTrack API
     */
    #[\Override]
    public function testConnection(): bool
    {
        try {
            // Test with machines endpoint
            $response = $this->makeRequest('GET', '/connected-machines/v1/machines');

            return ! empty($response) && $response['success'] !== false;
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();

            return false;
        }
    }

    /**
     * Fetch machines from Volvo CareTrack API
     */
    /** @return array<string, mixed> */
    #[\Override]
    public function fetchMachines(): array
    {
        try {
            $response = $this->makeRequest('GET', '/connected-machines/v1/machines');

            $machines = [];
            if (! empty($response['data'])) {
                $rows32 = self::rowsOf(data_get($response, 'data'));
                foreach ($rows32 as $machine) {
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
            $response = $this->makeRequest('GET', "/connected-machines/v1/machines/{$machineId}/location");

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
     * Fetch telemetry/metrics for equipment
     */
    /** @return array<string, mixed> */
    public function fetchMetrics(string $machineId): array
    {
        try {
            // Fetch multiple metric types
            $telemetry = $this->makeRequest('GET', "/connected-machines/v1/machines/{$machineId}/telemetry");
            $health = $this->makeRequest('GET', "/connected-machines/v1/machines/{$machineId}/health");
            $utilization = $this->makeRequest('GET', "/connected-machines/v1/machines/{$machineId}/utilization");
            $fuel = $this->makeRequest('GET', "/connected-machines/v1/machines/{$machineId}/fuel");

            $metrics = [];

            // Parse telemetry data
            if (! empty($telemetry['data'])) {
                $rows33 = self::rowsOf(data_get($telemetry, 'data'));
                foreach ($rows33 as $metric) {
                    $metrics[] = $this->parseMetric($metric);
                }
            }

            // Add health metrics
            if (! empty($health['data'])) {
                $metrics[] = [
                    'type' => 'health_status',
                    'value' => data_get($health, 'data.status') ?? 'unknown',
                    'timestamp' => data_get($health, 'data.timestamp') ?? now(),
                ];
            }

            // Add utilization metrics
            if (! empty($utilization['data'])) {
                $metrics[] = [
                    'type' => 'utilization',
                    'value' => data_get($utilization, 'data.percentage') ?? 0,
                    'timestamp' => data_get($utilization, 'data.timestamp') ?? now(),
                ];
            }

            // Add fuel metrics
            if (! empty($fuel['data'])) {
                $metrics[] = [
                    'type' => 'fuel_level',
                    'value' => data_get($fuel, 'data.level') ?? 0,
                    'unit' => data_get($fuel, 'data.unit') ?? 'liters',
                    'timestamp' => data_get($fuel, 'data.timestamp') ?? now(),
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
     * Fetch alerts/faults for equipment
     */
    /** @return array<string, mixed> */
    public function fetchAlerts(string $machineId): array
    {
        try {
            // CareTrack includes alerts in health endpoint
            $response = $this->makeRequest('GET', "/connected-machines/v1/machines/{$machineId}/health");

            $alerts = [];
            if (! empty($response['data']['alerts'])) {
                $rows34 = self::rowsOf(data_get($response, 'data.alerts'));
                foreach ($rows34 as $alert) {
                    $alerts[] = $this->parseAlert($alert);
                }
            }

            // Also check for faults/warnings
            if (! empty($response['data']['faults'])) {
                $rows35 = self::rowsOf(data_get($response, 'data.faults'));
                foreach ($rows35 as $fault) {
                    $alerts[] = $this->parseAlert($fault);
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
     * Parse equipment data from Volvo format
     */
    /** @param array<string, mixed> $data
     * @return array<string, mixed> */
    #[\Override]
    protected function parseMachineData(array $data): array
    {
        return [
            'external_id' => $data['id'] ?? $data['equipment_id'] ?? null,
            'name' => $data['name'] ?? $data['equipment_name'] ?? 'Unknown Equipment',
            'model' => $data['model'] ?? $data['model_name'] ?? 'Unknown Model',
            'manufacturer' => 'Volvo',
            'status' => $this->parseStatus(is_string($data['status'] ?? null) ? $data['status'] : 'unknown'),
            'location' => $this->parseLocation(self::payloadArray($data['position'] ?? null)),
            'last_heartbeat' => $data['last_update'] ?? $data['last_heartbeat'] ?? null,
            'specifications' => [
                'type' => $data['type'] ?? 'heavy_equipment',
                'model_code' => $data['model_code'] ?? null,
                'serial_number' => $data['serial_number'] ?? $data['serialNumber'] ?? null,
                'year_manufactured' => $data['year'] ?? $data['manufacture_year'] ?? null,
                'engine_power' => $data['engine_power'] ?? null,
                'weight' => $data['weight'] ?? null,
            ],
        ];
    }

    /**
     * Parse location data from Volvo format
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
     * Parse diagnostic/metric data from Volvo format
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function parseMetric(array $data): array
    {
        return [
            'type' => $data['parameter_name'] ?? $data['type'] ?? 'unknown',
            'value' => $data['value'] ?? $data['reading'] ?? 0,
            'unit' => $data['unit'] ?? $data['measurement_unit'] ?? '',
            'timestamp' => $data['timestamp'] ?? now()->toIso8601String(),
            'tags' => [
                'parameter_id' => $data['parameter_id'] ?? null,
                'component' => $data['component'] ?? null,
            ],
        ];
    }

    /**
     * Parse alert/fault data from Volvo format
     */
    #[\Override]
    protected function parseAlert(array $data): array
    {
        return [
            // Missing statuses default to 'active', never the legacy 'new'
            // (not in the alerts.status enum) -- same invariant as the Base
            // normaliser, pinned by ManufacturerAlertsShapeTest.
            'status' => $data['status'] ?? 'active',
            'external_id' => $data['id'] ?? $data['fault_id'] ?? null,
            'type' => $this->mapAlertType($data['fault_code'] ?? $data['type'] ?? 'unknown'),
            'priority' => $this->mapAlertPriority($data['priority'] ?? $data['severity'] ?? 'medium'),
            'message' => $data['description'] ?? $data['message'] ?? 'Fault detected',
            'timestamp' => $data['timestamp'] ?? $data['fault_time'] ?? now()->toIso8601String(),
            'acknowledged' => $data['acknowledged'] ?? false,
            'raw_data' => $data,
        ];
    }

    /**
     * Map Volvo equipment status to standard status
     */
    #[\Override]
    protected function parseStatus(string $status): string
    {
        $statusMap = [
            'online' => 'active',
            'offline' => 'inactive',
            'idle' => 'idle',
            'operating' => 'active',
            'in_operation' => 'active',
            'working' => 'active',
            'maintenance' => 'maintenance',
            'in_maintenance' => 'maintenance',
            'disabled' => 'stopped',
            'fault' => 'error',
            'error' => 'error',
        ];

        return $statusMap[strtolower($status)] ?? 'unknown';
    }

    /**
     * Fetch machine details from Volvo API
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
