<?php

namespace App\Services\Integration\Adapters;

use App\Contracts\ManufacturerAdapterInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Generic OEM adapter stub.
 *
 * Used for providers that have not yet received a dedicated adapter.
 * It attempts a basic authenticated GET to the configured base_url and
 * returns whatever machines are found in a standard JSON response.
 *
 * Teams using these providers can still add their credentials and test
 * connectivity. Full telemetry sync is added by implementing a dedicated
 * adapter class for that provider and registering it in AdapterRegistry.
 */
class GenericOemAdapter implements ManufacturerAdapterInterface
{
    /**
     * @param  array<string, mixed>  $credentials
     * @return array{success: bool, message: string, machines_found?: int}
     */
    public function testConnection(array $credentials): array
    {
        $baseUrl = trim($credentials['base_url'] ?? $credentials['api_url'] ?? '');

        if (empty($baseUrl)) {
            return ['success' => false, 'message' => 'No API URL provided.'];
        }

        try {
            $request = Http::timeout(15)->withHeaders($this->authHeaders($credentials));
            $response = $request->get($baseUrl);

            if ($response->successful()) {
                $data = $response->json();
                $count = is_array($data) ? count($data) : null;

                $result = ['success' => true, 'message' => 'Connection successful.'];
                if ($count !== null) {
                    $result['machines_found'] = $count;
                }

                return $result;
            }

            return [
                'success' => false,
                'message' => "API returned HTTP {$response->status()}.",
            ];
        } catch (\Throwable $e) {
            Log::warning('GenericOemAdapter::testConnection failed', ['error' => $e->getMessage()]);

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * @param  array<string, mixed>  $credentials
     * @return list<array{
     *   external_id: string, name: string, model: string, manufacturer: string,
     *   serial_number: string, latitude: float|null, longitude: float|null,
     *   engine_running: bool|null, fuel_remaining_percent: float|null,
     *   operating_hours: float|null, load_count: int|null, telemetry_date: string|null
     * }>
     */
    public function fetchFleet(array $credentials): array
    {
        $baseUrl = trim($credentials['base_url'] ?? $credentials['api_url'] ?? '');

        if (empty($baseUrl)) {
            return [];
        }

        try {
            $response = Http::timeout(30)
                ->withHeaders($this->authHeaders($credentials))
                ->get($baseUrl);

            if (! $response->successful()) {
                return [];
            }

            $raw = $response->json();

            if (! is_array($raw)) {
                return [];
            }

            // Normalise: wrap single object in array
            $items = isset($raw[0]) ? $raw : [$raw];

            return array_values(array_map(fn ($item) => [
                'external_id' => (string) ($item['id'] ?? $item['machineId'] ?? $item['equipment_id'] ?? uniqid('ext_')),
                'name' => (string) ($item['name'] ?? $item['machineName'] ?? 'Unknown Machine'),
                'model' => (string) ($item['model'] ?? $item['machineModel'] ?? ''),
                'manufacturer' => (string) ($item['manufacturer'] ?? ''),
                'serial_number' => (string) ($item['serialNumber'] ?? $item['serial'] ?? ''),
                'latitude' => isset($item['latitude']) ? (float) $item['latitude'] : null,
                'longitude' => isset($item['longitude']) ? (float) $item['longitude'] : null,
                'engine_running' => isset($item['engineRunning']) ? (bool) $item['engineRunning'] : null,
                'fuel_remaining_percent' => isset($item['fuelLevel']) ? (float) $item['fuelLevel'] : null,
                'operating_hours' => isset($item['operatingHours']) ? (float) $item['operatingHours'] : null,
                'load_count' => isset($item['loadCount']) ? (int) $item['loadCount'] : null,
                'telemetry_date' => $item['telemetryDate'] ?? $item['lastUpdate'] ?? null,
            ], $items));
        } catch (\Throwable $e) {
            Log::warning('GenericOemAdapter::fetchFleet failed', ['error' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * @param  array<string, mixed>  $credentials
     * @return list<array{signal: string, value: mixed, recorded_at: string}>
     */
    public function fetchHistory(array $credentials, string $externalId, string $from, string $to): array
    {
        // Generic adapters do not implement historical telemetry by default.
        return [];
    }

    /** @return list<array{key: string, label: string, type: string, required: bool, hint?: string, placeholder?: string}> */
    public function credentialSchema(): array
    {
        return [
            ['key' => 'api_url',    'label' => 'API Base URL', 'type' => 'url',      'required' => true,  'placeholder' => 'https://api.yourprovider.com'],
            ['key' => 'api_key',    'label' => 'API Key',      'type' => 'password', 'required' => true],
            ['key' => 'api_secret', 'label' => 'API Secret',   'type' => 'password', 'required' => false, 'hint' => 'Leave blank if not required'],
        ];
    }

    public function displayName(): string
    {
        return 'OEM Integration';
    }

    public function icon(): string
    {
        return '📦';
    }

    /**
     * Build auth headers from credentials. Supports Bearer token and Basic auth.
     *
     * @param  array<string, mixed>  $credentials
     * @return array<string, string>
     */
    private function authHeaders(array $credentials): array
    {
        $apiKey = $credentials['api_key'] ?? '';
        $apiSecret = $credentials['api_secret'] ?? '';

        if (! empty($apiKey) && ! empty($apiSecret)) {
            return ['Authorization' => 'Basic '.base64_encode("{$apiKey}:{$apiSecret}")];
        }

        if (! empty($apiKey)) {
            return ['Authorization' => "Bearer {$apiKey}"];
        }

        return [];
    }
}
