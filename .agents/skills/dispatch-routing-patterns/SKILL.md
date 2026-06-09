---
name: dispatch-routing-patterns
description: >
  Mines platform haul dispatch and route optimisation patterns. Use when: creating or updating
  HaulDispatch records, debugging cycle time calculations, working with Route/Waypoint models,
  using RoutePlanningService, building HaulDispatchDashboard or RoutePlanning Livewire components,
  investigating RouteSpeedMonitoringJob, or understanding the HaulDispatchUpdated event.
argument-hint: 'Describe the dispatch or routing task you need help with'
esm-layer: operational
esm-feeds-to:
  - production-patterns
  - fleet-intelligence-agent
  - esg-reporting-patterns
esm-consumes-from:
  - live-map-patterns
  - machine-health-patterns
  - mine-area-patterns
  - production-patterns
---

# Dispatch Routing Patterns

## When to Use

- Creating or querying HaulDispatch / Route / Waypoint records
- Debugging cycle time or idle time calculations
- Working with RoutePlanningService for route optimisation
- Writing tests for dispatch API endpoints
- Building or debugging HaulDispatchDashboard / RoutePlanning components
- Understanding RouteSpeedMonitoringJob throttle logic

---

## Core Models

```
HaulDispatch   — one dispatched assignment per machine per cycle
Route          — defined haul path between origin and destination
Waypoint       — ordered GPS coordinate on a Route
MachineAreaAssignment — which machine is assigned to which mine area
```

---

## Dispatch Cycle States

```
pending    → dispatched → in_transit → at_dump → returning → completed
                                     ↑
                              (idle detected here triggers alert)
```

---

## Key KPIs

```
Cycle time         = completed_at - dispatched_at (seconds → minutes)
Travel efficiency  = (expected_duration / actual_duration) * 100
Queue delay        = time spent in 'pending' state before dispatch
Idle time          = time at_dump beyond expected loading window
Dispatch util %    = (active_cycles / total_possible_cycles) * 100
```

---

## Pattern — Creating a Haul Dispatch

```php
// Via RoutePlanningService
use App\Services\RoutePlanningService;

$service = app(RoutePlanningService::class);
$dispatch = $service->dispatch(
    machine: $machine,
    route: $route,
    operator: $operator,
    mineArea: $loadArea,
);
// Returns HaulDispatch with status='pending'
// Fires HaulDispatchUpdated::dispatch($dispatch)
```

---

## Pattern — Completing a Dispatch Cycle

```php
// API
PATCH /api/v1/dispatch/{dispatch}/complete
{
    "completed_at": "2026-06-09T14:32:00Z",
    "payload_tons": 85.5,
    "dump_area_id": 4
}
// Observer updates cycle_time_seconds automatically
```

---

## Pattern — Route Speed Monitoring

```
RouteSpeedMonitoringJob runs every minute
  → queries in_transit dispatches
  → compares current GPS speed to route speed_limit
  → if exceeded → Alert created (type: 'overspeed', level: 'high')
  → sends notification via NotificationService
```

---

## Pattern — Dispatch Test

```php
#[Test]
public function dispatch_cycle_time_is_calculated_on_completion(): void
{
    $user     = $this->adminUser();
    $dispatch = HaulDispatch::factory()->create([
        'team_id'       => $user->current_team_id,
        'status'        => 'in_transit',
        'dispatched_at' => now()->subMinutes(45),
    ]);

    $this->actingAs($user, 'sanctum')
        ->patchJson("/api/v1/dispatch/{$dispatch->id}/complete", [
            'completed_at' => now()->toIso8601String(),
            'payload_tons' => 80.0,
        ])
        ->assertOk();

    $this->assertNotNull($dispatch->fresh()->cycle_time_seconds);
    $this->assertSame('completed', $dispatch->fresh()->status);
}

#[Test]
public function dispatch_is_isolated_between_teams(): void
{
    $userA = $this->adminUser();
    $userB = $this->createUserInSeparateTeam();

    HaulDispatch::factory()->create(['team_id' => $userA->current_team_id]);

    $this->actingAs($userB, 'sanctum')
        ->getJson('/api/v1/dispatch')
        ->assertJsonCount(0, 'data');
}
```

---

## HaulDispatchDashboard Livewire Component

```
app/Livewire/HaulDispatchDashboard.php

Key state:
  $activeDispatches   — collection of in-flight dispatches
  $pendingQueue       — machines waiting for dispatch
  $cycleMetrics       — real-time KPI summary

Real-time update:
  Listens to HaulDispatchUpdated via Echo private channel
  → #[On('echo-private:team.{teamId}.dispatch,dispatch.updated')]
  → refreshes $activeDispatches on change
```

---

## RoutePlanningService API

```php
$service = app(App\Services\RoutePlanningService::class);

// Get all active routes for a team
$routes = $service->getActiveRoutes($team);

// Calculate estimated cycle time for a route
$estimatedMinutes = $service->estimateCycleTime($route, $machine);

// Get bottleneck analysis
$bottlenecks = $service->getBottleneckAnalysis($team, $startDate, $endDate);
// Returns: [['route_id' => int, 'avg_delay_minutes' => float, 'occurrences' => int]]
```

---

## ESM Intelligence Handoff

When cycle times exceed target by > 15%:
- **production-patterns**: flag as productivity risk
- **mine-area-patterns**: check dump zone congestion
- **machine-health-patterns**: verify machine is not degraded
- **esg-reporting-patterns**: excess idle time = excess fuel burn

---

## Commands Reference

```bash
# Run dispatch tests
php artisan test --compact tests/Feature/HaulDispatchTest.php

# Check active dispatches
php artisan tinker --execute '
App\Models\HaulDispatch::where("status","in_transit")
    ->with("machine")
    ->get(["id","machine_id","dispatched_at","status"]);
'

# Analyse cycle time averages
php artisan tinker --execute '
$service = app(App\Services\RoutePlanningService::class);
$team    = App\Models\Team::first();
$bottlenecks = $service->getBottleneckAnalysis($team, now()->subDays(7), now());
foreach ($bottlenecks as $b) {
    echo "Route {$b["route_id"]}: avg delay {$b["avg_delay_minutes"]}min ({$b["occurrences"]} trips)\n";
}
'
```
