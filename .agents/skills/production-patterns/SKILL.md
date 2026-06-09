---
name: production-patterns
description: >
  Mines platform production intelligence patterns. Use when: recording production data,
  calculating BCM/tonnage KPIs, debugging production dashboard figures, working with
  ProductionTarget or ProductionForecast, writing production API tests, analysing shift
  production performance, or building production-related Livewire components.
argument-hint: 'Describe the production task you need help with'
esm-layer: operational
esm-feeds-to:
  - financial-intelligence-agent
  - esg-reporting-patterns
  - fleet-intelligence-agent
  - dispatch-routing-patterns
esm-consumes-from:
  - shift-patterns
  - dispatch-routing-patterns
  - machine-health-patterns
  - data-quality-patterns
  - mine-area-patterns
---

# Production Patterns

## When to Use

- Recording or querying ProductionRecord / ProductionTarget / ProductionForecast
- Calculating BCM, tonnage, or equipment productivity KPIs
- Writing tests for production API endpoints or the ProductionDashboard component
- Debugging why production figures don't match targets
- Wiring production data into the Financial or ESG pipelines

---

## Core Models

```
ProductionRecord    — individual production entry per machine per shift
ProductionTarget    — target BCM/tons per team per period
ProductionForecast  — AI-generated forecast (see ai-agent-patterns skill)
```

---

## Key KPI Formulas

```
BCM/hour       = total_bcm / total_operating_hours
Tons/hour      = total_tons / total_operating_hours
Achievement %  = (actual_bcm / target_bcm) * 100
Forecast error = abs(forecast_bcm - actual_bcm) / actual_bcm * 100
```

---

## Pattern — Recording a Production Entry

```php
// Via API
POST /api/v1/production/records
{
    "machine_id": 3,
    "shift_id": 12,
    "mine_area_id": 2,
    "bcm": 450.75,
    "tons": 1150.20,
    "loads": 18,
    "operating_hours": 7.5,
    "recorded_at": "2026-06-09T14:00:00Z"
}
```

---

## Pattern — Querying Production for a Period

```php
use App\Models\ProductionRecord;

// Daily production by machine
$records = ProductionRecord::query()
    ->whereBetween('recorded_at', [$startDate, $endDate])
    ->where('team_id', $team->id)
    ->with(['machine', 'shift', 'mineArea'])
    ->selectRaw('machine_id, SUM(bcm) as total_bcm, SUM(tons) as total_tons, SUM(operating_hours) as total_hours')
    ->groupBy('machine_id')
    ->get();
```

---

## Pattern — Target Variance Check

```php
use App\Models\ProductionTarget;
use App\Models\ProductionRecord;

$target = ProductionTarget::where('team_id', $team->id)
    ->where('period', now()->format('Y-m'))
    ->first();

$actual = ProductionRecord::where('team_id', $team->id)
    ->whereBetween('recorded_at', [now()->startOfMonth(), now()])
    ->sum('bcm');

$variancePercent = $target
    ? round((($actual - $target->target_bcm) / $target->target_bcm) * 100, 2)
    : null;
// Negative = underproduction; Positive = overproduction
```

---

## Pattern — Production Test Setup

```php
#[Test]
public function production_record_calculates_bcm_per_hour(): void
{
    $user  = $this->adminUser();
    $team  = $user->currentTeam;
    $machine = Machine::factory()->create(['team_id' => $team->id]);
    $shift   = Shift::factory()->create(['team_id' => $team->id]);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/production/records', [
            'machine_id'      => $machine->id,
            'shift_id'        => $shift->id,
            'bcm'             => 400.0,
            'tons'            => 1000.0,
            'operating_hours' => 8.0,
            'recorded_at'     => now()->toIso8601String(),
        ])
        ->assertCreated();

    $this->assertDatabaseHas('production_records', [
        'machine_id' => $machine->id,
        'bcm'        => 400.0,
    ]);
}

#[Test]
public function production_is_isolated_between_teams(): void
{
    $userA = $this->adminUser();
    $userB = $this->createUserInSeparateTeam();

    ProductionRecord::factory()->create(['team_id' => $userA->current_team_id]);

    $this->actingAs($userB, 'sanctum')
        ->getJson('/api/v1/production/records')
        ->assertJsonCount(0, 'data');
}
```

---

## ProductionService Usage

```php
use App\Services\ProductionService;

$service = app(ProductionService::class);

// Get summary for a team and period
$summary = $service->getPeriodSummary($team, now()->startOfMonth(), now());
// Returns: ['bcm' => float, 'tons' => float, 'achievement_percent' => float]

// Equipment productivity ranking
$ranking = $service->getEquipmentProductivity($team, $startDate, $endDate);
// Returns collection sorted by bcm/hour descending
```

---

## ProductionDashboard Livewire Component

```
app/Livewire/ProductionDashboard.php

Key public properties:
  $period     — 'daily'|'weekly'|'monthly'
  $mineAreaId — filter by area (null = all areas)
  $machineId  — filter by machine (null = all machines)

Key methods:
  mount()                — loads initial data
  updatedPeriod()        — re-runs queries on period change
  getProductionData()    — returns formatted chart data
  getTargetComparison()  — returns target vs actual
```

---

## ESM Intelligence Handoff

When production is below target by > 10%, this skill feeds:
- **financial-intelligence-agent**: revenue-at-risk calculation
- **dispatch-routing-patterns**: review haul cycle inefficiencies
- **machine-health-patterns**: check if machine downtime is the cause
- **mine-area-patterns**: check if area congestion is limiting loads

---

## Commands Reference

```bash
# Run production tests
php artisan test --compact tests/Feature/ProductionDashboardTest.php

# Check production totals for current month
php artisan tinker --execute '
$records = App\Models\ProductionRecord::where("team_id", App\Models\Team::first()->id)
    ->whereMonth("recorded_at", now()->month)
    ->selectRaw("SUM(bcm) as bcm, SUM(tons) as tons")
    ->first();
echo "BCM: {$records->bcm} | Tons: {$records->tons}";
'

# Check if targets exist for this month
php artisan tinker --execute '
App\Models\ProductionTarget::where("period", now()->format("Y-m"))->get(["team_id","target_bcm","target_tons"]);
'
```
