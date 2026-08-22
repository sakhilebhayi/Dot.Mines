# Operational Data Accuracy — Design

**Date:** 2026-08-22 · **Status:** Approved (audit findings + slice plan approved via Q&A) · **Brief:** real-time operational data accuracy program ("do not make Dot.Mines look real-time while the underlying data is fake or stale").

## Audit findings (evidence-backed, production)

1. **Root cause of missing production data — one XPath assumption.**
   `BellService::fetchTimeSeries()` extracts readings only from nodes carrying
   `ReadingUTC`/`Timestamp`/`DateTimeUTC` *attributes* with `Value`/`Reading`/`Amount`
   *attribute* values. Bell's cumulative production series respond element-style:
   `<CumulativeLoadCount datetime="2026-08-21T22:00:00Z"><Count>16491</Count></CumulativeLoadCount>`.
   Locations happen to be attribute-style — so the map works while every production
   sync silently parses **zero** readings. The entire downstream pipeline
   (TelemetryProductionCalculator daily deltas, timezone grouping, deduped telemetry
   ProductionRecords with loads/cycles metadata, baselines, backfill) already exists
   and is starved. `production_records` in prod: **0 rows**.
2. **Bell's counter cadence:** one cumulative snapshot per machine per day at
   22:00Z (midnight SAST). Time-series = daily history; **intra-day "today"** must
   come from the live fleet-snapshot counters already stored per metric row in
   `machine_metrics.raw_data` (`load_count`, `cumulative_payload` — verified live,
   e.g. 7,930 lifetime loads / 348,020,303 kg on machine 26).
3. **Timezone:** both teams have `timezone = UTC`. With Bell snapshotting at
   22:00Z, UTC day-grouping books each day's production onto the wrong SAST day
   (brief §17). Additionally a snapshot at exactly 00:00:00 local time closes the
   day that *ended*, so midnight-boundary readings must attribute to the previous
   local day.
4. **Zero configured geodata:** `mine_areas`, `geofences`, `routes`, `waypoints`,
   `production_targets` are all empty; all 27 machines have `mine_area_id = NULL`.
   Area rollups, loading/dumping detection, dispatch-zone states and road-following
   movement have no configured data behind them.
5. **API-health truth:** `integrations.last_error` holds a stale location failure
   while `status = connected` — success paths don't clear it (known cosmetic bug),
   and there is no admin-visible API health (brief §21).
6. **Fabrication risk:** `GenerateRoadsPathCoordinates` writes MachineMetric rows
   with `rand()` speeds and synthesized coordinates (manual command; brief §6
   violation if ever run against live data). To be quarantined.
7. Sync scheduling is healthy: `integrations:sync-due` every 5 min, Bell every
   15 min, deep sync hourly; 1,713 metric rows in the last 24 h; Pusher push to
   the map verified live earlier.

## Decisions (user-approved)

- Fix the parser first; trust and exercise the existing pipeline (no rebuild).
- Set the operating team's timezone to `Africa/Johannesburg`; timezone drives all
  day/shift boundaries. **Order matters:** set timezone before the first backfill
  so records book to correct SAST days from the start.
- Zones/roads: never invented. Derive haul-road polylines and candidate
  loading/dumping zones from the fleet's own GPS history as *suggestions the user
  confirms*; manual drawing remains available.
- Slice order P1–P7 approved.

## Slices

- **P1 — Ingestion fix + API-health truth.** Extend `fetchTimeSeries()` reading
  extraction to element-style readings (case-insensitive `datetime` attribute;
  child elements folded into `attributes`; value from `Count`/`Payload`/first
  numeric child when no value attribute). Regression fixtures copied from the
  real response shapes for both cumulative series. Clear `last_error` on every
  successful sync path. Verify on prod: trigger deep sync, `production_records`
  populates with real backfilled days, Production page shows them.
- **P2 — SAST day integrity.** Team timezone → `Africa/Johannesburg` (prod data
  update before first backfill); midnight-snapshot attribution rule in
  `TelemetryProductionCalculator` (a reading at exactly 00:00:00 local closes the
  previous day) pinned by tests; shift/day boundary tests per §17.
- **P3 — One source of truth + freshness.** `OperationalSnapshotService`: per
  machine — status, position, speed/heading, fuel, engine hours, loads/cycles
  today (live fleet counter − last daily close), payload today (tonnes), last
  telemetry timestamps. All pages read machine state from it (§14). Freshness
  labels (Live/Recent/Stale + "data may be outdated" when the API stops
  responding) on every operational view (§8). Missing values render
  "Awaiting API data" — never fabricated (§6).
- **P4 — Surfaces.** Production page (today/target/variance, by-machine,
  by-material where real data exists, trend/shift/daily/weekly/monthly), Fleet
  cards + Machine Detail (identity/current state/production/activity/freshness),
  Dashboard Dispatch states derived only from real telemetry (moving/idle/
  loading-zone states only where zones exist) — all from the P3 snapshot.
- **P5 — Reconciliation + monitoring.** Reconciliation checks (machine → fleet →
  production cycles and payload sums; discrepancies surfaced, not hidden, §18);
  admin API-health panel (last success/failure, response time, counts received/
  rejected, freshness, §21); duplicate/out-of-order/partial-response idempotency
  tests (§19).
- **P6 — Movement realism.** GPS-history polylines for replay/live trails;
  visual interpolation only between two real points and labeled as such (§11);
  GPS-derived road/zone suggestions with user confirmation (§9/§10); quarantine
  `GenerateRoadsPathCoordinates`' fabrication paths.
- **P7 — ThreeUI welcome page** (§25), only after P1–P6 verified: lightweight,
  progressive enhancement, no heavy WebGL.

## Verification loop (every slice)

INSPECT → TRACE → FIX → real API sync → DB check → UI check → machine check →
map check → dispatch check → freshness check → reconciliation → failure tests →
repeat (§23). Definition of Done is the brief's §24 checklist.
