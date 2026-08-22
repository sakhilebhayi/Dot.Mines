<?php

namespace App\Services\Integration;

use App\Contracts\ManufacturerServiceInterface;
use Exception;

/**
 * Kubota Diagnostics Integration Service
 *
 * Contact Kubota dealer for API access
 * Requires Kubota dealer ID
 */
class KubotaService extends BaseManufacturerService implements ManufacturerServiceInterface
{
    protected string $manufacturer = 'kubota';

    #[\Override]
    public function testConnection(): bool
    {
        try {
            $response = $this->makeRequest('GET', '/api/v1/machines', [
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
            $response = $this->makeRequest('GET', '/api/v1/machines');

            $machines = [];
            if (! empty($response['data']['machines'])) {
                $rows24 = data_get($response, 'data.machines');
                /** @var list<array<string, mixed>> $rows24 */
                $rows24 = is_array($rows24) ? array_values(array_filter($rows24, 'is_array')) : [];
                foreach ($rows24 as $machine) {
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
     * @return array<string, mixed>
     */
    public function fetchLocation(string $machineId): array
    {
        try {
            $response = $this->makeRequest('GET', "/api/v1/machines/{$machineId}/location");

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
            $telemetry = $this->makeRequest('GET', "/api/v1/machines/{$machineId}/telemetry");
            $diagnostics = $this->makeRequest('GET', "/api/v1/machines/{$machineId}/diagnostics");
            $service = $this->makeRequest('GET', "/api/v1/machines/{$machineId}/service-history");

            // array_merge() would let whichever source is listed last
            // silently overwrite every field from the earlier ones, since
            // parseMetrics() always returns the same set of keys -- see
            // mergeMetricsPreferNonNull().
            $metrics = $this->mergeMetricsPreferNonNull(
                $this->parseMetrics(is_array($telemetry['data'] ?? null) ? $telemetry['data'] : []),
                $this->parseMetrics(is_array($diagnostics['data'] ?? null) ? $diagnostics['data'] : []),
                $this->parseMetrics(is_array($service['data'] ?? null) ? $service['data'] : [])
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
            // Kubota uses diagnostics endpoint for alerts
            $response = $this->makeRequest('GET', "/api/v1/machines/{$machineId}/diagnostics");

            $alerts = [];
            if (! empty($response['data']['alerts'])) {
                $rows25 = data_get($response, 'data.alerts');
                /** @var list<array<string, mixed>> $rows25 */
                $rows25 = is_array($rows25) ? array_values(array_filter($rows25, 'is_array')) : [];
                foreach ($rows25 as $alert) {
                    $alerts[] = $this->parseAlert($alert);
                }
            }

            return [
                'success' => true,
                'alerts' => $alerts,
            ];
        } catch (Exception $e) {
            $this->logError('Failed to fetch diagnostics', $e);

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
     * Fetch machine details from Kubota API
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
