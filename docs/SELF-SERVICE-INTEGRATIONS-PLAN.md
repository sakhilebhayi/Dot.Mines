# Self-Service Machine API Integrations — Implementation Plan

## Problem Statement

The current Bell Equipment integration is entirely hardcoded:
- Credentials live in `.env` and are deployed by a developer
- Sync jobs run globally with no per-account scoping
- The `integrations` table exists but is disconnected from the real sync pipeline
- No other team can add their own OEM integration without a code change and redeployment
- The Integrations page UI exists but does not drive real syncs

**Goal:** Any account admin should be able to add their own OEM API credentials through the
Integrations page, have machines synced automatically to their team only, and see live telemetry
in the same pages AfriCoal sees today — without any developer involvement.

---

## Current State Audit

### What exists
| Layer | Current state |
|---|---|
| `integrations` table | `team_id`, `provider`, `credentials` (JSON), `status`, `last_sync_at`, `config` (JSON) — schema is solid |
| `IntegrationManager` Livewire | Create/delete/test/sync actions — all wired but `testConnection` + `syncMachines` call a stub `IntegrationService` |
| `IntegrationService` | Stub — does not call any real OEM API |
| Bell jobs | Hardcoded to env-var credentials, run for all teams at once |
| Machine linking | `bell_equipment.machine_id` → `machines.team_id` works but only for Bell |
| Credential security | Stored as plain JSON in `credentials` column — not encrypted |

### What is missing
1. Real per-integration credential dispatch (replacing global env-var jobs)
2. Encrypted credential storage
3. A generic `ManufacturerAdapter` interface that each OEM driver implements
4. Per-integration scheduled sync (every 5 min, respecting the integration's `config.sync_frequency`)
5. Machine auto-assignment to the correct team on first sync
6. Full sync audit log visible in the UI
7. Credential edit / key rotation UI
8. Sync pause / resume controls
9. Machine count + last seen per integration in the UI
10. OAuth2 / SSO credential flows (not just API key + secret)

---

## Architecture Overview

```
User fills in credentials on Integrations page
         │
         ▼
IntegrationManager (Livewire)
  → validates & encrypts credentials
  → stores in integrations table (team_id + provider + encrypted creds)
  → dispatches TestIntegrationConnectionJob (immediate)
         │
         ▼
TestIntegrationConnectionJob
  → resolves ManufacturerAdapter for provider
  → calls adapter->testConnection(credentials)
  → updates integration.status = 'connected' | 'failed'
  → broadcasts result to UI via Livewire event
         │
On success, scheduler picks up:
         │
         ▼
DispatchIntegrationSyncsJob (runs every 5 min, global)
  → queries integrations WHERE status='connected' AND sync_due
  → for each: dispatches SyncIntegrationJob(integrationId)
         │
         ▼
SyncIntegrationJob(integrationId)
  → loads integration + decrypts credentials
  → resolves ManufacturerAdapter for provider
  → adapter->syncFleet(credentials) → returns list of machines + telemetry
  → upserts machines scoped to integration.team_id
  → stores telemetry in existing bell_* / generic telemetry tables
  → writes IntegrationSyncLog entry
  → updates integration.last_sync_at + last_sync_status
```

---

## Implementation Phases

---

### Phase 1 — Secure Credential Storage (1–2 days)

**Goal:** Credentials must be encrypted at rest before we expose UI-driven credential entry.

#### 1.1 — Encrypt the `credentials` column

Use Laravel's `encrypted` cast on the `Integration` model:

```php
// app/Models/Integration.php
protected function casts(): array
{
    return [
        'credentials' => 'encrypted:array',  // AES-256-CBC via APP_KEY
        'config'      => 'array',
        'last_sync_at' => 'datetime',
    ];
}
```

Run a one-time migration to re-encrypt any existing plaintext credentials:

```bash
php artisan make:command EncryptExistingIntegrationCredentials
php artisan integration:encrypt-credentials
```

#### 1.2 — Never log or expose credentials

- Add `credentials` to `$hidden` on the `Integration` model
- Ensure no route or API resource serialises `credentials`
- Add `CredentialExposureTest` to confirm the field never appears in HTTP responses

---

### Phase 2 — Manufacturer Adapter Interface (2–3 days)

**Goal:** Each OEM (Bell, Komatsu, Volvo, CTrack, etc.) implements one contract. Adding a new
OEM never requires touching the core sync pipeline.

#### 2.1 — Define the contract

```php
// app/Contracts/ManufacturerAdapterInterface.php

interface ManufacturerAdapterInterface
{
    /**
     * Verify credentials are valid and the API is reachable.
     * @return array{success: bool, message: string, machines_found?: int}
     */
    public function testConnection(array $credentials): array;

    /**
     * Fetch the current fleet snapshot.
     * @return list<array{
     *   external_id: string,
     *   name: string,
     *   model: string,
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
     * Fetch historical telemetry for one machine over a date range.
     * @return list<array{signal: string, value: mixed, recorded_at: string}>
     */
    public function fetchHistory(array $credentials, string $externalId, string $from, string $to): array;

    /**
     * Return the credential fields this adapter requires (for dynamic form rendering).
     * @return list<array{key: string, label: string, type: 'text'|'password'|'url', required: bool, hint?: string}>
     */
    public function credentialSchema(): array;
}
```

#### 2.2 — Refactor Bell into an adapter

Move the existing `BellIso15143Service` logic into a `BellEquipmentAdapter` that implements
`ManufacturerAdapterInterface`. The existing Bell jobs become thin wrappers that resolve the
adapter from the container.

#### 2.3 — Adapter registry

```php
// app/Services/Integration/AdapterRegistry.php

class AdapterRegistry
{
    /** @var array<string, class-string<ManufacturerAdapterInterface>> */
    private array $adapters = [
        'bell'       => BellEquipmentAdapter::class,
        'komatsu'    => KomatsuAdapter::class,
        'volvo'      => VolvoAdapter::class,
        'cat'        => CaterpillarAdapter::class,
        'ctrack'     => CTrackAdapter::class,
        'john-deere' => JohnDeereAdapter::class,
        'sandvik'    => SandvikAdapter::class,
        'epiroc'     => EpirocAdapter::class,
        // ... add new OEMs here, no other code changes needed
    ];

    public function resolve(string $provider): ManufacturerAdapterInterface { ... }
    public function has(string $provider): bool { ... }
    public function all(): array { ... }  // returns credential schemas for UI
}
```

---

### Phase 3 — Per-Integration Sync Pipeline (3–4 days)

**Goal:** Replace hardcoded global Bell jobs with a generic per-integration queue pipeline.

#### 3.1 — `DispatchIntegrationSyncsJob` (runs every 5 min)

```php
// Replaces / supplements the individual Bell cron jobs in routes/console.php

Schedule::job(new DispatchIntegrationSyncsJob)
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->onOneServer();
```

Logic:
- Queries `integrations` where `status = 'connected'`
- Checks `last_sync_at` against `config.sync_frequency` to determine if a sync is due
- Dispatches `SyncIntegrationJob($integrationId)` on the `integrations` queue

#### 3.2 — `SyncIntegrationJob`

```php
class SyncIntegrationJob implements ShouldQueue
{
    public function __construct(private readonly int $integrationId) {}

    public function handle(AdapterRegistry $registry): void
    {
        $integration = Integration::findOrFail($this->integrationId);
        $credentials = $integration->credentials;  // auto-decrypted by cast
        $adapter     = $registry->resolve($integration->provider);

        // 1. Fetch fleet
        $fleet = $adapter->fetchFleet($credentials);

        // 2. Upsert machines scoped to this team
        foreach ($fleet as $machine) {
            $this->upsertMachine($integration->team_id, $integration->id, $machine);
        }

        // 3. Update integration status
        $integration->update([
            'last_sync_at'     => now(),
            'last_sync_status' => 'success',
            'machines_count'   => count($fleet),
        ]);

        // 4. Write sync log
        IntegrationSyncLog::create([...]);
    }
}
```

#### 3.3 — `IntegrationSyncLog` model + migration

New table to give users visibility into every sync attempt:

```
integration_sync_logs
  id, integration_id, team_id, started_at, finished_at,
  status (success|failed|partial), machines_synced, records_inserted,
  error_message, created_at
```

---

### Phase 4 — Machine Auto-Assignment (1 day)

**Goal:** Machines fetched by an integration are always scoped to the right team.

The `upsertMachine` method in `SyncIntegrationJob` must:

1. Find existing machine by `(team_id, external_id)` — prevents cross-team pollution
2. Create with `team_id = integration->team_id` if not found
3. Set `manufacturer` from the adapter's provider name
4. Update location, status, operating hours from telemetry
5. Link `bell_equipment.machine_id` (or generic `integration_machines` pivot) back to the
   canonical `machines` row

**Critical:** never assign a machine to a team it does not already belong to. The
`(team_id, external_id)` composite lookup enforces this.

---

### Phase 5 — Integrations Page Overhaul (3–4 days)

**Goal:** The page becomes the single place users configure, monitor, and manage their
OEM connections.

#### 5.1 — Dynamic credential form

Instead of hardcoded `@if $formData['provider'] === 'ctrack'` branches in the view,
drive the form from `AdapterRegistry::all()` — each adapter returns its
`credentialSchema()` and the view renders fields dynamically.

```blade
{{-- Dynamic credential fields from adapter schema --}}
@foreach($credentialFields as $field)
    <div>
        <label>{{ $field['label'] }} @if($field['required'])*@endif</label>
        <input
            type="{{ $field['type'] }}"
            wire:model="formData.credentials.{{ $field['key'] }}"
            placeholder="{{ $field['hint'] ?? '' }}"
        />
    </div>
@endforeach
```

#### 5.2 — Live "Test Connection" before save

On the Add modal, a **Test Connection** button calls
`IntegrationManager::liveTestConnection()` (no DB write yet) — shows inline
success/failure with machine count found before the user saves anything.

#### 5.3 — Integration detail panel

Replace the flat table with expandable rows (or a slide-over panel) showing:
- Connection status + last tested
- Sync history: last 10 `IntegrationSyncLog` entries (time, duration, machines synced, status)
- Machine count with a link to the fleet page filtered to that integration
- Sync now button + pause toggle
- Edit credentials (re-enter, never display existing values)
- Rotate webhook secret

#### 5.4 — Sync frequency selector

```
Every 5 minutes | Every 15 minutes | Every hour | Every 6 hours | Manual only
```

Stored in `config.sync_frequency`. `DispatchIntegrationSyncsJob` reads this before
dispatching.

#### 5.5 — Audit log tab

Show `integration_sync_logs` in a paginated table with filters by date/status.

---

### Phase 6 — Remove Bell Hardcoding (1 day)

Once Phase 3 is live and tested:

1. Remove `BELL_TEAM_ID`, `BELL_ISO15143_*`, `BELL_SSO_*`, `BELL_HISTORICAL_*` from `.env`
   and `config/integrations.php` — all credentials now live in the `integrations` table
2. Retire `SyncBellFleetDataJob`, `SyncBellHistoricalDataJob`, and all per-signal jobs
   from `routes/console.php` — replaced by `DispatchIntegrationSyncsJob`
3. The `BellBackfillHistoryCommand` (`bell:backfill-history`) is kept as a one-time utility
   but no longer needed for new setups

Bell data continues to work for AfriCoal because an `Integration` row exists for their
team with `provider = 'bell'` and the correct (now encrypted) credentials.

---

## Database Changes Summary

| Migration | Description |
|---|---|
| `alter_integrations_encrypt_credentials` | Re-encrypt existing `credentials` column |
| `create_integration_sync_logs` | Per-sync audit trail |
| `add_integration_id_to_machines` | `machines.integration_id FK → integrations.id` (nullable) — traces which integration created a machine |

No changes to `bell_equipment_*` tables — they continue to store Bell-specific telemetry.

---

## Security Checklist

- [ ] `credentials` column encrypted with `encrypted:array` cast (APP_KEY rotation = re-encrypt)
- [ ] `credentials` in `$hidden` — never serialised in API responses
- [ ] `CredentialExposureTest` — automated test confirms field absent from all HTTP responses
- [ ] Team isolation enforced at `upsertMachine` level — `(team_id, external_id)` lookup
- [ ] Webhook endpoint validates `webhook_secret` HMAC before processing any payload
- [ ] Rate limiting on the "Test Connection" action (prevent credential stuffing / API abuse)
- [ ] Integration rows are soft-deleted, never hard-deleted — audit trail preserved

---

## Files to Create / Modify

### New files
```
app/Contracts/ManufacturerAdapterInterface.php
app/Services/Integration/AdapterRegistry.php
app/Services/Integration/BellEquipmentAdapter.php        (refactored from BellIso15143Service)
app/Services/Integration/GenericOemAdapter.php           (fallback for unconfigured providers)
app/Jobs/DispatchIntegrationSyncsJob.php
app/Jobs/SyncIntegrationJob.php
app/Jobs/TestIntegrationConnectionJob.php
app/Models/IntegrationSyncLog.php
app/Console/Commands/EncryptExistingIntegrationCredentials.php
database/migrations/..._encrypt_integration_credentials.php
database/migrations/..._create_integration_sync_logs.php
database/migrations/..._add_integration_id_to_machines.php
tests/Feature/IntegrationManagerTest.php
tests/Feature/SyncIntegrationJobTest.php
tests/Feature/CredentialExposureTest.php
```

### Modified files
```
app/Models/Integration.php                   — encrypted cast, $hidden, relations
app/Livewire/IntegrationManager.php          — liveTestConnection, dynamic schema, sync logs
app/Services/Integration/IntegrationService.php  — delegate to AdapterRegistry
resources/views/livewire/integration-manager.blade.php  — dynamic form, detail panel, audit log
routes/console.php                           — add DispatchIntegrationSyncsJob, remove Bell-only jobs
```

---

## Suggested Build Order

1. Phase 1 — Encrypt credentials (security baseline, must be first)
2. Phase 2 — Adapter interface + Bell adapter (no UX change, enables everything else)
3. Phase 3 — Per-integration sync pipeline (background workers)
4. Phase 4 — Machine auto-assignment (data integrity)
5. Phase 5 — Integrations page overhaul (visible UX improvement)
6. Phase 6 — Remove Bell hardcoding (cleanup)

Each phase is independently deployable and testable.

---

## Definition of Done

- Any account admin can open the Integrations page, select a manufacturer, enter
  their API credentials, click Test, and have machines appear in their Fleet page
  within one sync cycle — **without any developer intervention**
- Credentials are encrypted in the database and never appear in any HTTP response or log
- Each integration only sees and syncs machines belonging to its own team
- The Bell integration for AfriCoal SA Operations continues to work unchanged,
  now driven by a database row instead of env vars
- All existing tests pass; new tests cover the full happy path and failure modes
  for each new component
