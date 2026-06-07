---
name: oem-integration-patterns
description: >
  Mines platform OEM integration patterns. Use when: adding a new manufacturer integration,
  extending the Bell Equipment sync, implementing ISO 15143-3 telemetry parsing, debugging
  SyncIntegrationMachinesJob, writing integration tests, understanding the BaseManufacturerService
  interface, or working with BellEquipment audit logs.
argument-hint: 'Describe the OEM integration task you need help with'
---

# OEM Integration Patterns

## When to Use

- Adding a new OEM manufacturer integration (Komatsu, CAT, Volvo, etc.)
- Debugging Bell Equipment sync failures
- Writing tests for integration services
- Understanding ISO 15143-3 telemetry data format
- Working with integration audit logs

---

## BaseManufacturerService Interface

All OEM services must implement (or override) these methods:

```php
// app/Services/Integration/BaseManufacturerService.php
abstract class BaseManufacturerService
{
    abstract public function testConnection(): bool;
    abstract public function syncMachines(Integration $integration): Collection;
    abstract public function getMachineStatus(string $externalId): array;

    // Optional overrides:
    public function getHistoricalTelemetry(string $externalId, Carbon $from, Carbon $to): Collection
    {
        return collect(); // default: not supported
    }
}
```

---

## Pattern — New OEM Service

```php
<?php

namespace App\Services\Integration;

use App\Models\Integration;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class NewOemService extends BaseManufacturerService
{
    private string $baseUrl;
    private string $apiKey;

    public function __construct()
    {
        $this->baseUrl = config('integrations.new_oem.base_url');
        $this->apiKey  = config('integrations.new_oem.api_key');
    }

    public function testConnection(): bool
    {
        $response = Http::withToken($this->apiKey)
            ->timeout(10)
            ->get("{$this->baseUrl}/machines?limit=1");
        return $response->successful();
    }

    public function syncMachines(Integration $integration): Collection
    {
        $response = Http::withToken($this->apiKey)
            ->get("{$this->baseUrl}/machines");

        return collect($response->json('data', []))->map(function ($item) use ($integration) {
            return [
                'external_id'         => $item['id'],
                'registration_number' => strtoupper($item['serialNumber']),
                'name'                => $item['name'],
                'model'               => $item['model'],
            ];
        });
    }

    public function getMachineStatus(string $externalId): array
    {
        $response = Http::withToken($this->apiKey)
            ->get("{$this->baseUrl}/machines/{$externalId}/status");
        return $response->json();
    }
}
```

---

## Pattern — Registering the New Service

```php
// app/Services/IntegrationService.php
// Add to the $services map:
private array $services = [
    'bell'    => BellService::class,
    'komatsu' => KomatsuService::class,
    // ...
    'new_oem' => NewOemService::class,  // ← add here
];
```

---

## Bell Audit Log Pattern

```php
// Always log Bell sync operations for debugging
use App\Models\BellIntegrationAuditLog;

BellIntegrationAuditLog::create([
    'event_type' => 'sync_started',   // sync_started|sync_completed|sync_error|token_refreshed
    'status'     => 'success',        // success|error|warning
    'message'    => 'Synced 42 machines',
    'data'       => ['machine_count' => 42],
]);
```

---

## ISO 15143-3 Key Fields

Bell Equipment uses the ISO 15143-3 OEM Data Exchange Standard. Key telemetry fields:

```
EquipmentHeader.OEMName           → manufacturer
EquipmentHeader.Model             → machine model
EquipmentHeader.SerialNumber      → unique ID
Location.Latitude / Longitude     → GPS
CumulativeOperatingHours.Hour     → engine hours
FuelUsed.FuelConsumed             → fuel consumption
LoadCount.Count                   → load cycles
```

---

## Commands Reference

```bash
# Test Bell connectivity
php artisan tinker --execute '
$service = app(App\Services\Integration\BellService::class);
var_dump($service->testConnection());
'

# Run Bell sync
php artisan tinker --execute 'App\Jobs\SyncBellFleetDataJob::dispatchSync();'

# Check audit log
php artisan tinker --execute 'App\Models\BellIntegrationAuditLog::latest()->first();'

# Run integration tests
php artisan test --compact tests/Feature/BellIso15143ServiceTest.php tests/Feature/BellOemIntelligenceTest.php
```
