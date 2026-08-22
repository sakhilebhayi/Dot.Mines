# Hybrid Data Architecture — MySQL Authority, Browser Local Layer, Real-Time Push

**Date:** 2026-08-22
**Status:** Approved (DB engine: MySQL migration; realtime: managed Pusher-protocol service; offline: reads first, write queue contract-only)

## Why this design differs from the original brief

The brief assumes MySQL/Postgres is already the server source of truth and SQLite is a
candidate local cache. The audit found the opposite shape:

- **Production's authoritative DB is SQLite** (single file on Verpex shared cPanel).
  Local dev is PostgreSQL; tests are SQLite in-memory.
- **Reverb cannot run in production.** Shared hosting has no systemd/supervisor; zero
  Reverb processes exist. Real-time today is polling (`wire:poll` 15–120s, `.visible`).
- **The client is server-rendered Livewire**, not an SPA. The only "local" store that
  survives pit connectivity is the browser. Browsers do not run SQLite; IndexedDB fills
  that role (explicitly permitted by brief §5).
- **Already built** by prior programs: version-keyed server cache (QueryCacheService),
  honest freshness timestamps and stale badges, incremental map marker movement
  (RealtimeMapManager), visible-only polling, Bell ingestion via queued jobs with
  retry/backoff, skeleton loaders, dispatch board. This program builds the missing
  layers; it does not rebuild those.
- **AVA integration does not exist** in the codebase. The pipeline is designed so AVA
  slots in beside Bell when it exists; nothing is built for it now.

## Architecture (adapted)

```
Bell (later AVA) ──> Integration services / queued jobs ──> MySQL (SOURCE OF TRUTH)
                                                              │
                                        ┌─────────────────────┴───────────────┐
                                        ▼                                     ▼
                            Domain events → managed                 Sync API (versioned,
                            Pusher-protocol websocket                incremental, tombstones)
                                        │                                     │
                                        ▼                                     ▼
                            ┌──────────────────────────────────────────────────────┐
                            │ Browser: Livewire UI (online, unchanged)             │
                            │ + service worker (app shell)                         │
                            │ + IndexedDB behind LocalDataService (cache/offline)  │
                            │ + connectivity state machine (pill + banners)        │
                            └──────────────────────────────────────────────────────┘
```

Rules enforced throughout (brief §36): MySQL is the only authority; IndexedDB is a
downstream representation; the browser never receives credentials or cross-team data;
every sync/broadcast path authorizes server-side from the session, never from
client-supplied team/site ids.

## Slice 0 — MySQL becomes the production source of truth

- `mysql` connection in `config/database.php` is re-keyed to dedicated
  `MYSQL_HOST/MYSQL_PORT/MYSQL_DATABASE/MYSQL_USERNAME/MYSQL_PASSWORD` env vars (falling
  back to the stock `DB_*` keys). This matters: during rehearsal and cutover the live
  `sqlite` connection and the target `mysql` connection must coexist in one `.env`, and
  the stock config makes both read `DB_DATABASE`.
- New artisan command `db:engine-copy {--from=} {--to=} {--fresh} {--verify-only}`:
  runs `migrate --database=<to> --force` (schema from migrations, never guessed from
  SQLite), copies data table-by-table in chunks with FK checks disabled on the target
  during copy, skips transient tables (`cache`, `cache_locks`), then verifies per-table
  row counts plus sum(id)/max(updated_at) checksums and prints a report. Refuses a
  non-empty target without `--fresh`.
- CI tests exercise the command sqlite→sqlite (second connection) for ordering,
  chunking, verification, and refusal logic. The engine-specific rehearsal runs **on the
  Verpex host** against the real (empty) cPanel MySQL DB with real production data,
  without touching the live connection — the dress rehearsal is the real environment.
- Cutover: maintenance mode → fresh copy → verify → user pastes `DB_*` lines into prod
  `.env` (user-side, paste-ready) → smoke tests → up. Rollback at any point = revert the
  `.env` lines; the SQLite file is never modified.
- User-side prerequisite: create MySQL database + user in cPanel.

## Slice 1 — Incremental sync contract (server)

- `sync_versions` sequence table + `HasSyncVersion` model concern: every write to a
  synced entity stamps a monotonic global `sync_version` (observer). Hard deletes write
  a `sync_tombstones` row (entity_type, entity_id, team_id, sync_version).
- Synced scopes (deliberately minimal, brief §22): `fleet` (machine id/name/type/status/
  allocation_state/last position/engine hours/fuel/payload/last_seen_at), `production`
  (daily summaries + today), `notifications`, `reference` (sites/mine areas id+name+geometry).
- `GET /api/v1/sync?since=<version>&scopes=a,b` → `{version, server_time, changes: {scope: [...]},
  deleted: [...]}` . Sanctum session auth; team scoping from the authenticated user only;
  per-scope authorization; hard cap on page size with `has_more` + repeat-until-drained.
- Tests: tenant isolation (two teams, zero leakage), unauthorized scope, cursor
  monotonicity, tombstones, pagination.

## Slice 2 — Browser local layer

- `resources/js/local/` module: `localData.js` (IndexedDB wrapper; stores:
  `fleet_state`, `positions`, `production_summary`, `notifications`, `reference`,
  `sync_meta`), `syncClient.js` (pull loop against the sync API using the stored
  cursor), `connectivity.js` (state machine: ONLINE / CONNECTING / OFFLINE / SYNCING /
  SYNC_ERROR, driven by navigator.onLine + fetch outcomes + socket state).
- Service worker: precache app shell (built assets, offline fallback page); network-first
  for HTML with cached-shell fallback; **never** caches authenticated API responses in
  Cache API. Offline, a read-only snapshot view renders from IndexedDB with
  "Showing cached data — last updated HH:MM"; online, Livewire is untouched (no
  double-rendering).
- Global connectivity pill (Alpine store + one Blade component) in both layouts.
- Freshness policies (§23): positions 60s, machine status 120s, today's production 300s,
  reference 24h; stale stores render with the existing stale badge pattern.
- Security (§20/§22): no tokens, secrets, or financial/billing data in IndexedDB;
  storage is cleared on logout.

## Slice 3 — Real-time push

- Managed Pusher-protocol service (user creates account; keys pasted user-side, Paystack
  pattern). Config tidied to Laravel 12 `BROADCAST_CONNECTION` (the legacy
  `BROADCAST_DRIVER` read noted in phpunit.xml gets fixed).
- Private channel `team.{teamId}` authorized by membership; existing 10 events reviewed,
  broadcasting added where missing on the Bell ingestion path (locations, status,
  alerts, production updates).
- Client: Echo (already wired in bootstrap.js) → on event: update IndexedDB + patch the
  live UI (markers via RealtimeMapManager; counters via Livewire dispatch), stamp
  freshness. On disconnect: connectivity state machine drops to fallback polling (the
  existing wire:poll cadence); on reconnect: catch-up pull from the sync cursor, then
  minimal polling (§15).

## Slice 4 — States, observability, health

- Consolidate state UI into one component set: `SyncStatus`, `OfflineBanner`,
  `LastUpdated`, `ErrorState`+retry (several exist; converge, don't duplicate — §24).
- Structured logs: sync_started/completed/failed, socket connected/disconnected,
  offline/online detected — with team_id, user_id, scope, cursor, duration; never
  secrets (§32).
- Admin `/system-health`: DB, Bell API freshness + breaker state, queue depth + last
  drain, broadcast status, sync cursor age (§33).

## Slice 5 — Offline write queue (contract only, implementation deferred)

- IndexedDB `sync_queue` store: `{id, idempotency_key, action, entity_type, entity_id,
  payload, created_at, attempts, last_attempt_at, status(pending|processing|completed|
  failed|conflict), error}`.
- Server endpoint contract: POST with idempotency key; server validates auth + business
  rules + `client_version`/`client_updated_at`; per-entity conflict strategy —
  machine status: server-newer wins; operational notes: merge; production adjustments:
  explicit server validation; financial records: never offline-writable at all.
- First candidate entity when implemented: production-loss records.

## Testing & rollout

Each slice: feature tests (tenant isolation is mandatory in every sync/broadcast test),
pint + phpstan/psalm, PR, merge on green, deploy, live verification (house pattern).
Performance baseline captured before Slice 2 (TTFB, time-to-first-useful-data on
dashboard/fleet/map) and re-measured after Slices 2–3.

## Out of scope

AVA (doesn't exist), loads/cycles events for models that don't exist, native mobile
apps, WASM SQLite in the browser, moving off Verpex.
