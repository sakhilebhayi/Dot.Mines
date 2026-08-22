<?php

namespace App\Services\Integration;

use App\Contracts\ManufacturerServiceInterface;
use Exception;

/**
 * Liebherr LiDAT Integration Service
 *
 * API Documentation: https://www.liebherr.com/lidat-api
 * Requires Liebherr customer ID
 */
class LiebherrService extends BaseManufacturerService implements ManufacturerServiceInterface
{
    protected string $manufacturer = 'liebherr';

    #[\Override]
    public function testConnection(): bool
    {
        try {
            $response = $this->makeRequest('GET', '/api/v2/equipment', [
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
            $response = $this->makeRequest('GET', '/api/v2/equipment');

            $machines = [];
            if (! empty($response['data']['equipment'])) {
                $rows26 = data_get($response, 'data.equipment');
                /** @var list<array<string, mixed>> $rows26 */
                $rows26 = is_array($rows26) ? array_values(array_filter($rows26, 'is_array')) : [];
                foreach ($rows26 as $equipment) {
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
            $response = $this->makeRequest('GET', "/api/v2/equipment/{$machineId}/position");

            return [
                'success' => true,
                'location' => $this->parseLocation(is_array($response['data'] ?? null) ? $response['data'] : []),
            ];
        } catch (Exception $e) {
            $this->logError('Failed to fetch position', $e);

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
            $operatingData = $this->makeRequest('GET', "/api/v2/equipment/{$machineId}/operating-data");
            $telemetry = $this->makeRequest('GET', "/api/v2/equipment/{$machineId}/telemetry");
            $serviceIntervals = $this->makeRequest('GET', "/api/v2/equipment/{$machineId}/service-intervals");

            // array_merge() would let whichever source is listed last
            // silently overwrite every field from the earlier ones, since
            // parseMetrics() always returns the same set of keys -- see
            // mergeMetricsPreferNonNull().
            $metrics = $this->mergeMetricsPreferNonNull(
                $this->parseMetrics(is_array($operatingData['data'] ?? null) ? $operatingData['data'] : []),
                $this->parseMetrics(is_array($telemetry['data'] ?? null) ? $telemetry['data'] : []),
                $this->parseMetrics(is_array($serviceIntervals['data'] ?? null) ? $serviceIntervals['data'] : [])
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
            $response = $this->makeRequest('GET', "/api/v2/equipment/{$machineId}/error-codes");

            $alerts = [];
            if (! empty($response['data']['errorCodes'])) {
                $rows27 = data_get($response, 'data.errorCodes');
                /** @var list<array<string, mixed>> $rows27 */
                $rows27 = is_array($rows27) ? array_values(array_filter($rows27, 'is_array')) : [];
                foreach ($rows27 as $error) {
                    $alerts[] = $this->parseAlert($error);
                }
            }

            return [
                'success' => true,
                'alerts' => $alerts,
            ];
        } catch (Exception $e) {
            $this->logError('Failed to fetch error codes', $e);

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
     * Fetch machine details from Liebherr API
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
