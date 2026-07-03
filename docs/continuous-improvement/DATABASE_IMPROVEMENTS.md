# Database Improvements

> Track schema improvements, index recommendations, archiving, and optimisation.

---

## Current Database Score: 80/100

---

## Critical

### DB-001 — SQLite in Use (Development and Potentially Production)
- **Risk**: Critical
- **Finding**: `.env` shows `DB_CONNECTION=sqlite`. SQLite lacks concurrent write support, JSON column operators, full-text search, and partitioning.
- **Migration Plan**:
  1. Add `DB_CONNECTION=mysql` / `pgsql` to production `.env`
  2. Run `php artisan schema:dump` to get SQL baseline
  3. Test all migrations against MySQL 8+
  4. Update CI to use MySQL service container
- **Effort**: 3 days
- **Status**: 🔴 Open

---

## Index Recommendations

### DB-002 — `bell_equipment_location_history` Composite Index
```sql
-- Current: only single-column index on equipment_key
-- Recommended: composite covering the most common query pattern
CREATE INDEX idx_bell_loc_eq_time
  ON bell_equipment_location_history (equipment_key, recorded_at DESC);
```
- **Impact**: Eliminates full-table scans on the densest Bell history table
- **Status**: 🔴 Open

### DB-003 — `machine_metrics` Composite Index
```sql
CREATE INDEX idx_machine_metrics_machine_time
  ON machine_metrics (machine_id, recorded_at DESC);
```
- **Impact**: Speeds up `MachineTelemetryService` fallback queries
- **Status**: 🟡 Planned

### DB-004 — `bell_equipment_daily_kpis` Date Index
```sql
CREATE INDEX idx_bell_kpi_date
  ON bell_equipment_daily_kpis (equipment_key, kpi_date);
```
- **Status**: 🟡 Planned

### DB-005 — `alerts` Covering Index for Active Alert Lookups
```sql
CREATE INDEX idx_alerts_team_status_type
  ON alerts (team_id, status, type, created_at DESC);
```
- **Impact**: Dashboard active alert count query is hot path
- **Status**: 🔴 Open

---

## Archiving Strategy

### DB-006 — `bell_equipment_location_history` Growth Management
- **Finding**: Each 5-minute sync adds 1 record per machine. At 100 machines, that's ~28,800 rows/day.
- **Retention Policy**: Keep 24 months online; archive to S3 Glacier after 24 months.
- **Implementation**: Extend `ArchiveOldMetricsJob` to cover Bell location history.
- **Effort**: 2 days
- **Status**: 🟡 Planned

### DB-007 — `platform_error_logs` Retention
- **Retention Policy**: Keep 90 days online; purge or archive after.
- **Implementation**: Add `PurgeOldPlatformErrorLogsJob` to weekly schedule.
- **Effort**: 0.5 days
- **Status**: 🔵 Backlog

---

## Schema Improvements

### DB-008 — `machines` Table Missing `integration_source` Column
- **Finding**: No way to know which OEM integration manages a given machine (Bell, Volvo, generic, etc.)
- **Recommendation**: Add `integration_source` enum column to `machines` table.
- **SQL**: `ALTER TABLE machines ADD COLUMN integration_source VARCHAR(32) NULL;`
- **Effort**: 0.5 days
- **Status**: 🔵 Backlog

### DB-009 — Foreign Key Cascade Audit
- **Finding**: Some Bell tables may be missing `ON DELETE CASCADE` for equipment deregistration.
- **Recommendation**: Audit all Bell FKs; ensure deregistering a `BellEquipment` cascades to all history tables.
- **Effort**: 1 day
- **Status**: 🔵 Backlog

---

## Partitioning Candidates

Once on MySQL/PostgreSQL, these tables should be partitioned by date:

| Table | Partition Key | Strategy |
|---|---|---|
| `bell_equipment_location_history` | `recorded_at` | RANGE by month |
| `bell_equipment_telemetry_history` | `telemetry_date` | RANGE by month |
| `machine_metrics` | `recorded_at` | RANGE by month |
| `platform_error_logs` | `created_at` | RANGE by quarter |

Partitioning defers the need for archival jobs and keeps query plans on small partitions.
