<?php

namespace App\Services\Integration;

use App\Contracts\ManufacturerServiceInterface;
use Exception;

/**
 * Hyundai Hi-MATE Integration Service
 *
 * API Documentation: https://www.hi-mate.com/api-documentation
 * Requires Hyundai dealer code
 */
class HyundaiService extends BaseManufacturerService implements ManufacturerServiceInterface
{
    protected string $manufacturer = 'hyundai';

    #[\Override]
    public function testConnection(): bool
    {
        try {
            $response = $this->makeRequest('GET', '/api/v1/equipment', [
                'query' => ['limit' => 1],
            ]);

            return ! empty($response) && $response['success'] !== false;
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();

            return false;
        }
    }

    /** @return array<string, mixed> */
    #[\Override]
    public function fetchMachines(): array
    {
        try {
            $response = $this->makeRequest('GET', '/api/v1/equipment');

            $machines = [];
            if (! empty($response['data']['equipment'])) {
                $rows13 = data_get($response, 'data.equipment');
                /** @var list<array<string, mixed>> $rows13 */
                $rows13 = is_array($rows13) ? array_values(array_filter($rows13, 'is_array')) : [];
                foreach ($rows13 as $equipment) {
                    $machines[] = $this->parseMachineData($equipment);
                }
            }

            return [
                'success' => true,
                'machines' => $machines,
                'count' => count($machines),
            ];
        } catch (Exception $e) {
            $this->logError('Failed to fetch equipment', $e);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'machines' => [],
            ];
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function fetchLocation(string $machineId): array
    {
        try {
            $response = $this->makeRequest('GET', "/api/v1/equipment/{$machineId}/location");

            return [
                'success' => true,
                'location' => $this->parseLocation(is_array($response['data'] ?? null) ? $response['data'] : []),
            ];
        } catch (Exception $e) {
            $this->logError('Failed to fetch location', $e);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /** @return array<string, mixed> */
    public function fetchMetrics(string $machineId): array
    {
        try {
            $workingInfo = $this->makeRequest('GET', "/api/v1/equipment/{$machineId}/working-info");
            $engineData = $this->makeRequest('GET', "/api/v1/equipment/{$machineId}/engine-data");
            $serviceInfo = $this->makeRequest('GET', "/api/v1/equipment/{$machineId}/service-info");

            // array_merge() would let whichever source is listed last
            // silently overwrite every field from the earlier ones, since
            // parseMetrics() always returns the same set of keys -- see
            // mergeMetricsPreferNonNull().
            $metrics = $this->mergeMetricsPreferNonNull(
                $this->parseMetrics(is_array($workingInfo['data'] ?? null) ? $workingInfo['data'] : []),
                $this->parseMetrics(is_array($engineData['data'] ?? null) ? $engineData['data'] : []),
                $this->parseMetrics(is_array($serviceInfo['data'] ?? null) ? $serviceInfo['data'] : [])
            );

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

    /** @return array<string, mixed> */
    public function fetchAlerts(string $machineId): array
    {
        try {
            $response = $this->makeRequest('GET', "/api/v1/equipment/{$machineId}/notifications");

            $alerts = [];
            if (! empty($response['data']['notifications'])) {
                $rows14 = data_get($response, 'data.notifications');
                /** @var list<array<string, mixed>> $rows14 */
                $rows14 = is_array($rows14) ? array_values(array_filter($rows14, 'is_array')) : [];
                foreach ($rows14 as $notification) {
                    $alerts[] = $this->parseAlert($notification);
                }
            }

            return [
                'success' => true,
                'alerts' => $alerts,
            ];
        } catch (Exception $e) {
            $this->logError('Failed to fetch notifications', $e);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'alerts' => [],
            ];
        }
    }

    #[\Override]
    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    /**
     * Fetch machine details from Hyundai API
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

            $metrics = $result['metrics'] ?? [];

            return is_array($metrics) ? $metrics : [];
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
}
