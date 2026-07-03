# Changelog — Engineering History

> Auto-updated after every significant engineering change.
> Format: `[YYYY-MM-DD] Category — Description`

---

## 2026-07-02 — Live Platform Audit (platform-governor-agent run)

### Code Quality — Fixed
- **PHPStan**: Resolved 3 errors → **0 errors**
  - `ProductionDashboard::getOemKpiSummaryProperty()`: `$startDate`/`$endDate` are nullable strings; added `?? today()->toDateString()` guard
  - `MachineFaultCodeService`: collection template type resolved by replacing `concat()`/`merge()` with explicit `push()` loop
- **Pint**: Fixed style violations in **11 files** (services and Livewire components modified in recent sessions): `unary_operator_spaces`, `binary_operator_spaces`, `braces_position`, `phpdoc_align`, `not_operator_with_successor_space`, `single_line_empty_body`, `blank_line_before_statement`

### Audit Findings Recorded
- **CI confirmed**: 12 GitHub Actions workflows present — previous scorecard score (60) was wrong; revised to **72**
- **Tests**: 373/385 failing — root cause: `database.sqlite` is 12KB/empty, `php artisan migrate` never run. Added as KI-007 (Critical)
- **OEM coupling**: Zero `BellEquipment*` imports remaining in Livewire components (grep confirmed clean)
- **Fat components**: Fleet.php (891 lines), Feed.php (778), Reports.php (749) — TD-003 confirmed
- **New service tests**: `MachineKpiService`, `MachineFaultCodeService`, `MachineTelemetryService` all have 0 test files — added as TD-015, TD-016
- **Database indexes**: `machine_metrics` has **0 indexes** — DB-003 priority elevated to 🔴
- **Security**: `SENTRY_DSN` blank, `SESSION_SECURE_COOKIE=false`, no `/health` route — all confirmed open

### Documents Updated
- `PLATFORM_SCORECARD.md` — scores revised (Testing: 58→45, DevOps: 60→72, Overall: 74→75); score history entry added
- `KNOWN_ISSUES.md` — KI-007 (test DB), KI-008 (PHPStan), KI-009 (Pint) added; KI-008/009 immediately resolved
- `TECHNICAL_DEBT.md` — TD-015 (test DB), TD-016 (service tests) added; PHPStan and Pint resolved entries added
- `CHANGELOG.md` — this entry
- `memory.md` — session summary appended

---

## 2026-07-02 — Integration Agnosticism & Platform Governance

### Architecture
- **Extracted `MachineKpiService`** — integration-agnostic production KPI aggregator; sources Bell daily KPIs + `machine_metrics`; replaces direct `BellEquipmentDailyKpi` queries in Livewire components
- **Extracted `MachineFaultCodeService`** — integration-agnostic fault code aggregator; sources Bell caution codes; structured for future OEM additions without UI changes
- **Refactored `MachineTelemetryService`** — now resolves from three priority tiers: Bell ISO 15143-3 → `machine_metrics` → `Machine` model fields; every machine returns a meaningful snapshot regardless of OEM
- Added `telemetry_source` field to every snapshot (`'bell'` | `'machine_metric'` | `'machine'` | `'none'`)

### Livewire Components
- **`Dashboard`** — removed direct `BellEquipment*` model imports; now uses `MachineKpiService::getTodayKpis()` for loads/payload stats
- **`ProductionDashboard`** — replaced Bell-specific KPI query with `MachineKpiService::getDailyKpiSummary()`; computed property renamed `oemKpiSummary`
- **`FuelManagement`** — replaced direct `BellEquipmentCurrentStatus` JOIN query with `MachineTelemetryService::forMachines()` for live fuel levels
- **`MaintenanceDashboard`** — replaced direct `BellEquipmentCautionCode` query with `MachineFaultCodeService::getActiveFaultCodes()`; fault code table now shows `source` column

### Views
- Removed "Bell Equipment" / "Bell OEM" labels from all generic telemetry UI sections; replaced with manufacturer-neutral text ("OEM Telemetry", "Live Machine Fuel Levels", etc.)
- Maintenance fault codes table: added "Source" column so operators know which OEM reported each code

### Documentation
- Created `docs/continuous-improvement/` directory with full enterprise governance framework (16 documents)
- Updated `docs/AGENTS.md` with continuous improvement framework references
- Updated `docs/memory.md` with this session entry

---

## 2026-07-02 — Live Telemetry & Fleet Card Revamp

### Telemetry Pipeline
- **`MachineTelemetryService`** — removed cumulative idle-ratio check (was causing all running machines to show as "Idling"); engine running now defaults to `working` or `travelling` based on live speed
- Increased offline threshold from 15 → 30 minutes (prevents false-offline on any missed 15-min sync cycle)
- **`BellIso15143Service::bridgeToMachine()`** — replaced `engine_running → active/idle` with full status derivation: offline (>30min stale) / idle (engine off) / active (engine running); preserves `maintenance` status
- Added `MachineStatusChanged` broadcast when machine status changes on sync
- **`BellHistoricalTelemetryService::syncLocations()`** — now updates `machines.last_location_*` and dispatches `MachineLocationUpdated` on every 5-minute GPS record (previously only ISO15143-3 snapshot updated machine position)
- `MachineLocationUpdated` broadcast payload enriched: `speed`, `bearing`, `status` added alongside lat/lng

### Live Map
- Added Echo listener for `machine.location.updated` events — markers move in real time without page reload
- Added `wire:poll.{{ $pollInterval }}s` on live map container driven by `BELL_UI_POLL_SECONDS` config
- Added `animateMarkerTo()` — smooth ease-in-out cubic marker animation via `requestAnimationFrame`
- Added `updateMachinePositions()` — incremental update (animate existing, add new, remove stale) replaces destructive clear+rebuild
- Added `LiveMap::refreshMachinePositions()` method for poll-triggered server-side refresh

### Fleet Page
- Reverted machine cards to clean original design: only Engine Hours + Fuel % as progress bars
- Removed from cards: speed badge, odometer tile, loads tile, cycle breakdown bar, last sync text, Bell live status labels
- Status badge restored to simple Active / Idle / Maintenance from `$machine->status`

### Configuration
- Added `bell_polling` config block: `location_interval_seconds`, `snapshot_interval_seconds`, `lookback_multiplier`, `ui_poll_seconds`
- Added env vars: `BELL_LOCATION_POLL_SECONDS`, `BELL_SNAPSHOT_POLL_SECONDS`, `BELL_LOCATION_LOOKBACK_MULTIPLIER`, `BELL_UI_POLL_SECONDS`
- Created `bell:watch-locations` artisan command for sub-minute GPS polling (Supervisor managed)
- Scheduler now dynamically routes location sync: ≥300s → `everyFiveMinutes`, ≥120s → `everyTwoMinutes`, ≥60s → `everyMinute`, <60s → `bell:watch-locations --once` safety-net
- `SyncBellLocationsJob` lookback window now config-driven: `interval × lookback_multiplier`

### Cross-Platform Data Wiring
- **Dashboard** — Bell live stats visible for all teams with OEM equipment (running machines, avg fuel %, loads/payload today); previously only the Bell team ID saw this
- **Production Dashboard** — OEM KPI banner (loads, payload, utilisation) from `bell_equipment_daily_kpis`
- **Fuel Management** — live Bell fuel % progress bars per machine alongside physical tank data
- **Maintenance Dashboard** — active Bell fault codes table with severity badges
- **Haul Dispatch** — live Bell fuel % and speed override dispatch record values
- **`BellIso15143Service`** — every sync now calls `syncAlertsFromCautionCodes()`: creates Alert records from active fault codes; auto-resolves when cleared

---

## 2026-07-01 — Bell Integration, Self-Service Integrations & Enterprise Audit

### Bell Equipment Integration
- Fixed `BELL_SSO_CLIENT_SECRET` blank env var causing silent token failures
- Fixed `MachineLocationUpdated::dispatch` inside `DB::transaction` — Pusher failure was rolling back Bell sync data
- Fixed `BellHistoricalTelemetryService` to use `serial_number` as URL identifier (equipment_id returned 400)
- Added `syncRange(from, to)` method + `bell:backfill-history` Artisan command
- Full historical backfill: May 1 → July 1 2026 (6,121+ records across all 13 signal tables)
- Consolidated all Bell sync jobs to 5-minute intervals
- Updated `MachineDetail` with full Bell telemetry: current status, caution codes, fuel/hours/load/DEF/regen charts, location history

### Self-Service OEM Integration System
- Encrypted `credentials` column on `Integration` model (AES-256 via `APP_KEY`)
- Created `ManufacturerAdapterInterface` contract + `AdapterRegistry` (15 providers registered)
- Created `BellEquipmentAdapter` + `GenericOemAdapter`
- Created `SyncIntegrationJob` + `DispatchIntegrationSyncsJob` + `TestIntegrationConnectionJob`
- Overhauled `IntegrationManager` Livewire component: dynamic credential form, sync history, real-time status

### Security
- Fixed npm vulnerabilities: `form-data`, `shell-quote`, `ws` — 0 remaining
- Fixed Composer vulnerabilities: guzzle 7.13.1, psr7 2.12.3, jmespath 2.9.1
- Fixed 14 PHPStan level-max errors

### Reports
- Enhanced Bell Operations tab: 6-metric fleet KPIs, per-machine table, monthly comparison, caution code frequency, CSV export

### Enterprise Audit Infrastructure
- Created `docs/ENTERPRISE-AUDIT-PLAN.md`
- Created `platform_error_logs` table + `PlatformErrorLog` model + `ErrorLoggerService`
- Updated `bootstrap/app.php` catch-all to log all unhandled exceptions
- Created branded `resources/views/errors/platform.blade.php` (shows `error_ref` UUID)
