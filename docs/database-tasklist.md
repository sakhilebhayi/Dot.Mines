# Database Migration Tasklist: SQLite → MySQL

## Context

The application currently runs on **SQLite**, which uses file-level locking and
supports only one concurrent writer. This is the primary blocker for scaling beyond
~1,000 concurrent users. MySQL with Redis-backed sessions/cache/queue resolves this.

The items below are ordered by dependency. Complete them in sequence.

---

## Phase 1 — Pre-migration preparation

### 1.1 Provision MySQL instance

- [ ] Create a MySQL 8.x instance (RDS, PlanetScale, or self-hosted)
- [ ] Create the application database and a dedicated app user:
  ```sql
  CREATE DATABASE mines CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
  CREATE USER 'mines_app'@'%' IDENTIFIED BY '<strong-password>';
  GRANT ALL PRIVILEGES ON mines.* TO 'mines_app'@'%';
  FLUSH PRIVILEGES;
  ```
- [ ] Verify the app server can reach the MySQL host on port 3306
- [ ] Enable SSL on the MySQL connection (set `MYSQL_ATTR_SSL_CA` in `config/database.php` `options`)

### 1.2 Provision Redis

Redis is already configured in `.env` (`REDIS_CLIENT=phpredis`, `REDIS_HOST=127.0.0.1`).

- [ ] Confirm Redis is running on the production server (`redis-cli ping`)
- [ ] If using a remote Redis (Upstash, ElastiCache, etc.), update `REDIS_HOST`, `REDIS_PASSWORD`, `REDIS_PORT` in the production `.env`
- [ ] Install the PHP Redis extension if not present: `apt install php-redis` or use the `predis/predis` package

### 1.3 Back up the SQLite database

```bash
# Take a timestamped backup before any changes
cp /home/infodotc/mines.infodot.co.za/mines/database/database.sqlite \
   /home/infodotc/mines.infodot.co.za/mines/database/database.sqlite.bak-$(date +%Y%m%d%H%M%S)

# Also run the S3 backup script for an off-site copy
./scripts/backup-db.sh
```

---

## Phase 2 — Code compatibility fixes (do before switching driver)

### 2.1 Fix `ILIKE` → `LIKE` in `Reports.php`

`ILIKE` is PostgreSQL/SQLite syntax. MySQL uses `LIKE` (case-insensitive by default on `utf8mb4_unicode_ci`).

**File:** `app/Livewire/Reports.php` ~line 582

```diff
- $q->whereRaw('body ILIKE ?', [$safe])
-     ->orWhereHas('allComments', fn ($c) => $c->whereRaw('body ILIKE ?', [$safe]));
+ $q->whereRaw('body LIKE ?', [$safe])
+     ->orWhereHas('allComments', fn ($c) => $c->whereRaw('body LIKE ?', [$safe]));
```

- [ ] Apply the fix above and run `vendor/bin/pint --dirty --format agent app/Livewire/Reports.php`

### 2.2 Fix `PRAGMA` statements in `make_center_coordinates_nullable` migration

`PRAGMA foreign_keys=off/on` is SQLite-only. This migration has already run in SQLite
and will run again from scratch on MySQL — the `PRAGMA` block must be guarded.

**File:** `database/migrations/2026_02_16_010000_make_center_coordinates_nullable.php`

Wrap each `PRAGMA` block so it only runs on SQLite:

```php
if ($driver === 'sqlite') {
    DB::statement('PRAGMA foreign_keys=off;');
    // ... column changes ...
    DB::statement('PRAGMA foreign_keys=on;');
} else {
    // MySQL: alter columns normally — no pragma needed
    // ... column changes (same Schema::table block without the PRAGMA calls) ...
}
```

- [ ] Update the migration file as shown above
- [ ] Run `php artisan test --compact` to confirm no test regressions

### 2.3 Verify `add_performance_indexes` migration on MySQL

`database/migrations/2026_01_20_181914_add_performance_indexes.php` uses a raw
`sqlite_master` query inside the `$indexExists` helper. This migration already ran
on the SQLite database, but when re-run on MySQL from scratch it will skip the SQLite
branch and fall through correctly — **no change needed**. Confirm by dry-running:

```bash
# Point DB at MySQL in a staging env, then:
php artisan migrate --pretend
```

- [ ] Confirm `--pretend` output shows no `sqlite_master` errors on MySQL

### 2.4 Fix `Cache::flush()` stampede in `QueryCacheService`

When any machine is updated, `Cache::flush()` currently nukes the entire cache. On
Redis this is safe but wasteful; on a shared Redis it clears all tenants' data.

**File:** `app/Services/QueryCacheService.php` — `invalidateMachine()` method:

```php
public static function invalidateMachine(int $machineId, int $teamId): void
{
    Cache::forget("machine_details_{$machineId}");
    Cache::forget("machines_list_{$teamId}_*"); // needs tagged cache — see below
    Cache::forget("dashboard_stats_{$teamId}");
}
```

To clear all `machines_list_{teamId}_*` variations without `flush()`, enable Redis
cache tags in `config/cache.php`:

```php
// config/cache.php
'redis' => [
    'driver' => 'redis',
    'connection' => 'cache',
    'lock_connection' => 'default',
],
```

Then use tags in `QueryCacheService::machineList()`:

```php
return Cache::tags(["team_{$teamId}_machines"])->remember(...);
```

And in `invalidateMachine()`:

```php
Cache::tags(["team_{$teamId}_machines"])->flush();
```

- [ ] Implement cache tags for machine list as described above
- [ ] Remove the bare `Cache::flush()` call

---

## Phase 3 — Switch drivers

### 3.1 Update `.env` for MySQL + Redis

```dotenv
DB_CONNECTION=mysql
DB_HOST=<mysql-host>
DB_PORT=3306
DB_DATABASE=mines
DB_USERNAME=mines_app
DB_PASSWORD=<password>

CACHE_STORE=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis
```

- [ ] Update production `.env` with MySQL credentials
- [ ] Update `CACHE_STORE`, `SESSION_DRIVER`, `QUEUE_CONNECTION` to `redis`
- [ ] Set `SESSION_SECURE_COOKIE=true` (production HTTPS)
- [ ] Set `APP_DEBUG=false`

### 3.2 Run fresh migrations on MySQL

On a **new empty MySQL database**, run all 68 migrations from scratch:

```bash
php artisan migrate --no-interaction
```

This is the recommended approach over data export/import (see Phase 4 for data migration).

- [ ] Confirm `php artisan migrate` completes with no errors on MySQL
- [ ] Verify row counts and spot-check critical tables after import

### 3.3 Clear and recache config

```bash
php artisan config:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

- [ ] Run all four cache commands after switching drivers

---

## Phase 4 — Data migration (existing SQLite data)

If existing production data must be preserved:

### 4.1 Export SQLite to SQL dump

```bash
# Install sqlite3 if needed: apt install sqlite3
sqlite3 /home/infodotc/mines.infodot.co.za/mines/database/database.sqlite \
  .dump > /tmp/sqlite_dump.sql
```

### 4.2 Convert dump to MySQL-compatible SQL

SQLite dumps contain SQLite-specific syntax. Use the conversion tool:

```bash
# Option A: use pgloader (also handles SQLite → MySQL)
pgloader sqlite:///path/to/database.sqlite mysql://mines_app:<pw>@host/mines

# Option B: manual conversion steps
# 1. Remove "BEGIN TRANSACTION" / "COMMIT"
# 2. Replace `INTEGER PRIMARY KEY` with `INT AUTO_INCREMENT PRIMARY KEY`
# 3. Replace `AUTOINCREMENT` with `AUTO_INCREMENT`
# 4. Remove `PRAGMA` statements
# 5. Replace `TEXT` JSON columns with `JSON` type where appropriate
# 6. Fix boolean 0/1 — MySQL uses TINYINT(1), values are compatible as-is
```

- [ ] Choose and run the conversion method
- [ ] Verify row counts match between SQLite and MySQL after import

### 4.3 Verify JSON columns

These columns use `json`/`array` casts and must be valid JSON after import:

| Model | Column |
|---|---|
| `Report` | `filters` |
| `Alert` | `metadata` |
| `Shift` | `previous_assignments`, `productivity_metrics`, `performance_summary`, `metadata` |
| `MaintenanceSchedule` | `required_parts`, `required_tools` |
| `AiRecommendationAction` | `recommendation`, `performance_impact` |
| `HaulDispatch` | `path_coordinates`, `metadata` |
| `AIAnalysisSession` | `input_parameters`, `results` |
| `AIAgent` | `configuration`, `capabilities` |
| `Geofence` | `coordinates` |

```bash
# Spot-check JSON validity in MySQL
SELECT id, JSON_VALID(filters) FROM reports WHERE JSON_VALID(filters) = 0 LIMIT 5;
```

- [ ] Run JSON validity checks on all columns listed above

---

## Phase 5 — Extend caching to high-read pages

`QueryCacheService` is only used in `Dashboard.php`. These pages query on every render
and should also cache:

| Component | Query to cache | Suggested TTL |
|---|---|---|
| `LiveMap` | Machine locations + statuses | 15 seconds |
| `AIAnalytics` | AI insight aggregates | 5 minutes |
| `MineAreaDetail` | Mine area machine lists | 1 minute |
| `FleetMovementReplay` | Path coordinates for a replay | 5 minutes (keyed by machine + date range) |
| `FuelManagement` | Monthly allocation aggregates | 2 minutes |

- [ ] Add `QueryCacheService` cache wrappers to the components above
- [ ] Ensure cache is invalidated via model Observers when underlying data changes

---

## Phase 6 — Post-migration validation

### 6.1 Run full test suite against MySQL

```bash
# Point phpunit.xml or .env.testing at MySQL, then:
php artisan test --compact
```

- [ ] All tests pass on MySQL

### 6.2 Load test

Use `k6`, `ab`, or `wrk` to simulate concurrent users before going live:

```bash
# Example with wrk: 50 concurrent connections for 30 seconds
wrk -t4 -c50 -d30s https://mines.infodot.co.za/dashboard
```

Target: P95 response time < 500ms at 100 concurrent users.

- [ ] Run load test and confirm no lock timeouts or queue pile-up

### 6.3 Monitor slow queries

After switching to MySQL, enable the slow query log for 24 hours:

```sql
SET GLOBAL slow_query_log = 'ON';
SET GLOBAL long_query_time = 0.5;  -- log queries slower than 500ms
```

- [ ] Review slow query log after first 24 hours of production traffic
- [ ] Add any missing indexes identified by the slow query log

---

## Summary of issues found and their status

| Issue | Severity | Status |
|---|---|---|
| SQLite as production DB | 🔴 Critical | Addressed in this plan |
| Cache/Session/Queue on database driver | 🔴 Critical | Phase 3.1 |
| `ILIKE` syntax incompatible with MySQL | 🔴 Must fix | Phase 2.1 |
| `PRAGMA` in migration | 🟡 Will error on MySQL | Phase 2.2 |
| `Cache::flush()` stampede in `invalidateMachine()` | 🟡 High | Phase 2.4 |
| N+1 `calculateMachinePerformance()` query loop | ✅ Fixed | Done |
| Missing `activity_logs` indexes | ✅ Fixed | Done (migration `2026_06_03_194115`) |
| `sortBy` SQL injection in 5 Livewire components | ✅ Fixed | Done |
| `dispatchBrowserEvent` Livewire v2 API calls | ✅ Fixed | Done |
| `QueryCacheService` used in only 1 component | 🟡 Moderate | Phase 5 |
| `APP_DEBUG=true` in production | 🟡 Security | Phase 3.1 |
