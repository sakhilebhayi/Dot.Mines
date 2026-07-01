# Session Memory Log

This file records a summary of every development session — what was built, changed, or fixed.
Each entry is added after code is updated or upgraded.

---

## Session: 2026-07-01 — Bell Integration, Self-Service Integrations & Enterprise Audit

### Bell Equipment Integration
- Fixed `BELL_SSO_CLIENT_SECRET` blank env var (was silently breaking all token requests)
- Fixed `MachineLocationUpdated::dispatch` inside a `DB::transaction` — Pusher failure was rolling back all Bell sync data; wrapped in try-catch
- Fixed `BellHistoricalTelemetryService` to use `serial_number` as the URL identifier for the historical API (equipment_id returned 400; serial number returns 200)
- Added `syncRange(from, to)` method to `BellHistoricalTelemetryService` for explicit date-range backfills
- Created `bell:backfill-history` Artisan command (`--from`, `--to`, `--chunk-days` options)
- Ran full historical backfill: May 1 → July 1 2026 (6,121+ records stored across all 13 signal tables)
- Consolidated all Bell sync jobs to 5-minute intervals (previously 15 min / hourly)
- Removed Bell fleet snapshot card from dashboard and Bell fleet intelligence card from fleet page
- Updated `MachineDetail` Livewire component and view with full Bell telemetry sections: current status, active caution codes, fuel/operating hours/load/DEF/regen charts, location history table
- Enhanced Reports Bell Operations tab: 6-metric fleet KPIs, per-machine KPI table with utilisation% and fuel efficiency (L/load), monthly comparison (May/Jun/Jul 2026), caution code frequency report, CSV export
- Locked Bell integration to AfriCoal SA Operations (team ID 4) only via `BELL_TEAM_ID` config — no other team sees Bell data

### Self-Service OEM Integration System
- Encrypted `credentials` column on `Integration` model using `encrypted:array` cast (AES-256 via `APP_KEY`)
- Added `credentials` to `$hidden` — never serialised in API responses
- Created `integration:encrypt-credentials` Artisan command to migrate existing plaintext rows
- Created `ManufacturerAdapterInterface` contract (`testConnection`, `fetchFleet`, `fetchHistory`, `credentialSchema`, `displayName`, `icon`)
- Created `AdapterRegistry` — resolves provider slug to adapter class; exposes credential schemas for dynamic form rendering; 15 providers registered
- Created `BellEquipmentAdapter` — wraps existing Bell services using Integration row credentials instead of `.env` vars
- Created `GenericOemAdapter` — works for any REST JSON API out of the box (Bearer/Basic auth)
- Created `IntegrationSyncLog` model + `integration_sync_logs` migration (per-sync audit trail)
- Created `DispatchIntegrationSyncsJob` — runs every 5 min, dispatches `SyncIntegrationJob` for each connected + due integration respecting `sync_frequency`
- Created `SyncIntegrationJob` — fetches fleet via adapter, upserts machines scoped to team, writes sync log, updates integration status
- Created `TestIntegrationConnectionJob` — async credential test after save; updates status to connected/disconnected
- Overhauled `IntegrationManager` Livewire component: dynamic credential form, Save & Test Connection flow, provider grid, sync frequency selector, sync now + re-test buttons, sync history panel, 10-second polling
- Overhauled `integration-manager.blade.php` view to match new component
- Added `DispatchIntegrationSyncsJob` to 5-minute schedule in `routes/console.php`
- Tests: `IntegrationCredentialExposureTest` (DB encryption, decryption, `$hidden`) + `SyncIntegrationJobTest` (machine upsert, team isolation, dispatch logic)

### Security Patches
- Fixed npm vulnerabilities: `form-data` (CRLF injection), `shell-quote` (critical), `ws` (DoS) — 0 vulnerabilities remaining
- Fixed Composer vulnerabilities: `guzzlehttp/guzzle` 7.13.1, `guzzlehttp/psr7` 2.12.3, `mtdowling/jmespath.php` 2.9.1
- Fixed 14 PHPStan level-max errors: type narrowing, null safety, resource guards, PHPDoc return types, `BellEquipment` property annotation

### Enterprise Readiness Audit
- Created `docs/ENTERPRISE-AUDIT-PLAN.md` — 10-part audit plan covering error logbook, exception handler, API security, OWASP Top 10, frontend audit, queue audit, DB integrity, enterprise readiness, CI/CD checklist, 20-item execution table
- Created `platform_error_logs` table + migration — stores every unhandled exception with UUID `error_id`, level, category, PII-stripped context, stack trace (hidden)
- Created `PlatformErrorLog` model with `$hidden = ['stack_trace']`, array context cast, `scopeUnresolved` / `scopeOfLevel`
- Created `ErrorLoggerService` — strips 12 sensitive field patterns, truncates stack traces to 10KB, never crashes the app (double try-catch), exposes UUID `error_ref` to users
- Updated `bootstrap/app.php` catch-all: logs unexpected exceptions to `platform_error_logs` in all environments; branded error page in production
- Updated `AppServiceProvider` — `JobFailed` event now also writes to `platform_error_logs`
- Created `resources/views/errors/platform.blade.php` — branded error page showing status, message, and `error_ref` UUID for 5xx

### Documentation & Repo Hygiene
- Moved all root `.md` files to `docs/` (except `README.md` which was restored to root)
- Created `docs/SELF-SERVICE-INTEGRATIONS-PLAN.md`
- Created `docs/ENTERPRISE-AUDIT-PLAN.md`
- `README.md` at project root

### Test suite at end of session
- **385 tests, 856 assertions — all passing**
- PHPStan level max — 0 errors
- Pint — passed
- Composer audit — 0 vulnerabilities
- npm audit — 0 vulnerabilities

---

## How to add a new session entry

Append a new `## Session: YYYY-MM-DD — Short Title` block above following the same format:
- Date and one-line title
- Grouped bullet points per area of change
- Test suite status at the end of the session

