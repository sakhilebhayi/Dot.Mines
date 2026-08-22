<?php

namespace App\Services\Integration;

use Exception;

/**
 * C-Track Fleet Management Integration Service
 *
 * API Documentation: https://www.ctrack.com/api-documentation
 * GPS tracking and fleet management system
 */
class CTrackService extends BaseManufacturerService
{
    /**
     * Manufacturer identifier
     */
    protected string $manufacturer = 'ctrack';

    /**
     * Test connection to C-Track API
     */
    #[\Override]
    public function testConnection(): bool
    {
        try {
            $response = $this->makeRequest('GET', '/v3/vehicles', [
                'query' => ['limit' => 1],
            ]);

            return ! empty($response) && $response['success'] !== false;
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();

            return false;
        }
    }

    /**
     * Fetch vehicles from C-Track API
     */
    /** @return array<string, mixed> */
    #[\Override]
    public function fetchMachines(): array
    {
        try {
            $response = $this->makeRequest('GET', '/v3/vehicles');

            $machines = [];
            if (! empty($response['data']['vehicles'])) {
                foreach ($response['data']['vehicles'] as $vehicle) {
                    $machines[] = $this->parseMachineData($vehicle);
                }
            }

            return [
                'success' => true,
                'machines' => $machines,
                'count' => count($machines),
            ];
        } catch (Exception $e) {
            $this->logError('Failed to fetch vehicles', $e);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'machines' => [],
            ];
        }
    }

    /**
     * Fetch current location for vehicle
     */
    #[\Override]
    public function fetchMachineLocation(string $machineId): ?array
    {
        try {
            $response = $this->makeRequest('GET', "/v3/vehicles/{$machineId}/location");

            return $this->parseLocation($response['data'] ?? []);
        } catch (Exception $e) {
            $this->logError('Failed to fetch location', $e);

            return null;
        }
    }

    /**
     * Fetch tracking metrics and history for vehicle
     */
    #[\Override]
    public function fetchMachineMetrics(string $machineId): array
    {
        try {
            $history = $this->makeRequest('GET', "/v3/vehicles/{$machineId}/history");
            $events = $this->makeRequest('GET', "/v3/vehicles/{$machineId}/events");

            // array_merge() would let whichever source is listed last
            // silently overwrite every field from the earlier one, since
            // parseMetrics() always returns the same set of keys -- see
            // mergeMetricsPreferNonNull().
            $metrics = $this->mergeMetricsPreferNonNull(
                $this->parseMetrics($history['data'] ?? []),
                $this->parseMetrics($events['data'] ?? [])
            );

            return $metrics;
        } catch (Exception $e) {
            $this->logError('Failed to fetch metrics', $e);

            return [];
        }
    }

    /**
     * Fetch geofence violations and alerts
     *
     * @return list<array<string, mixed>>
     */
    #[\Override]
    public function fetchMachineAlerts(string $machineId): array
    {
        try {
            $response = $this->makeRequest('GET', "/v3/vehicles/{$machineId}/events", [
                'query' => ['type' => 'alert'],
            ]);

            $alerts = [];
            if (! empty($response['data']['events'])) {
                foreach ($response['data']['events'] as $event) {
                    if (($event['type'] ?? '') === 'alert') {
                        $alerts[] = $this->parseAlert($event);
                    }
                }
            }

            return $alerts;
        } catch (Exception $e) {
            $this->logError('Failed to fetch alerts', $e);

            return [];
        }
    }

    /**
     * Fetch machine details from C-Track API
     */
    /** @return array<string, mixed> */
    #[\Override]
    public function fetchMachineDetails(string $machineId): array
    {
        try {
            return $this->makeRequest('GET', "/v3/vehicles/{$machineId}");
        } catch (Exception $e) {
            $this->logError('Failed to fetch machine details', $e);

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

    /**
     * Parse vehicle data from C-Track format
     */
    /** @param array<string, mixed> $data
     * @return array<string, mixed> */
    #[\Override]
    protected function parseMachineData(array $data): array
    {
        return [
            'external_id' => $data['id'] ?? $data['vehicle_id'] ?? null,
            'name' => $data['name'] ?? $data['plate'] ?? 'Unknown Vehicle',
            'model' => $data['model'] ?? $data['vehicle_type'] ?? 'Unknown Model',
            'manufacturer' => 'C-Track',
            'status' => $this->parseStatus($data['status'] ?? 'unknown'),
            'location' => $this->parseLocation($data['position'] ?? []),
            'last_heartbeat' => $data['last_gps'] ?? $data['last_update'] ?? null,
            'specifications' => [
                'type' => 'gps_tracker',
                'vehicle_type' => $data['vehicle_type'] ?? null,
                'plate_number' => $data['plate'] ?? null,
                'vin' => $data['vin'] ?? null,
                'make' => $data['make'] ?? null,
                'year' => $data['year'] ?? null,
                'imei' => $data['imei'] ?? null,
            ],
        ];
    }

    /**
     * Parse location data from C-Track format
     *
     * @return array<string, mixed>
     */
    #[\Override]
    protected function parseLocation(array $data): array
    {
        return [
            'latitude' => $data['latitude'] ?? $data['lat'] ?? 0,
            'longitude' => $data['longitude'] ?? $data['lng'] ?? 0,
            'altitude' => $data['altitude'] ?? 0,
            'accuracy' => $data['accuracy'] ?? 0,
            'speed' => $data['speed'] ?? 0,
            'bearing' => $data['bearing'] ?? $data['heading'] ?? 0,
            'timestamp' => $data['timestamp'] ?? now()->toIso8601String(),
        ];
    }

    /**
     * Parse event/alert data from C-Track format
     */
    #[\Override]
    protected function parseAlert(array $data): array
    {
        return [
            // Missing statuses default to 'active', never the legacy 'new'
            // (not in the alerts.status enum) -- same invariant as the Base
            // normaliser, pinned by ManufacturerAlertsShapeTest.
            'status' => $data['status'] ?? 'active',
            'external_id' => $data['id'] ?? $data['event_id'] ?? null,
            'type' => $this->mapAlertType($data['event_type'] ?? $data['type'] ?? 'unknown'),
            'priority' => $this->mapAlertPriority($data['severity'] ?? $data['priority'] ?? 'medium'),
            'message' => $data['description'] ?? $data['message'] ?? 'Event from C-Track',
            'timestamp' => $data['timestamp'] ?? $data['created_at'] ?? now()->toIso8601String(),
            'acknowledged' => $data['acknowledged'] ?? false,
            'raw_data' => $data,
        ];
    }

    /**
     * Map C-Track vehicle status to standard status
     */
    #[\Override]
    protected function parseStatus(string $status): string
    {
        $statusMap = [
            'online' => 'active',
            'offline' => 'inactive',
            'moving' => 'active',
            'stationary' => 'idle',
            'idle' => 'idle',
            'parked' => 'idle',
            'maintenance' => 'maintenance',
            'service' => 'maintenance',
            'inactive' => 'inactive',
            'gps_lost' => 'error',
            'error' => 'error',
        ];

        return $statusMap[strtolower($status)] ?? 'unknown';
    }
}
