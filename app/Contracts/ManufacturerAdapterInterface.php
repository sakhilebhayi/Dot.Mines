<?php

namespace App\Contracts;

/**
 * Contract for all OEM manufacturer adapters.
 *
 * Unlike the legacy ManufacturerServiceInterface (which receives credentials
 * via constructor injection), adapters are stateless singletons: credentials
 * are passed on each call, sourced at runtime from the decrypted
 * integrations.credentials column.
 */
interface ManufacturerAdapterInterface
{
    /**
     * Verify the credentials are valid and the API is reachable.
     *
     * @param  array<string, mixed>  $credentials
     * @return array{success: bool, message: string, machines_found?: int}
     */
    public function testConnection(array $credentials): array;

    /**
     * Fetch the current fleet snapshot.
     *
     * @param  array<string, mixed>  $credentials
     * @return list<array{
     *   external_id: string,
     *   name: string,
     *   model: string,
     *   manufacturer: string,
     *   serial_number: string,
     *   latitude: float|null,
     *   longitude: float|null,
     *   engine_running: bool|null,
     *   fuel_remaining_percent: float|null,
     *   operating_hours: float|null,
     *   load_count: int|null,
     *   telemetry_date: string|null,
     * }>
     */
    public function fetchFleet(array $credentials): array;

    /**
     * Fetch historical telemetry for one machine over a UTC date range.
     *
     * @param  array<string, mixed>  $credentials
     * @return list<array{signal: string, value: mixed, recorded_at: string}>
     */
    public function fetchHistory(array $credentials, string $externalId, string $from, string $to): array;

    /**
     * Return the credential fields required by this adapter.
     * Drives the dynamic form on the Integrations page.
     *
     * @return list<array{key: string, label: string, type: 'text'|'password'|'url', required: bool, hint?: string, placeholder?: string}>
     */
    public function credentialSchema(): array;

    /**
     * Human-readable display name shown in the UI.
     */
    public function displayName(): string;

    /**
     * Emoji or short icon string shown in the manufacturer grid.
     */
    public function icon(): string;
}
