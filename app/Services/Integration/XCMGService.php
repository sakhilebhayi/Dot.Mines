<?php

namespace App\Services\Integration;

use App\Contracts\ManufacturerServiceInterface;
use Exception;

/**
 * XCMG Xrea (XCMG Remote Expert Assistant) Integration Service
 *
 * Contact XCMG for API access
 * Requires XCMG company ID
 */
class XCMGService extends BaseManufacturerService implements ManufacturerServiceInterface
{
    protected string $manufacturer = 'xcmg';

    #[\Override]
    public function testConnection(): bool
    {
        try {
            $response = $this->makeRequest('GET', '/iot/v1/devices', [
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
            $response = $this->makeRequest('GET', '/iot/v1/devices');

            $machines = [];
            if (! empty($response['data']['devices'])) {
                $rows36 = data_get($response, 'data.devices');
                /** @var list<array<string, mixed>> $rows36 */
                $rows36 = is_array($rows36) ? array_values(array_filter($rows36, 'is_array')) : [];
                foreach ($rows36 as $device) {
                    $machines[] = $this->parseMachineData($device);
                }
            }

            return [
                'success' => true,
                'machines' => $machines,
                'count' => count($machines),
            ];
        } catch (Exception $e) {
            $this->logError('Failed to fetch devices', $e);

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
            $response = $this->makeRequest('GET', "/iot/v1/devices/{$machineId}/location");

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
            $status = $this->makeRequest('GET', "/iot/v1/devices/{$machineId}/status");
            $parameters = $this->makeRequest('GET', "/iot/v1/devices/{$machineId}/parameters");
            $workData = $this->makeRequest('GET', "/iot/v1/devices/{$machineId}/work-data");

            // array_merge() would let whichever source is listed last
            // silently overwrite every field from the earlier ones, since
            // parseMetrics() always returns the same set of keys -- see
            // mergeMetricsPreferNonNull().
            $metrics = $this->mergeMetricsPreferNonNull(
                $this->parseMetrics(is_array($status['data'] ?? null) ? $status['data'] : []),
                $this->parseMetrics(is_array($parameters['data'] ?? null) ? $parameters['data'] : []),
                $this->parseMetrics(is_array($workData['data'] ?? null) ? $workData['data'] : [])
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
            $response = $this->makeRequest('GET', "/iot/v1/devices/{$machineId}/faults");

            $alerts = [];
            if (! empty($response['data']['faults'])) {
                $rows37 = data_get($response, 'data.faults');
                /** @var list<array<string, mixed>> $rows37 */
                $rows37 = is_array($rows37) ? array_values(array_filter($rows37, 'is_array')) : [];
                foreach ($rows37 as $fault) {
                    $alerts[] = $this->parseAlert($fault);
                }
            }

            return [
                'success' => true,
                'alerts' => $alerts,
            ];
        } catch (Exception $e) {
            $this->logError('Failed to fetch faults', $e);

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
     * Fetch machine details from XCMG API
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
