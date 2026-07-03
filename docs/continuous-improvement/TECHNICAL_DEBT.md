# Technical Debt Register

> Track all known technical debt, refactoring opportunities, and cleanup tasks.
> Items are resolved as sprint capacity allows (target: 10% per sprint).

---

## Critical Debt

### TD-001 — SQLite in Production
- **Location**: `.env` `DB_CONNECTION=sqlite`
- **Risk**: SQLite does not support concurrent writes, JSON operators across all queries, or partitioning. **Will break at production scale.**
- **Recommendation**: Migrate to MySQL 8+ or PostgreSQL 15+ before launch.
- **Effort**: 3 days (schema compatibility + query review)
- **Status**: 🔴 Open

### TD-002 — No CI Pipeline
- **Location**: `.github/workflows/` (absent)
- **Risk**: No automated gate before merge. Regressions can reach production undetected.
- **Recommendation**: Add GitHub Actions: PHPStan → PHPUnit → Pint → Deployment
- **Effort**: 2 days
- **Status**: 🔴 Open

### TD-015 — Test Database Never Migrated
- **Location**: `database/database.sqlite` (12KB, empty)
- **Risk**: Critical — 373/385 tests fail; CI is not green
- **Recommendation**: `php artisan migrate` then re-run test suite. Add migration step to CI setup.
- **Effort**: 0.5 days
- **Status**: 🔴 Open

### TD-016 — Three New Services Have Zero Test Coverage
- **Location**: `app/Services/MachineKpiService.php`, `app/Services/MachineFaultCodeService.php`, `app/Services/MachineTelemetryService.php`
- **Risk**: High — core abstraction layer untested
- **Recommendation**: Write unit tests for each service (happy path + null/empty input)
- **Effort**: 3 days
- **Status**: 🔴 Open

---

### TD-003 — Fat Livewire Components
- **Location**: `app/Livewire/Fleet.php` (891 lines), `Reports.php`, `MaintenanceDashboard.php` (503 lines)
- **Risk**: Hard to test, review, and extend. Violates Single Responsibility Principle.
- **Recommendation**: Extract business logic into services; split large components into parent + child components
- **Effort**: 5 days
- **Status**: 🟡 Planned

### TD-004 — `Reports.php` Still Contains Direct Bell Model Queries
- **Location**: `app/Livewire/Reports.php` lines 611–660
- **Risk**: Integration-specific coupling in UI layer; breaks the OEM agnosticism pattern
- **Recommendation**: Route through `MachineKpiService` and `MachineFaultCodeService`
- **Effort**: 1 day
- **Status**: 🟡 Planned

### TD-005 — No Health Check Endpoint
- **Location**: `routes/api.php` (missing `/health` route)
- **Risk**: Load balancers, container orchestrators, and uptime monitors cannot verify application health
- **Recommendation**: Add `/health` returning DB status, queue status, cache status (consider `laravel/health` package)
- **Effort**: 0.5 days
- **Status**: 🟡 Planned

### TD-006 — Inconsistent Error Response Format
- **Location**: Various API controllers
- **Risk**: Clients receive mixed error formats (JSON, HTML, redirect) depending on the endpoint
- **Recommendation**: Centralise in `Handler.php`; always return `{error: bool, message: string, code: string, data: null}`
- **Effort**: 2 days
- **Status**: 🟡 Planned

### TD-007 — `MachineDetail.php` Still Contains Direct Bell Model Imports
- **Location**: `app/Livewire/MachineDetail.php` lines 7–40
- **Risk**: Breaks OEM agnosticism for the Fleet Details page
- **Recommendation**: Route Bell-specific data through `MachineTelemetryService` + new detail-level service
- **Effort**: 2 days
- **Status**: 🟡 Planned

---

## Medium Priority

### TD-008 — Livewire Component State Not Persisted Across Navigation
- **Location**: Various Livewire components
- **Risk**: Filter and sort state is lost on browser back navigation
- **Recommendation**: Persist state in query string via Livewire `#[Url]` attribute
- **Effort**: 2 days
- **Status**: 🔵 Backlog

### TD-009 — No Formal DTO / Value Object Pattern
- **Location**: Telemetry snapshot arrays throughout the codebase
- **Risk**: Array shape contracts are implicit; PHPStan can only partially verify them
- **Recommendation**: Create `TelemetrySnapshot` readonly DTO class
- **Effort**: 2 days
- **Status**: 🔵 Backlog

### TD-010 — `BellTeamInsightsService` Locked to Team ID 4
- **Location**: `app/Livewire/Dashboard.php` line ~140 + `config/integrations.php`
- **Risk**: Fragile hard-coded team ID for Bell overview. Breaks if team is recreated.
- **Recommendation**: Move to a team capability flag on the `Team` model
- **Effort**: 1 day
- **Status**: 🔵 Backlog

### TD-011 — PHPStan Baseline Contains Pre-Existing Errors
- **Location**: `phpstan-baseline.neon`
- **Risk**: Baseline masks real errors over time as the codebase grows
- **Recommendation**: Resolve baseline errors one sprint at a time; target empty baseline
- **Effort**: 3 days total
- **Status**: 🔵 Backlog

### TD-012 — Queue Connection is `sync` in Development
- **Location**: `.env` `QUEUE_CONNECTION=sync`
- **Risk**: Delayed jobs (`->delay()`) run immediately in sync mode; behaviour differs from production
- **Recommendation**: Use `database` driver in dev; `redis` in production
- **Effort**: 0.5 days
- **Status**: 🔵 Backlog

---

## Low Priority / Cleanup

### TD-013 — Commented-Out Code Blocks
- **Location**: Multiple blade views (dashboard.blade.php, fleet.blade.php)
- **Risk**: Dead code increases cognitive load
- **Recommendation**: Remove all commented-out HTML blocks; use git history for reference
- **Effort**: 0.5 days
- **Status**: 🔵 Backlog

### TD-014 — `fleet-enhanced.blade.php` Redundant File
- **Location**: `resources/views/livewire/fleet-enhanced.blade.php`
- **Risk**: Out-of-date duplicate view; confusing for new developers
- **Recommendation**: Delete the file
- **Effort**: 0.25 days
- **Status**: 🔵 Backlog

---

## Resolved

| ID | Item | Resolved | Session |
|---|---|---|---|
| — | Bell-specific queries in Dashboard, ProductionDashboard, FuelManagement, MaintenanceDashboard | 2026-07-02 | Integration Agnosticism |
| — | Cumulative idle ratio false "Idling" machine state | 2026-07-02 | Telemetry pipeline |
| — | PHPStan 3 errors (ProductionDashboard null-safe, MachineFaultCodeService collection) | 2026-07-02 | Audit run |
| — | Pint style violations in 11 files | 2026-07-02 | Audit run |
| — | npm vulnerabilities (`form-data`, `shell-quote`, `ws`) | 2026-07-01 | Security patches |
| — | Composer vulnerabilities (guzzle, psr7, jmespath) | 2026-07-01 | Security patches |
| — | Integration credentials stored in plaintext | 2026-07-01 | Credential encryption |
