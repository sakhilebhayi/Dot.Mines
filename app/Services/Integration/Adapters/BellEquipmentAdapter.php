<?php

namespace App\Services\Integration\Adapters;

use App\Contracts\ManufacturerAdapterInterface;
use App\Models\BellEquipmentCurrentStatus;
use App\Services\Integration\BellHistoricalTelemetryService;
use App\Services\Integration\BellIso15143Service;

/**
 * Bell Equipment adapter.
 *
 * Wraps the existing BellIso15143Service and BellHistoricalTelemetryService,
 * routing credentials from the integrations table rather than .env.
 */
class BellEquipmentAdapter implements ManufacturerAdapterInterface
{
    /**
     * @param  array<string, mixed>  $credentials
     * @return array{success: bool, message: string, machines_found?: int}
     */
    public function testConnection(array $credentials): array
    {
        // If fresh data already exists (synced within the last 10 minutes) treat the
        // connection as valid without hitting the Bell API again. This prevents 405
        // rate-limit errors when testConnection() and fetchFleet() run back-to-back
        // under QUEUE_CONNECTION=sync.
        $freshCutoff = now()->subMinutes(10);
        $existingCount = BellEquipmentCurrentStatus::where('updated_date', '>=', $freshCutoff)->count();

        if ($existingCount > 0) {
            return [
                'success' => true,
                'message' => "Connected. {$existingCount} machine(s) in live snapshot.",
                'machines_found' => $existingCount,
            ];
        }

        // No fresh data — perform a real API sync
        try {
            $service = $this->makeService($credentials);
            $result = $service->sync();

            if ($result['success'] || $result['processed'] > 0) {
                return [
                    'success' => true,
                    'message' => "Connected. {$result['processed']} machine(s) found.",
                    'machines_found' => $result['processed'],
                ];
            }

            return [
                'success' => false,
                'message' => $result['error'] ?? 'Connection failed — no machines returned.',
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
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
        // Check if there is already fresh data in bell_equipment_current_status
        // (within the last 10 minutes). This avoids a redundant API call when
        // TestIntegrationConnectionJob already synced moments earlier — which
        // would trigger Bell API rate-limiting on the second call.
        $freshCutoff = now()->subMinutes(10);
        $hasFreshData = BellEquipmentCurrentStatus::where('updated_date', '>=', $freshCutoff)->exists();

        if (! $hasFreshData) {
            // Data is stale or missing — fetch from Bell API
            $service = $this->makeService($credentials);
            $service->sync();
            // Continue regardless of sync outcome; return whatever is in the table
        }

        // Read from bell_equipment_current_status — always return existing data
        // even when the API call above failed, so previous snapshots are not lost.
        $rows = BellEquipmentCurrentStatus::with('equipment')
            ->get()
            ->filter(fn ($status) => $status->equipment !== null);

        if ($rows->isEmpty()) {
            return [];
        }

        return array_values($rows
            ->map(function ($status) {
                $eq = $status->equipment;

                // Human-readable name: "Bell B50E #9112" (last 4 of serial)
                $serial = $eq?->serial_number ?? '';
                $suffix = $serial ? ' #'.substr($serial, -4) : '';
                $name = 'Bell '.($eq?->model ?? 'Equipment').$suffix;

                return [
                    'external_id' => trim($eq?->equipment_id ?? ''),
                    'name' => $name,
                    'model' => $eq?->model ?? '',
                    'machine_type' => str_starts_with(strtolower($eq?->model ?? ''), 'g') ? 'grader' : 'articulated_hauler',
                    'manufacturer' => 'Bell Equipment',
                    'serial_number' => $serial,
                    'latitude' => $status->latitude ? (float) $status->latitude : null,
                    'longitude' => $status->longitude ? (float) $status->longitude : null,
                    'engine_running' => (bool) $status->engine_running,
                    'fuel_remaining_percent' => $status->fuel_remaining_percent !== null ? (float) $status->fuel_remaining_percent : null,
                    'operating_hours' => $status->operating_hours !== null ? (float) $status->operating_hours : null,
                    'load_count' => $status->load_count !== null ? (int) $status->load_count : null,
                    'telemetry_date' => $status->last_telemetry_date,
                    // Passed through so SyncIntegrationJob can link bell_equipment.machine_id
                    '_bell_equipment_key' => $eq?->equipment_key,
                ];
            })
            ->filter(fn ($item) => $item['external_id'] !== '')  // skip equipment with no ID
            ->values()
            ->all());
    }

    /**
     * @param  array<string, mixed>  $credentials
     * @return list<array{signal: string, value: mixed, recorded_at: string}>
     */
    public function fetchHistory(array $credentials, string $externalId, string $from, string $to): array
    {
        // Delegate to BellHistoricalTelemetryService for a single machine
        $service = new BellHistoricalTelemetryService(
            $credentials['base_url'] ?? config('integrations.bell_historical.base_url'),
            $credentials['username'] ?? $credentials['api_key'] ?? '',
            $credentials['password'] ?? $credentials['api_secret'] ?? '',
            $credentials['sso_token_url'] ?? config('integrations.bell_sso.token_url'),
            $credentials['client_id'] ?? config('integrations.bell_sso.client_id'),
            $credentials['client_secret'] ?? config('integrations.bell_sso.client_secret'),
            $credentials['scope'] ?? 'ISO_Exports',
        );

        $service->syncRange($from, $to);

        return []; // Records written directly to bell_* tables
    }

    /** @return list<array{key: string, label: string, type: string, required: bool, hint?: string, placeholder?: string}> */
    public function credentialSchema(): array
    {
        return [
            ['key' => 'api_url',       'label' => 'Fleet API URL',        'type' => 'url',      'required' => true,  'placeholder' => 'https://b-fleet03.bellequipment.com:8080/Fleet'],
            ['key' => 'username',      'label' => 'Username',             'type' => 'text',     'required' => true,  'placeholder' => 'yourname@bell.co.za'],
            ['key' => 'password',      'label' => 'Password',             'type' => 'password', 'required' => true],
            ['key' => 'client_id',     'label' => 'SSO Client ID',        'type' => 'text',     'required' => true,  'placeholder' => 'ISO_Export_Service'],
            ['key' => 'client_secret', 'label' => 'SSO Client Secret',    'type' => 'password', 'required' => true],
            ['key' => 'sso_token_url', 'label' => 'SSO Token URL',        'type' => 'url',      'required' => false, 'placeholder' => 'https://sso.bellequipment.com/connect/token'],
        ];
    }

    public function displayName(): string
    {
        return 'Bell Equipment';
    }

    public function icon(): string
    {
        return '🔔';
    }

    /** @param array<string, mixed> $credentials */
    private function makeService(array $credentials): BellIso15143Service
    {
        return new BellIso15143Service(
            $credentials['api_url'] ?? config('integrations.bell_iso15143.api_url'),
            $credentials['username'] ?? config('integrations.bell_iso15143.api_username'),
            $credentials['password'] ?? config('integrations.bell_iso15143.api_password'),
            $credentials['sso_token_url'] ?? config('integrations.bell_sso.token_url'),
            $credentials['client_id'] ?? config('integrations.bell_sso.client_id'),
            $credentials['client_secret'] ?? config('integrations.bell_sso.client_secret'),
            $credentials['scope'] ?? config('integrations.bell_sso.scope', 'ISO_Exports'),
        );
    }
}
