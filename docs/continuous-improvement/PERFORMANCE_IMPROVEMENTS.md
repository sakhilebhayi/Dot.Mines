# Performance Improvements

> Track query optimisation, caching, queue tuning, and load testing results.

---

## Current Performance Score: 74/100

---

## Database Performance

### PERF-001 — Missing Composite Index on `bell_equipment_location_history`
- **Impact**: High — queried on every telemetry render + live map refresh
- **Finding**: `(equipment_key, recorded_at DESC)` queries are frequent; no composite index confirmed.
- **Fix**:
  ```sql
  CREATE INDEX idx_bell_location_eq_time
    ON bell_equipment_location_history (equipment_key, recorded_at DESC);
  ```
- **Effort**: 0.5 days (add migration)
- **Status**: 🔴 Open

### PERF-002 — `MachineTelemetryService` makes two bulk queries per render
- **Impact**: Medium — acceptable for current fleet size; may degrade at 100+ machines
- **Finding**: Two queries (Bell equipment + location history) + 1 fallback chain per machine set on every Livewire render.
- **Fix**: Cache the telemetry map for 30 seconds per team using `Cache::remember()`.
- **Effort**: 1 day
- **Status**: 🟡 Planned

### PERF-003 — No Slow Query Logging
- **Impact**: High — blind to query performance degradation
- **Fix**: Enable `DB_SLOW_QUERY_THRESHOLD_MS=500` + `DB::listen()` in `AppServiceProvider`. Log to Pulse.
- **Effort**: 0.5 days
- **Status**: 🔴 Open

### PERF-004 — `bell_equipment_daily_kpis` Daily SUM Query
- **Impact**: Medium — scans all rows for date range when fleet grows
- **Fix**: Add partial index on `kpi_date`; consider pre-aggregated monthly rollup table.
- **Effort**: 1 day
- **Status**: 🔵 Backlog

---

## Queue Performance

### PERF-005 — Bell Sync Jobs May Pile Up Under High Load
- **Impact**: High — `SyncBellFleetDataJob` uses `ShouldBeUnique` but other signal jobs don't
- **Fix**: Add `ShouldBeUnique` to all Bell sync jobs; configure `uniqueFor()` TTL.
- **Effort**: 0.5 days
- **Status**: 🟡 Planned

### PERF-006 — No Queue Depth Monitoring
- **Impact**: Medium
- **Fix**: Add Horizon queue depth alerts; alert on `integrations` queue depth > 50 jobs.
- **Effort**: 1 day
- **Status**: 🔵 Backlog

---

## Cache Strategy

### PERF-007 — Dashboard Stats Re-computed on Every Poll
- **Impact**: Medium — `loadDashboardData()` runs every 10 seconds via `wire:poll.10s`
- **Finding**: Machine counts and telemetry stats are not cached between polls.
- **Fix**: Use `QueryCacheService::dashboardStats()` (already exists) for all stats; cache telemetry for 30s.
- **Effort**: 1 day
- **Status**: 🟡 Planned

### PERF-008 — No Redis in Development/Staging
- **Impact**: Medium — cache falls back to database driver (much slower)
- **Fix**: Use Redis for cache, sessions, and queues in all environments.
- **Effort**: 0.5 days
- **Status**: 🔵 Backlog

---

## Frontend Performance

### PERF-009 — Live Map Re-renders All Markers on Every wire:poll
- **Impact**: Medium — replaced with `updateMachinePositions()` incremental update (2026-07-02)
- **Status**: ✅ Resolved

### PERF-010 — No Frontend Bundle Analysis
- **Impact**: Low
- **Fix**: Run `npm run build -- --report` to identify large chunks. Consider lazy-loading the Leaflet map.
- **Effort**: 1 day
- **Status**: 🔵 Backlog

---

## Load Testing Benchmarks

| Test | Result | Target | Status |
|---|---|---|---|
| API P95 latency | Not measured | < 200ms | 🔴 Not done |
| Fleet page load (50 machines) | Not measured | < 500ms | 🔴 Not done |
| Bell sync 100 machines / 5-min cycle | Not measured | < 30s wall time | 🔴 Not done |
| Live map 100 markers + Reverb | Not measured | Smooth (60fps) | 🔴 Not done |
| Concurrent users (100) | Not measured | No degradation | 🔴 Not done |

**Next step**: Set up `k6` scripts targeting the production URL. Run weekly in CI.
