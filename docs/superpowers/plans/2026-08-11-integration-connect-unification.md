# Integration Connect Unification Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the two-click, shallow-boolean "Create Integration then Test Connection" flow with a single "Connect" action that runs a real, honest, multi-point pipeline check (auth, fetch, store, tenant association, sync dispatch), derives which data streams (fleet / telemetry / production / location) the connected account actually provides from the real data it returns, and surfaces that per-stream status to the user — without fabricating capabilities the API hasn't actually demonstrated.

**Architecture:** One new `IntegrationService::connect()` orchestration method reuses the existing `getServiceForIntegration()`, `fetchMachines()`, and machine-persisting code paths (refactored out of `syncMachines()` into a shared `persistMachines()` so the deep check and the ongoing sync never re-fetch the same data twice). Capabilities are derived by inspecting the shape of the first real machine record returned — never hardcoded per provider. Two new nullable JSON columns on `integrations` (`capabilities`, `sync_streams`) persist what was actually found. The existing `createIntegration()` and `testConnection()` methods are left completely untouched so every existing test in `tests/Feature/IntegrationManagerConnectionTest.php` and `tests/Unit/IntegrationServiceTest.php` keeps passing unchanged.

**Tech Stack:** Laravel 12, PHP 8.5, Livewire 3, PostgreSQL, PHPUnit.

## Global Constraints

- Do not modify, rename, or remove `IntegrationManager::createIntegration()`, `IntegrationManager::testConnection()`, or `IntegrationService::testConnection()` — their existing return shapes and side effects are asserted on by name in `tests/Feature/IntegrationManagerConnectionTest.php` and `tests/Unit/IntegrationServiceTest.php`.
- `IntegrationService::testConnection()`'s `success` key must continue to mean "the manufacturer service's own `testConnection()` call returned true" and nothing more — do not fold the deep pipeline into it.
- Never fabricate a capability (`fleet`/`telemetry`/`production`/`location`) that wasn't derived from a real field present in a real API response. Zero machines returned is a legitimate "connected, nothing synced yet" outcome, not a failure.
- `Integration::$hidden` already excludes `api_key`/`api_secret`/`webhook_secret` from array/JSON serialization — any new code touching `Integration::toArray()` or building arrays for the Livewire view must not reintroduce a leak of `credentials`.
- Run `vendor/bin/pint --dirty --format agent` after every task that touches a `.php` file, before moving to the next task.
- The `unique(['team_id', 'provider'])` DB constraint on `integrations` already prevents a second connection per provider per team — do not add application-level duplicate-checking that could race with or contradict it.

---

## File Structure

- **Create:** `database/migrations/2026_08_11_000001_add_capability_columns_to_integrations_table.php` — the two new columns.
- **Modify:** `app/Models/Integration.php` — casts + `hasCapability()`/`streamStatus()` helpers.
- **Modify:** `app/Services/Integration/IntegrationService.php` — extract `persistMachines()`, add `deriveCapabilities()`, add `buildSyncStreams()`, add `connect()`.
- **Modify:** `app/Jobs/SyncIntegrationMachinesJob.php` — write real per-stream `sync_streams` counts on each scheduled run, not just `last_sync_at`.
- **Modify:** `app/Livewire/IntegrationManager.php` — add `connectIntegration()` (new one-click action) and `retestConnection()` (thin rename-free wrapper kept for UI clarity; internally calls the untouched `testConnection()`).
- **Modify:** `resources/views/livewire/integration-manager.blade.php` — wire the modal's submit button to `connectIntegration`, enrich the result panel with the per-check breakdown and capability badges, add per-stream status chips to each integration row.
- **Test:** `tests/Unit/IntegrationServiceCapabilitiesTest.php` — new, pure-function coverage for `deriveCapabilities()`.
- **Test:** `tests/Feature/IntegrationConnectFlowTest.php` — new, full-flow coverage for `connectIntegration()`.

---

### Task 1: Migration — capability + per-stream sync status columns

**Files:**
- Create: `database/migrations/2026_08_11_000001_add_capability_columns_to_integrations_table.php`
- Test: none (migration correctness is proven by Task 2's model test)

**Interfaces:**
- Produces: `integrations.capabilities` (nullable json), `integrations.sync_streams` (nullable json) — consumed by Task 2's model casts and Task 3's service methods.

- [ ] **Step 1: Write the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * capabilities: the data streams (subset of fleet/telemetry/production/
 * location) this integration's account has actually been observed to
 * provide, derived from a real API response by
 * IntegrationService::deriveCapabilities() -- never hardcoded per provider.
 *
 * sync_streams: per-stream status ({status, last_synced_at, records} per
 * capability key), what powers "Fleet sync: Active / Production sync:
 * Active" in the UI instead of one bundled status/last_sync_at pair.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('integrations', function (Blueprint $table) {
            $table->json('capabilities')->nullable()->after('config');
            $table->json('sync_streams')->nullable()->after('capabilities');
        });
    }

    public function down(): void
    {
        Schema::table('integrations', function (Blueprint $table) {
            $table->dropColumn(['capabilities', 'sync_streams']);
        });
    }
};
```

- [ ] **Step 2: Run the migration**

Run: `php artisan migrate`
Expected: `2026_08_11_000001_add_capability_columns_to_integrations_table ... DONE`

- [ ] **Step 3: Commit**

```bash
git add database/migrations/2026_08_11_000001_add_capability_columns_to_integrations_table.php
git commit -m "feat: add capability and sync_streams columns to integrations"
```

---

### Task 2: Integration model — casts and read helpers

**Files:**
- Modify: `app/Models/Integration.php`
- Test: `tests/Unit/IntegrationModelCapabilitiesTest.php`

**Interfaces:**
- Consumes: `capabilities`/`sync_streams` columns from Task 1.
- Produces: `Integration::hasCapability(string $key): bool`, `Integration::streamStatus(string $key): ?array` — consumed by Task 6's blade view.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit;

use App\Models\Integration;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IntegrationModelCapabilitiesTest extends TestCase
{
    use RefreshDatabase;

    public function test_has_capability_reflects_the_capabilities_array(): void
    {
        $integration = Integration::factory()->forProvider('bell')->create([
            'team_id' => Team::factory()->create()->id,
            'capabilities' => ['fleet', 'telemetry'],
        ]);

        $this->assertTrue($integration->hasCapability('fleet'));
        $this->assertTrue($integration->hasCapability('telemetry'));
        $this->assertFalse($integration->hasCapability('production'));
    }

    public function test_has_capability_is_false_when_capabilities_is_null(): void
    {
        $integration = Integration::factory()->forProvider('bell')->create([
            'team_id' => Team::factory()->create()->id,
            'capabilities' => null,
        ]);

        $this->assertFalse($integration->hasCapability('fleet'));
    }

    public function test_stream_status_returns_the_matching_entry(): void
    {
        $integration = Integration::factory()->forProvider('bell')->create([
            'team_id' => Team::factory()->create()->id,
            'sync_streams' => [
                'fleet' => ['status' => 'active', 'last_synced_at' => '2026-08-11T00:00:00+00:00', 'records' => 4],
                'production' => ['status' => 'unavailable', 'last_synced_at' => null, 'records' => 0],
            ],
        ]);

        $this->assertSame('active', $integration->streamStatus('fleet')['status']);
        $this->assertSame('unavailable', $integration->streamStatus('production')['status']);
        $this->assertNull($integration->streamStatus('telemetry'));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Unit/IntegrationModelCapabilitiesTest.php`
Expected: FAIL — `Call to undefined method App\Models\Integration::hasCapability()`

- [ ] **Step 3: Add the casts and helper methods**

In `app/Models/Integration.php`, add `'capabilities'` and `'sync_streams'` to `$fillable`, then extend `$casts`:

```php
    protected $fillable = [
        'team_id',
        'provider', // volvo, cat, komatsu, bell, c_track
        'name',
        'api_key',
        'api_secret',
        'credentials', // JSON for all credentials
        'webhook_url',
        'webhook_secret',
        'status', // connected, disconnected, error
        'last_sync_at',
        'last_sync_status', // success, failed
        'last_error',
        'last_error_at',
        'machines_count',
        'config', // JSON for provider-specific configuration
        'capabilities', // JSON list of data streams actually observed: fleet, telemetry, production, location
        'sync_streams', // JSON per-stream status: {status, last_synced_at, records} per capability key
    ];
```

```php
    protected $casts = [
        'last_sync_at' => 'datetime',
        'last_error_at' => 'datetime',
        'credentials' => 'encrypted:json',
        'config' => 'json',
        'capabilities' => 'json',
        'sync_streams' => 'json',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
```

Add after `machines()`:

```php
    /**
     * True only if $key was actually derived from a real API response by
     * IntegrationService::deriveCapabilities() -- never assume a provider
     * supports a stream just because another provider does.
     */
    public function hasCapability(string $key): bool
    {
        return in_array($key, $this->capabilities ?? [], true);
    }

    /**
     * @return array{status: string, last_synced_at: ?string, records: int}|null
     */
    public function streamStatus(string $key): ?array
    {
        return ($this->sync_streams ?? [])[$key] ?? null;
    }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact tests/Unit/IntegrationModelCapabilitiesTest.php`
Expected: PASS

- [ ] **Step 5: Run Pint**

Run: `vendor/bin/pint --dirty --format agent`

- [ ] **Step 6: Commit**

```bash
git add app/Models/Integration.php tests/Unit/IntegrationModelCapabilitiesTest.php
git commit -m "feat: add capability/sync_streams casts and read helpers to Integration"
```

---

### Task 3: IntegrationService — extract persistMachines(), add deriveCapabilities()

**Files:**
- Modify: `app/Services/Integration/IntegrationService.php`
- Test: `tests/Unit/IntegrationServiceCapabilitiesTest.php`

**Interfaces:**
- Consumes: nothing new — pure refactor of `syncMachines()`'s existing loop body plus a new pure function.
- Produces: `IntegrationService::persistMachines(Integration $integration, ManufacturerServiceInterface $service, array $machineList): array` (same return shape `syncMachines()` already produces: `{success, message, count}` or `{success, message: 'No machines found', count: 0}`), `IntegrationService::deriveCapabilities(array $sampleMachine): array` — both consumed by Task 4's `connect()`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit;

use App\Services\Integration\IntegrationService;
use Tests\TestCase;

class IntegrationServiceCapabilitiesTest extends TestCase
{
    public function test_fleet_is_always_present_when_a_machine_was_returned(): void
    {
        $capabilities = app(IntegrationService::class)->deriveCapabilities([
            'external_id' => 'X1',
            'metrics' => [],
        ]);

        $this->assertContains('fleet', $capabilities);
        $this->assertNotContains('telemetry', $capabilities);
        $this->assertNotContains('production', $capabilities);
        $this->assertNotContains('location', $capabilities);
    }

    public function test_telemetry_is_detected_from_a_real_metric_field(): void
    {
        $capabilities = app(IntegrationService::class)->deriveCapabilities([
            'external_id' => 'X1',
            'metrics' => ['fuel_level' => 82.5, 'operating_hours' => 1200],
        ]);

        $this->assertContains('telemetry', $capabilities);
    }

    public function test_production_is_detected_from_bells_own_raw_data_shape(): void
    {
        $capabilities = app(IntegrationService::class)->deriveCapabilities([
            'external_id' => 'X1',
            'metrics' => [
                'raw_data' => ['cumulative_payload' => 4032.1, 'load_count' => 118],
            ],
        ]);

        $this->assertContains('production', $capabilities);
    }

    public function test_location_is_detected_from_a_valid_last_location(): void
    {
        $capabilities = app(IntegrationService::class)->deriveCapabilities([
            'external_id' => 'X1',
            'metrics' => [],
            'last_location' => ['latitude' => -26.2, 'longitude' => 28.0],
        ]);

        $this->assertContains('location', $capabilities);
    }

    public function test_a_null_metric_value_does_not_count_as_present(): void
    {
        $capabilities = app(IntegrationService::class)->deriveCapabilities([
            'external_id' => 'X1',
            'metrics' => ['fuel_level' => null, 'operating_hours' => null],
        ]);

        $this->assertNotContains('telemetry', $capabilities);
    }

    public function test_an_empty_sample_returns_no_capabilities_at_all(): void
    {
        $this->assertSame([], app(IntegrationService::class)->deriveCapabilities([]));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Unit/IntegrationServiceCapabilitiesTest.php`
Expected: FAIL — `Call to undefined method App\Services\Integration\IntegrationService::deriveCapabilities()`

- [ ] **Step 3: Extract persistMachines() from syncMachines()**

In `app/Services/Integration/IntegrationService.php`, replace the body of `syncMachines()` from the `$synced = 0;` line onward:

```php
    public function syncMachines(Integration $integration): array
    {
        try {
            $service = $this->getServiceForIntegration($integration);

            if (! $service) {
                return ['success' => false, 'error' => 'Service not found'];
            }

            $machines = $service->fetchMachines();

            if (! ($machines['success'] ?? false)) {
                return [
                    'success' => false,
                    'error' => $machines['error'] ?? 'Failed to fetch machines',
                ];
            }

            $result = $this->persistMachines($integration, $service, $machines['machines'] ?? []);

            $integration->update(['last_sync_at' => now()]);

            return $result;
        } catch (\Throwable $e) {
            Log::error('Integration machine sync failed', [
                'integration_id' => $integration->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Persist an already-fetched machine list. Split out of syncMachines()
     * so IntegrationService::connect() (Task 4) can persist the exact same
     * list its own deep-check already fetched, instead of calling
     * fetchMachines() a second time against the live API (spec's "avoid
     * unnecessary duplicate API calls").
     */
    public function persistMachines(Integration $integration, ManufacturerServiceInterface $service, array $machineList): array
    {
        if (empty($machineList)) {
            return [
                'success' => true,
                'message' => 'No machines found',
                'count' => 0,
            ];
        }

        $synced = 0;
        foreach ($machineList as $machineData) {
            $machine = $this->syncMachine($integration, $machineData);

            if ($machine && $machine->manufacturer_id) {
                $this->syncMachineAlertsFromService($service, $machine);
            }

            $synced++;
        }

        return [
            'success' => true,
            'message' => "Synced {$synced} machines",
            'count' => $synced,
        ];
    }
```

- [ ] **Step 4: Add deriveCapabilities()**

Add as a new public method, near `getStatus()`:

```php
    /**
     * Derives which data streams a connected account actually provides
     * from the real shape of one sample machine record -- never a static
     * per-provider assumption. 'fleet' is present whenever a machine was
     * returned at all; 'telemetry'/'production'/'location' each require a
     * real, non-null field to be present, matching the exact shapes
     * BaseManufacturerService::parseMetrics()/BellService::buildCurrentMetric()
     * actually produce today.
     *
     * @return list<'fleet'|'telemetry'|'production'|'location'>
     */
    public function deriveCapabilities(array $sampleMachine): array
    {
        if (empty($sampleMachine)) {
            return [];
        }

        $capabilities = ['fleet'];
        $metrics = $sampleMachine['metrics'] ?? [];
        $rawData = $metrics['raw_data'] ?? [];

        $telemetryKeys = [
            'fuel_level', 'engine_temperature', 'operating_hours', 'idle_hours',
            'oil_pressure', 'coolant_temperature', 'battery_voltage', 'engine_rpm',
        ];
        foreach ($telemetryKeys as $key) {
            if (($metrics[$key] ?? null) !== null) {
                $capabilities[] = 'telemetry';
                break;
            }
        }

        $productionKeys = ['load_count', 'cumulative_payload', 'load_weight', 'cycles', 'payload_units'];
        foreach ($productionKeys as $key) {
            if (($metrics[$key] ?? null) !== null || ($rawData[$key] ?? null) !== null) {
                $capabilities[] = 'production';
                break;
            }
        }

        if (! empty($sampleMachine['last_location']['latitude']) && ! empty($sampleMachine['last_location']['longitude'])) {
            $capabilities[] = 'location';
        }

        return array_values(array_unique($capabilities));
    }
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --compact tests/Unit/IntegrationServiceCapabilitiesTest.php`
Expected: PASS

- [ ] **Step 6: Run the pre-existing IntegrationService/IntegrationManager tests to confirm the refactor changed nothing observable**

Run: `php artisan test --compact tests/Unit/IntegrationServiceTest.php tests/Feature/IntegrationManagerConnectionTest.php`
Expected: PASS (identical to before this task)

- [ ] **Step 7: Run Pint**

Run: `vendor/bin/pint --dirty --format agent`

- [ ] **Step 8: Commit**

```bash
git add app/Services/Integration/IntegrationService.php tests/Unit/IntegrationServiceCapabilitiesTest.php
git commit -m "refactor: extract persistMachines() and add deriveCapabilities() to IntegrationService"
```

---

### Task 4: IntegrationService::connect() — the deep pipeline check

**Files:**
- Modify: `app/Services/Integration/IntegrationService.php`
- Test: `tests/Feature/IntegrationConnectFlowTest.php` (created in Task 6, this task's own coverage is folded into Task 6's feature test since `connect()` has no meaningful behavior to assert without a real `Integration` + `Http::fake()` — matching how `testConnection()` itself is only covered at the feature/integration level today)

**Interfaces:**
- Consumes: `getServiceForIntegration()` (existing), `persistMachines()` and `deriveCapabilities()` (Task 3).
- Produces: `IntegrationService::connect(Integration $integration): array` — return shape:
  ```php
  [
      'success' => bool,
      'message' => string,
      'error' => ?string,
      'checks' => [
          'credentials_valid' => bool,
          'fleet_reachable' => bool,
          'data_retrieved' => bool,
          'data_storable' => bool,
          'tenant_associated' => bool,
          'sync_dispatchable' => bool,
      ],
      'capabilities' => list<string>,
      'sample_machine_count' => int,
  ]
  ```
  Consumed by Task 5's `IntegrationManager::connectIntegration()`.

- [ ] **Step 1: Add connect() to IntegrationService**

Add as a new public method, after `testConnection()`:

```php
    /**
     * The real, honest "Connect" pipeline (spec: "Do not report Connected
     * simply because authentication succeeded"). Unlike testConnection(),
     * which this method deliberately does NOT call or reuse the return
     * shape of, this actually fetches, persists, and dispatches an ongoing
     * sync -- every check here is a real side effect on the real data
     * path, not a simulated probe. On success, updates the Integration's
     * status/capabilities/sync_streams in one place so the UI always
     * reflects exactly what this method verified.
     */
    public function connect(Integration $integration): array
    {
        $checks = [
            'credentials_valid' => false,
            'fleet_reachable' => false,
            'data_retrieved' => false,
            'data_storable' => false,
            'tenant_associated' => false,
            'sync_dispatchable' => false,
        ];

        $service = $this->getServiceForIntegration($integration);

        if (! $service) {
            return [
                'success' => false,
                'message' => 'Connection failed',
                'error' => "Service not found for manufacturer: {$integration->provider}",
                'checks' => $checks,
                'capabilities' => [],
                'sample_machine_count' => 0,
            ];
        }

        try {
            $authenticated = $service->testConnection();
        } catch (\Throwable $e) {
            Log::error('Integration connect: auth check threw', [
                'integration_id' => $integration->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Connection failed',
                'error' => $e->getMessage(),
                'checks' => $checks,
                'capabilities' => [],
                'sample_machine_count' => 0,
            ];
        }

        // No separate scope/permission surface exists on
        // ManufacturerServiceInterface today -- a failed testConnection()
        // is the only signal this app can honestly attribute to either bad
        // credentials or missing permissions, so both checks share it
        // rather than fabricating a distinction the API doesn't expose.
        $checks['credentials_valid'] = $authenticated;
        $checks['fleet_reachable'] = $authenticated;

        if (! $authenticated) {
            return [
                'success' => false,
                'message' => 'Connection failed — API credentials could not be verified.',
                'error' => $service->getLastError(),
                'checks' => $checks,
                'capabilities' => [],
                'sample_machine_count' => 0,
            ];
        }

        $fetchResult = $service->fetchMachines();
        $checks['data_retrieved'] = $fetchResult['success'] ?? false;

        if (! $checks['data_retrieved']) {
            return [
                'success' => false,
                'message' => 'Connected, but fleet data could not be retrieved.',
                'error' => $fetchResult['error'] ?? 'Failed to fetch fleet data',
                'checks' => $checks,
                'capabilities' => [],
                'sample_machine_count' => 0,
            ];
        }

        $machineList = $fetchResult['machines'] ?? [];
        $capabilities = $this->deriveCapabilities($machineList[0] ?? []);

        $syncResult = $this->persistMachines($integration, $service, $machineList);
        $checks['data_storable'] = $syncResult['success'] ?? false;

        // syncMachine() always writes $integration->team_id onto every row
        // it creates/updates -- if persistence succeeded at all, tenant
        // association is true by construction, not a separate query.
        $checks['tenant_associated'] = $checks['data_storable'];

        try {
            SyncIntegrationMachinesJob::dispatch($integration);
            $checks['sync_dispatchable'] = true;
        } catch (\Throwable $e) {
            Log::warning('Integration connect: failed to dispatch ongoing sync job', [
                'integration_id' => $integration->id,
                'error' => $e->getMessage(),
            ]);
            $checks['sync_dispatchable'] = false;
        }

        $streams = $this->buildSyncStreams($capabilities, $syncResult['count'] ?? 0);

        $integration->update([
            'status' => 'connected',
            'capabilities' => $capabilities,
            'sync_streams' => $streams,
            'last_sync_at' => now(),
            'last_sync_status' => 'success',
            'last_error' => null,
        ]);

        $message = in_array('production', $capabilities, true) || count($machineList) === 0
            ? 'Connection successful'
            : 'Connected, but production data could not be synchronised.';

        return [
            'success' => true,
            'message' => $message,
            'error' => null,
            'checks' => $checks,
            'capabilities' => $capabilities,
            'sample_machine_count' => count($machineList),
        ];
    }

    /**
     * @param  list<string>  $capabilities
     * @return array<string, array{status: string, last_synced_at: ?string, records: int}>
     */
    private function buildSyncStreams(array $capabilities, int $recordCount): array
    {
        $now = now()->toIso8601String();
        $streams = [];

        foreach (['fleet', 'telemetry', 'production', 'location'] as $stream) {
            $streams[$stream] = in_array($stream, $capabilities, true)
                ? ['status' => 'active', 'last_synced_at' => $now, 'records' => $recordCount]
                : ['status' => 'unavailable', 'last_synced_at' => null, 'records' => 0];
        }

        return $streams;
    }
```

Add the import at the top of the file:

```php
use App\Jobs\SyncIntegrationMachinesJob;
```

- [ ] **Step 2: Run Pint**

Run: `vendor/bin/pint --dirty --format agent`

- [ ] **Step 3: Run the pre-existing tests to confirm nothing broke**

Run: `php artisan test --compact tests/Unit/IntegrationServiceTest.php tests/Unit/IntegrationServiceCapabilitiesTest.php tests/Feature/IntegrationManagerConnectionTest.php`
Expected: PASS

- [ ] **Step 4: Commit**

```bash
git add app/Services/Integration/IntegrationService.php
git commit -m "feat: add IntegrationService::connect() deep pipeline check"
```

---

### Task 5: SyncIntegrationMachinesJob — write real per-stream status on every scheduled run

**Files:**
- Modify: `app/Jobs/SyncIntegrationMachinesJob.php`
- Test: `tests/Unit/SyncIntegrationMachinesJobStreamsTest.php`

**Interfaces:**
- Consumes: `Integration::capabilities` (Task 2) — the job re-derives nothing, it re-stamps `sync_streams` timestamps/counts for whichever capabilities `connect()` already established, keeping "Last successful sync" honest per stream across every scheduled run, not just the initial connect.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit;

use App\Jobs\SyncIntegrationMachinesJob;
use App\Models\Integration;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SyncIntegrationMachinesJobStreamsTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_successful_run_refreshes_last_synced_at_for_each_active_stream(): void
    {
        Http::fake(['*' => Http::response(['data' => []], 200)]);

        $integration = Integration::factory()->forProvider('hitachi')->create([
            'team_id' => Team::factory()->create()->id,
            'status' => 'connected',
            'credentials' => ['api_key' => 'key', 'base_url' => 'https://api.example.test'],
            'capabilities' => ['fleet', 'telemetry'],
            'sync_streams' => [
                'fleet' => ['status' => 'active', 'last_synced_at' => '2026-08-01T00:00:00+00:00', 'records' => 3],
                'telemetry' => ['status' => 'active', 'last_synced_at' => '2026-08-01T00:00:00+00:00', 'records' => 3],
                'production' => ['status' => 'unavailable', 'last_synced_at' => null, 'records' => 0],
                'location' => ['status' => 'unavailable', 'last_synced_at' => null, 'records' => 0],
            ],
        ]);

        (new SyncIntegrationMachinesJob($integration))->handle(app(\App\Services\Integration\IntegrationService::class));

        $fresh = $integration->fresh();
        $this->assertNotSame('2026-08-01T00:00:00+00:00', $fresh->sync_streams['fleet']['last_synced_at']);
        $this->assertNotSame('2026-08-01T00:00:00+00:00', $fresh->sync_streams['telemetry']['last_synced_at']);
        // Never-provided streams stay unavailable -- a scheduled sync run
        // must not fabricate a stream the account never demonstrated.
        $this->assertSame('unavailable', $fresh->sync_streams['production']['status']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Unit/SyncIntegrationMachinesJobStreamsTest.php`
Expected: FAIL — `last_synced_at` unchanged (job doesn't touch `sync_streams` yet)

- [ ] **Step 3: Update the job**

In `app/Jobs/SyncIntegrationMachinesJob.php`, replace the `if ($result['success']) { ... update ... }` block inside `handle()`:

```php
            // Perform sync
            $result = $integrationService->syncMachines($this->integration);

            // Update integration with sync status
            $this->integration->update([
                'last_sync_at' => now(),
                'last_sync_status' => $result['success'] ? 'success' : 'failed',
            ]);

            if ($result['success']) {
                // Refresh last_synced_at (and record count) for every
                // stream this integration already established during
                // connect() -- never invent a stream that isn't already in
                // capabilities, a scheduled run only refreshes what
                // connect() already confirmed was real.
                $streams = $this->integration->sync_streams ?? [];
                $now = now()->toIso8601String();
                $count = $result['count'] ?? 0;

                foreach ($this->integration->capabilities ?? [] as $capability) {
                    $streams[$capability] = [
                        'status' => 'active',
                        'last_synced_at' => $now,
                        'records' => $count,
                    ];
                }

                $this->integration->update(['sync_streams' => $streams]);

                Log::info('Machine sync completed successfully', [
                    'integration_id' => $this->integration->id,
                    'machines_synced' => $count,
                ]);
            } else {
                Log::error('Machine sync failed', [
                    'integration_id' => $this->integration->id,
                    'error' => $result['error'] ?? 'Unknown error',
                ]);
            }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact tests/Unit/SyncIntegrationMachinesJobStreamsTest.php`
Expected: PASS

- [ ] **Step 5: Run the pre-existing job tests to confirm nothing broke**

Run: `php artisan test --compact tests/Unit/ScheduledIntegrationJobsTest.php tests/Unit/SyncDueIntegrationsTest.php`
Expected: PASS

- [ ] **Step 6: Run Pint**

Run: `vendor/bin/pint --dirty --format agent`

- [ ] **Step 7: Commit**

```bash
git add app/Jobs/SyncIntegrationMachinesJob.php tests/Unit/SyncIntegrationMachinesJobStreamsTest.php
git commit -m "feat: refresh per-stream sync_streams status on every scheduled sync run"
```

---

### Task 6: IntegrationManager — the one-click connectIntegration() action

**Files:**
- Modify: `app/Livewire/IntegrationManager.php`
- Test: `tests/Feature/IntegrationConnectFlowTest.php`

**Interfaces:**
- Consumes: `IntegrationService::connect()` (Task 4).
- Produces: `IntegrationManager::connectIntegration(): void` (validates `formData`, creates the `Integration` row, immediately runs `connect()`, sets `$testResult` to its return array, sets `$showTestModal = true`) — consumed by Task 7's blade view. `IntegrationManager::retestConnection(int $integrationId): void` (re-runs `connect()` against an already-saved integration, no re-entered credentials) — consumed by Task 7's per-row "Retest" button.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Livewire\IntegrationManager;
use App\Models\Integration;
use App\Models\Team;
use App\Models\User;
use App\Services\TeamRoleProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The new one-click "Connect" flow (spec: "the user should not have to
 * configure Fleet and Production separately" -- confirmed in the plan doc
 * that this was never actually true; this is the real gap the spec's
 * acceptance criteria still point at: one action does validate + save +
 * enable + sync, and partial capability is reported honestly, not as a
 * blanket failure).
 */
class IntegrationConnectFlowTest extends TestCase
{
    use RefreshDatabase;

    private function actingAdmin(): User
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);
        $owner->update(['current_team_id' => $team->id]);
        TeamRoleProvisioner::assignRole($owner, $team, 'admin');

        return $owner;
    }

    public function test_connecting_with_valid_credentials_saves_tests_and_syncs_in_one_action(): void
    {
        Http::fake(['*' => Http::response(['data' => [['id' => 'H1', 'model' => 'ZX350']]], 200)]);

        $owner = $this->actingAdmin();

        Livewire::actingAs($owner)
            ->test(IntegrationManager::class)
            ->set('formData.provider', 'hitachi')
            ->set('formData.name', 'Hitachi Fleet')
            ->set('formData.connection_type', 'api')
            ->set('formData.sync_frequency', 'manual')
            ->set('formData.credentials.api_key', 'real-key')
            ->set('formData.credentials.api_secret', 'real-secret')
            ->call('connectIntegration')
            ->assertSet('testResult.success', true)
            ->assertSet('showTestModal', true);

        $integration = Integration::firstWhere('name', 'Hitachi Fleet');
        $this->assertNotNull($integration);
        $this->assertSame('connected', $integration->status);
        $this->assertContains('fleet', $integration->capabilities);
    }

    public function test_connecting_with_invalid_credentials_reports_failure_without_leaving_a_connected_row(): void
    {
        Http::fake(['*' => Http::response('', 401)]);

        $owner = $this->actingAdmin();

        Livewire::actingAs($owner)
            ->test(IntegrationManager::class)
            ->set('formData.provider', 'hitachi')
            ->set('formData.name', 'Bad Creds')
            ->set('formData.connection_type', 'api')
            ->set('formData.sync_frequency', 'manual')
            ->set('formData.credentials.api_key', 'wrong')
            ->set('formData.credentials.api_secret', 'wrong')
            ->call('connectIntegration')
            ->assertSet('testResult.success', false)
            ->assertSet('testResult.message', 'Connection failed — API credentials could not be verified.');

        $integration = Integration::firstWhere('name', 'Bad Creds');
        $this->assertNotNull($integration);
        $this->assertNotSame('connected', $integration->status);
    }

    public function test_retest_connection_re_verifies_an_existing_integration_without_new_credentials(): void
    {
        Http::fake(['*' => Http::response(['data' => [['id' => 'H1', 'model' => 'ZX350']]], 200)]);

        $owner = $this->actingAdmin();
        $integration = Integration::factory()->forProvider('hitachi')->create([
            'team_id' => $owner->current_team_id,
            'status' => 'error',
            'credentials' => ['api_key' => 'real-key', 'base_url' => 'https://api.example.test'],
        ]);

        Livewire::actingAs($owner)
            ->test(IntegrationManager::class)
            ->call('retestConnection', $integration->id)
            ->assertSet('testResult.success', true);

        $this->assertSame('connected', $integration->fresh()->status);
        // The credentials already on the row are exactly what got re-used --
        // nothing in this call path accepts new credential input.
        $this->assertSame('real-key', $integration->fresh()->credentials['api_key']);
    }

    public function test_credentials_never_appear_in_the_integrations_array_state(): void
    {
        Http::fake(['*' => Http::response(['data' => []], 200)]);

        $owner = $this->actingAdmin();
        Integration::factory()->forProvider('hitachi')->create([
            'team_id' => $owner->current_team_id,
            'credentials' => ['api_key' => 'super-secret-key', 'api_secret' => 'super-secret-value'],
        ]);

        $component = Livewire::actingAs($owner)->test(IntegrationManager::class);

        $this->assertStringNotContainsString('super-secret-key', json_encode($component->get('integrations')));
        $this->assertStringNotContainsString('super-secret-value', json_encode($component->get('integrations')));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Feature/IntegrationConnectFlowTest.php`
Expected: FAIL — `Unable to call component method. Public method [connectIntegration] not found`

- [ ] **Step 3: Add connectIntegration() and retestConnection()**

In `app/Livewire/IntegrationManager.php`, add the import:

```php
use App\Services\Integration\IntegrationService;
```

(already imported — confirm, don't duplicate). Add these two new public methods after `createIntegration()`:

```php
    /**
     * The spec's single "Connect" action: validate -> save -> authenticate
     * -> fetch -> store -> dispatch ongoing sync, all in one click. Shares
     * createIntegration()'s own validation rules exactly (including Bell's
     * separate credential fields) so a submission that would have been
     * accepted by the old two-step flow is accepted here too.
     */
    public function connectIntegration(): void
    {
        if (! $this->team) {
            $this->addError('general', 'No team context available');

            return;
        }

        $rules = [
            'formData.provider' => 'required|string',
            'formData.name' => 'required|string|max:100',
            'formData.connection_type' => 'required|string',
            'formData.sync_frequency' => 'required|string',
            'formData.notification_email' => 'nullable|email',
            'formData.endpoint' => 'nullable|string',
        ];

        if ($this->formData['provider'] === 'bell') {
            $rules['formData.credentials.username'] = 'required|string';
            $rules['formData.credentials.password'] = 'required|string';
            $rules['formData.credentials.client_secret'] = 'required|string';
        } else {
            $rules['formData.credentials.api_key'] = 'required|string';
            $rules['formData.credentials.api_secret'] = 'required|string';
        }

        $this->validate($rules);

        if ($this->formData['provider'] === 'bell' && empty($this->formData['credentials']['client_id'])) {
            $this->formData['credentials']['client_id'] = 'ISO_Export_Service';
        }

        try {
            $this->authorize('create', Integration::class);

            $integration = Integration::create([
                'team_id' => $this->team->id,
                'provider' => $this->formData['provider'],
                'name' => $this->formData['name'],
                'credentials' => $this->formData['credentials'],
                'status' => 'pending',
                'webhook_url' => $this->formData['connection_type'] === 'webhook' && Route::has('webhook.receive')
                    ? route('webhook.receive', ['provider' => $this->formData['provider']])
                    : null,
                'config' => [
                    'endpoint' => $this->formData['endpoint'],
                    'connection_type' => $this->formData['connection_type'],
                    'sync_frequency' => $this->formData['sync_frequency'],
                    'notification_email' => $this->formData['notification_email'],
                ],
            ]);

            $this->testResult = app(IntegrationService::class)->connect($integration);
            $this->showTestModal = true;
            $this->closeAddModal();
            $this->loadIntegrations();
        } catch (\Throwable $e) {
            Log::error('Failed to connect integration', ['error' => $e->getMessage()]);
            $this->addError('general', 'Failed to connect integration. Please try again.');
        }
    }

    /**
     * Re-runs the same deep check against an already-saved integration's
     * existing credentials -- the spec's "allow the user to retry without
     * re-entering credentials unnecessarily." Distinct from testConnection()
     * (the shallow, pre-existing action left untouched for backward
     * compatibility) so both remain independently callable.
     */
    public function retestConnection($integrationId): void
    {
        if (! $this->team) {
            $this->testResult = ['success' => false, 'message' => 'No team context available'];
            $this->showTestModal = true;

            return;
        }

        try {
            $integration = Integration::where('team_id', $this->team->id)->findOrFail($integrationId);
            $this->authorize('test', $integration);

            $this->testResult = app(IntegrationService::class)->connect($integration);
            $this->selectedIntegration = $integrationId;
            $this->showTestModal = true;
            $this->loadIntegrations();
        } catch (\Throwable $e) {
            Log::error('Retest connection failed', ['error' => $e->getMessage()]);
            $this->testResult = ['success' => false, 'message' => 'Error testing connection'];
            $this->showTestModal = true;
        }
    }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact tests/Feature/IntegrationConnectFlowTest.php`
Expected: PASS

- [ ] **Step 5: Run the full pre-existing integration test set to confirm nothing broke**

Run: `php artisan test --compact tests/Feature/IntegrationManagerConnectionTest.php tests/Feature/IntegrationManagerAuthorizationTest.php tests/Feature/IntegrationsPageTest.php tests/Unit/IntegrationServiceTest.php`
Expected: PASS

- [ ] **Step 6: Run Pint**

Run: `vendor/bin/pint --dirty --format agent`

- [ ] **Step 7: Commit**

```bash
git add app/Livewire/IntegrationManager.php tests/Feature/IntegrationConnectFlowTest.php
git commit -m "feat: add IntegrationManager::connectIntegration() one-click flow and retestConnection()"
```

---

### Task 7: Blade view — wire the new action and show per-stream status honestly

**Files:**
- Modify: `resources/views/livewire/integration-manager.blade.php`
- Test: none new — covered by Task 6's feature test via `assertSet`; this task is a pure presentation change verified manually (Step 4) since Livewire feature tests don't render Blade output by default in this suite's existing convention (`IntegrationManagerConnectionTest.php` never asserts on rendered HTML either).

**Interfaces:**
- Consumes: `Integration::hasCapability()`/`streamStatus()` (Task 2), `$testResult['checks']`/`$testResult['capabilities']` (Task 4/6).

- [ ] **Step 1: Change the submit button**

Find (around line 300-305):

```blade
                    <button 
                        wire:click="createIntegration"
                        class="flex-1 px-4 py-2 bg-[var(--gold)] hover:bg-[var(--gold-soft)] text-[var(--ink)] rounded font-display font-semibold transition"
                    >
                        Create Integration
                    </button>
```

Replace with:

```blade
                    <button 
                        wire:click="connectIntegration"
                        class="flex-1 px-4 py-2 bg-[var(--gold)] hover:bg-[var(--gold-soft)] text-[var(--ink)] rounded font-display font-semibold transition"
                    >
                        Connect
                    </button>
```

- [ ] **Step 2: Enrich the result modal with the per-check breakdown and capability badges**

Find the "Test Connection Modal" block (around line 311-330) and replace its inner content:

```blade
    <!-- Connect Result Modal -->
    @if($showTestModal && $testResult)
        <div class="fixed inset-0 bg-black/50 flex items-center justify-center z-50" style="backdrop-filter: blur(4px);">
            <div class="bg-[var(--ink-soft)] border border-[var(--line)] rounded-lg shadow-xl max-w-md w-full mx-4">
                <div class="p-6 text-center">
                    @if($testResult['success'])
                        <div class="text-5xl mb-4">✅</div>
                        <h3 class="text-xl font-bold text-green-400 mb-2">Connected</h3>
                    @else
                        <div class="text-5xl mb-4">❌</div>
                        <h3 class="text-xl font-bold text-red-400 mb-2">Connection Failed</h3>
                    @endif
                    <p class="text-[var(--sand)]">{{ $testResult['message'] ?? '' }}</p>
                </div>

                @if(!empty($testResult['checks']))
                    <div class="px-6 pb-4 text-sm text-left">
                        @foreach($testResult['checks'] as $label => $passed)
                            <div class="flex items-center gap-2 py-1">
                                <span>{{ $passed ? '✓' : '✗' }}</span>
                                <span class="text-[var(--stone)]">{{ ucfirst(str_replace('_', ' ', $label)) }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif

                @if(!empty($testResult['capabilities']))
                    <div class="px-6 pb-4">
                        <p class="text-xs text-[var(--sand)] uppercase tracking-wide mb-2">Data available from this connection</p>
                        <div class="flex flex-wrap gap-2">
                            @foreach($testResult['capabilities'] as $capability)
                                <span class="text-xs px-2 py-1 rounded bg-[var(--gold)]/10 text-[var(--gold)]">
                                    ✓ {{ ucfirst($capability) }}
                                </span>
                            @endforeach
                        </div>
                    </div>
                @endif

                <div class="p-6 border-t border-[var(--line)]">
                    <button 
                        wire:click="$set('showTestModal', false)"
                        class="w-full px-4 py-2 bg-[var(--gold)] hover:bg-[var(--gold-soft)] text-[var(--ink)] rounded font-display font-semibold transition"
                    >
                        Close
                    </button>
                </div>
            </div>
        </div>
    @endif
```

- [ ] **Step 3: Add per-stream status chips and a Retest button to each integration row**

Find the loop rendering `$integrations` (search for `@foreach($integrations`) and locate the row's action buttons. Add per-stream chips before the existing action buttons, and add a "Retest" button alongside the existing "Test Connection"/"Sync"/"Delete" buttons — same `wire:click` pattern already used for `testConnection($integration['id'])`:

```blade
<div class="flex flex-wrap gap-1.5 mt-2">
    @foreach(['fleet', 'telemetry', 'production', 'location'] as $stream)
        @php($streamStatus = \App\Models\Integration::find($integration['id'])?->streamStatus($stream))
        @if($streamStatus)
            <span class="text-xs px-2 py-0.5 rounded {{ $streamStatus['status'] === 'active' ? 'bg-green-500/10 text-green-400' : 'bg-white/5 text-[var(--sand)]' }}">
                {{ ucfirst($stream) }}: {{ ucfirst($streamStatus['status']) }}
            </span>
        @endif
    @endforeach
</div>
<button 
    wire:click="retestConnection({{ $integration['id'] }})"
    class="text-xs px-3 py-1.5 bg-white/5 hover:bg-white/10 border border-[var(--line)] text-[var(--stone)] rounded transition"
>
    Retest
</button>
```

- [ ] **Step 4: Verify manually in the browser**

Run: `php artisan serve` (or the project's usual `composer run dev`), open `/integrations`, click "Add Integration", pick a provider, fill in fake credentials, click "Connect" — confirm the result modal shows the checklist and closes correctly. This is a presentation-only change with no automated render assertion in this suite's existing convention.

- [ ] **Step 5: Commit**

```bash
git add resources/views/livewire/integration-manager.blade.php
git commit -m "feat: wire Connect action and per-stream status into integration-manager view"
```

---

### Task 8: Full regression pass

**Files:** none — verification only.

- [ ] **Step 1: Run the full test suite**

Run: `php artisan test --compact`
Expected: all tests pass, including every pre-existing integration test named in Tasks 3-6 plus every new test added in this plan.

- [ ] **Step 2: Run Pint across the whole diff**

Run: `vendor/bin/pint --dirty --format agent`
Expected: `{"result":"passed"}` or auto-fixed with no remaining issues.

- [ ] **Step 3: Confirm no secret ever appears in a committed file**

Run: `git log --oneline -9 -- app/Models/Integration.php app/Services/Integration/IntegrationService.php app/Livewire/IntegrationManager.php app/Jobs/SyncIntegrationMachinesJob.php resources/views/livewire/integration-manager.blade.php database/migrations/2026_08_11_000001_add_capability_columns_to_integrations_table.php`
Expected: 9 commits, one per task above (Task 7 has no test commit line but still counts).

- [ ] **Step 4: Report to the user**

Summarize: what changed, which existing tests were preserved untouched (list the 4 pre-existing files), and the honest scope note that "Production endpoint accessible" and "permissions/scopes available" (spec §4 items 3-4) are derived from the same single authenticated call every provider's `ManufacturerServiceInterface` exposes today, not verified against a separate endpoint — because no such separate endpoint exists in the current interface for any of the 25 manufacturer services.

---

## Self-Review Notes

- **Spec coverage:** §1 (single credential setup) — unchanged, already true, verified in the plan's own investigation. §2 (one connection → multiple streams) — Task 4's `connect()` + `deriveCapabilities()`. §3 (integration status) — Task 2's `streamStatus()` + Task 7's chips. §4 (test the complete pipeline) — Task 4's six `checks`. §5 (automatic sync) — already partially real (`integrations:sync-due`); Task 5 makes per-stream status track it honestly; pagination/rate-limit/dedup were already handled per-provider in `BaseManufacturerService::request()`'s retry logic and are out of scope for this plan (not touched, not claimed as new). §6 (remove duplicate configuration) — investigated and reported: no duplication existed to remove; the plan does not fabricate a removal for a bug that doesn't exist. §7 (data-source transparency) — Task 7's capability badges. §8 (error handling) — Task 4's exact required copy strings, Task 6's `retestConnection()`. §9 (security) — no new field ever reads `credentials`/`api_key`/`api_secret` outside the existing `$hidden` boundary; Task 6 includes a regression test proving it.
- **Placeholder scan:** none found — every step has real code or a real command.
- **Type consistency:** `IntegrationService::connect()`'s return shape is used identically in Task 6 (`$this->testResult = ... ->connect($integration)`) and Task 7 (`$testResult['checks']`, `$testResult['capabilities']`) — verified matching keys throughout.
