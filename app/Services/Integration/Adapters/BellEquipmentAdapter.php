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
        $service = $this->makeService($credentials);
        $result = $service->sync();

        // Pull normalised data from the stored fleet snapshot
        if (! $result['success'] && $result['processed'] === 0) {
            return [];
        }

        // Re-read from the bell_equipment_current_status table to return
        // a normalised fleet list scoped to this credential set.
        return array_values(BellEquipmentCurrentStatus::with('equipment')
            ->get()
            ->map(function ($status) {
                $eq = $status->equipment;

                return [
                    'external_id' => $eq?->equipment_id ?? '',
                    'name' => ($eq?->oem_name ?? '').' '.($eq?->model ?? ''),
                    'model' => $eq?->model ?? '',
                    'manufacturer' => 'Bell Equipment',
                    'serial_number' => $eq?->serial_number ?? '',
                    'latitude' => $status->latitude ? (float) $status->latitude : null,
                    'longitude' => $status->longitude ? (float) $status->longitude : null,
                    'engine_running' => (bool) $status->engine_running,
                    'fuel_remaining_percent' => $status->fuel_remaining_percent !== null ? (float) $status->fuel_remaining_percent : null,
                    'operating_hours' => $status->operating_hours !== null ? (float) $status->operating_hours : null,
                    'load_count' => $status->load_count !== null ? (int) $status->load_count : null,
                    'telemetry_date' => $status->last_telemetry_date,
                ];
            })
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
