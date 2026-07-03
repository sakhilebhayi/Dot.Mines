# Known Issues

> Active bugs, workarounds, priorities, and status. Closed when resolved.

---

## Open Issues

### KI-001 — `view:cache` Fails When Blade References Undefined Variables
- **Severity**: Medium
- **Description**: After the last session, `php artisan view:cache` exits with code 1. Likely a Blade variable reference issue introduced in a recently modified view.
- **Workaround**: Run `php artisan view:clear` before `view:cache`; or use `--no-scripts` in deployment.
- **Owner**: Engineering
- **Status**: 🔴 Open
- **Discovered**: 2026-07-02

### KI-002 — `machine.status` Can Drift From Live Bell State
- **Severity**: Low
- **Description**: `machine.status` is updated every Bell sync (every 5 min). Between syncs, the status badge on Fleet cards may show a stale state. The live telemetry map uses `MachineTelemetryService` which is based on `updated_date`, so the map is more accurate.
- **Workaround**: The Live Map shows the most accurate real-time status. Fleet cards are eventually consistent (up to 5 min stale).
- **Owner**: Engineering
- **Status**: 🟡 Accepted — By Design
- **Discovered**: 2026-07-02

### KI-003 — `bell:watch-locations` Not Yet Deployed Under Supervisor
- **Severity**: Medium
- **Description**: The `bell:watch-locations` artisan command for sub-minute GPS polling exists but is not yet configured in Supervisor or the deployment runbook.
- **Workaround**: The Laravel scheduler handles every-minute fallback via `bell:watch-locations --once`.
- **Owner**: DevOps
- **Status**: 🟡 Planned
- **Discovered**: 2026-07-02

### KI-004 — No Uptime Monitoring Configured
- **Severity**: High
- **Description**: If the application goes down, there is no external alert. Downtime is only discovered when a user reports it.
- **Workaround**: Manual monitoring.
- **Owner**: DevOps
- **Status**: 🔴 Open
- **Discovered**: 2026-07-02

### KI-005 — Sentry DSN is Blank
- **Severity**: High
- **Description**: `SENTRY_DSN=` is not configured. All unhandled exceptions are logged to `platform_error_logs` in the database only. No real-time alerting on critical errors.
- **Workaround**: Monitor `platform_error_logs` table manually.
- **Owner**: Engineering
- **Status**: 🔴 Open
- **Discovered**: 2026-07-02

### KI-006 — SQLite Used in Development (DB Portability Risk)
- **Severity**: Critical (pre-production)
- **Description**: SQLite does not support all MySQL/PostgreSQL features used or planned. Application behaviour may differ between development and production.
- **Workaround**: Test critical migrations against MySQL before deploying.
- **Owner**: Engineering
- **Status**: 🔴 Open — Must resolve before production launch
- **Discovered**: 2026-07-02

### KI-007 — Test Database Not Migrated (373/385 Tests Failing)
- **Severity**: Critical
- **Description**: `database/database.sqlite` is 12KB with only `sqlite_sequence` table. All database-dependent tests fail because `php artisan migrate` has never been run in this environment.
- **Fix**: `php artisan migrate && php artisan test --no-coverage --compact`
- **Owner**: Engineering
- **Status**: 🔴 Open — must fix before CI can be green
- **Discovered**: 2026-07-02 (live audit run)

---

## Resolved Issues

| ID | Summary | Resolved | Session |
|---|---|---|---|
| — | Bell sync rolling back data due to `MachineLocationUpdated::dispatch` inside `DB::transaction` | 2026-07-01 | Bell integration |
| — | `BellHistoricalTelemetryService` using wrong URL segment (equipment_id → serial_number) | 2026-07-01 | Bell integration |
| — | Bell SSO token request failing silently (blank `BELL_SSO_CLIENT_SECRET`) | 2026-07-01 | Bell integration |
| — | npm CVEs: form-data, shell-quote, ws | 2026-07-01 | Security |
| — | Composer CVEs: guzzle, psr7, jmespath | 2026-07-01 | Security |
| — | Integration credentials stored as plaintext JSON | 2026-07-01 | Security |
| — | All machines showing as "Idling" due to cumulative idle-ratio false positive | 2026-07-02 | Telemetry |
| — | Fleet cards showing Bell-specific telemetry (odometer, loads, speed, last sync) | 2026-07-02 | Fleet cards |
| — | Bell model imports leaking into Livewire UI layer (OEM coupling) | 2026-07-02 | Agnosticism |
| KI-008 | PHPStan: 3 errors in ProductionDashboard + MachineFaultCodeService | 2026-07-02 | Audit run |
| KI-009 | Pint: style violations in 11 files (services + Livewire components) | 2026-07-02 | Audit run |
