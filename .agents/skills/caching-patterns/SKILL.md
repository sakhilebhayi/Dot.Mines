---
name: caching-patterns
description: >
  Mines platform caching and Redis optimisation patterns. Use when: implementing query caching
  using QueryCacheService, designing Redis key naming conventions, invalidating cache on model
  events, setting TTL policies for different data types, preventing cache stampedes, auditing
  cache hit rates, or optimising Livewire component data caching.
argument-hint: 'Describe the caching or Redis optimisation task you need help with'
esm-layer: operational
esm-feeds-to:
  - performance-agent
  - fleet-intelligence-agent
  - production-patterns
esm-consumes-from:
  - data-quality-patterns
---

# Caching Patterns

## When to Use

- Adding query caching to an expensive or frequently called computation
- Designing or reviewing Redis key names for a new feature
- Implementing cache invalidation when a model is updated or deleted
- Setting appropriate TTLs for telemetry, aggregate, or config data
- Preventing cache stampedes on high-traffic endpoints
- Auditing cache hit rates or Redis memory pressure
- Caching Livewire component data that doesn't need real-time updates

---

## QueryCacheService API

```php
use App\Services\QueryCacheService;

$cache = app(QueryCacheService::class);

// Cache a query result
$result = $cache->remember(
    key: "team:{$team->id}:fuel:summary:monthly",
    ttl: 300,   // seconds
    callback: fn() => FuelManagementService::getMonthlySummary($team),
);

// Invalidate a cache key
$cache->forget("team:{$team->id}:fuel:summary:monthly");

// Cache with tags (allows group invalidation)
$result = $cache->tags(["team:{$team->id}", "fuel"])
    ->remember("fuel:summary:monthly", 300, fn() => ...);

// Invalidate all fuel cache for a team
$cache->tags(["team:{$team->id}", "fuel"])->flush();
```

---

## Key Naming Convention

```
Format: {domain}:{team_id}:{entity}:{qualifier}:{period}

Examples:
  team:42:production:summary:2026-06          — monthly production summary
  team:42:machines:list                       — team machine list
  team:42:fleet:health:score                  — fleet health aggregate
  team:42:fuel:tank:5:level                   — specific tank level
  team:42:geofence:list                       — team geofence list
  global:subscription:plans                   — subscription plans (no team scope)
  machine:103:health:score                    — individual machine health score
```

**NEVER** include user IDs in team-scoped cache keys — cache is per-team, not per-user.

---

## TTL Reference by Data Type

```
Machine GPS position       30s   — near real-time required
Sensor readings            60s   — acceptable slight lag
Machine health score       120s  — recalculated every 5 min anyway
Alert count (badge)        15s   — users expect near-real-time
Production summary         300s  — 5 minutes is fine for aggregates
Fuel tank level            60s   — balances freshness vs load
Geofence list              600s  — rarely changes
Mine area list             600s
Subscription plans         3600s — changes very rarely
Report status              30s   — polled while pending
```

---

## Cache Invalidation on Model Events

Use Observer or Model event hooks to invalidate related cache:

```php
// In FuelTank Observer (or model boot)
protected static function booted(): void
{
    static::updated(function (FuelTank $tank) {
        Cache::forget("team:{$tank->team_id}:fuel:tank:{$tank->id}:level");
        Cache::tags(["team:{$tank->team_id}", "fuel"])->flush();
    });
}
```

---

## Stampede Prevention

For expensive computations hit by many simultaneous requests:

```php
use Illuminate\Support\Facades\Cache;

// Lock-based atomic cache population
$value = Cache::lock("lock:team:{$team->id}:health:score", 10)
    ->block(5, function () use ($team) {
        return Cache::remember(
            "team:{$team->id}:fleet:health:score",
            120,
            fn() => expensive_calculation($team),
        );
    });
```

---

## Livewire Component Caching Pattern

For Livewire components that display aggregate data that doesn't need real-time updates:

```php
public function getProductionSummaryProperty(): array
{
    return Cache::remember(
        "team:{$this->team->id}:production:summary:" . now()->format('Y-m'),
        300,
        fn() => $this->productionService->getMonthlySummary($this->team),
    );
}
// Expose as: $this->productionSummary (Livewire computed property)
```

---

## Pattern — Cache Test

```php
#[Test]
public function fuel_summary_is_cached_on_second_request(): void
{
    Cache::spy();

    $user = $this->adminUser();

    // First request
    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/fuel/summary')
        ->assertOk();

    // Second request should hit cache
    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/fuel/summary')
        ->assertOk();

    Cache::shouldHaveReceived('remember')->once(); // only computed once
}

#[Test]
public function cache_is_invalidated_on_tank_update(): void
{
    $user = $this->adminUser();
    $tank = FuelTank::factory()->create(['team_id' => $user->current_team_id]);

    Cache::put("team:{$user->current_team_id}:fuel:tank:{$tank->id}:level", 1000, 60);

    $tank->update(['current_level' => 750]);

    $this->assertNull(Cache::get("team:{$user->current_team_id}:fuel:tank:{$tank->id}:level"));
}
```

---

## Redis Health Checks

```bash
# Check Redis connection
php artisan tinker --execute 'Cache::put("health_check", true, 5); var_dump(Cache::get("health_check"));'

# Check Redis memory usage
php artisan tinker --execute '
$info = Redis::info("memory");
echo "Used: " . round($info["used_memory"] / 1024 / 1024, 2) . "MB\n";
echo "Peak: " . round($info["used_memory_peak"] / 1024 / 1024, 2) . "MB\n";
'

# Cache hit rate
php artisan tinker --execute '
$stats = Redis::info("stats");
$hits  = $stats["keyspace_hits"];
$miss  = $stats["keyspace_misses"];
$rate  = $hits + $miss > 0 ? round($hits / ($hits + $miss) * 100, 2) : 0;
echo "Hit rate: {$rate}%";
'
```
