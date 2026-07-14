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

## Pattern — Auto-Sync OEM Production Data (Bell)

Bell machines generate daily KPI data automatically in `bell_equipment_daily_kpis`.
`SyncBellProductionRecordsJob` converts this into canonical `ProductionRecord` rows every night.

```bash
# Trigger manual backfill of last 30 days
php artisan tinker --execute 'App\Jobs\SyncBellProductionRecordsJob::dispatch(30);'
```

The synced records have:
- `shift = 'oem_auto'` — identifies OEM-sourced records
- `metadata['source'] = 'bell_oem_kpi'` — traces back to Bell KPI table
- `quantity_produced` = payload_moved_kg / 1000 (tonnes)
- `metadata['loads_moved']`, `['operating_hours']`, `['utilization_percent']`, `['fuel_used_litres']`

**WARNING:** Never manually edit `oem_auto` shift records — they will be overwritten on next sync.

---

## Pattern — Recording a Production Entry

```php
// Via API
POST /api/v1/production/records
{
    "mine_area_id": 2,
    "machine_id": 3,
    "record_date": "2026-07-12",
    "shift": "day",
    "quantity_produced": 1150.20,
    "unit": "tonnes",
    "target_quantity": 1200.00,
    "status": "completed"
}
```

---

## OEM KPI Summary (MachineKpiService)

For Bell machines, use `MachineKpiService` to get production KPIs directly from OEM data
without waiting for manual `ProductionRecord` entry:

```php
use App\Services\MachineKpiService;

$summary = app(MachineKpiService::class)->getDailyKpiSummary(
    machineIds: $team->machines->pluck('id')->toArray(),
    startDate: '2026-07-01',
    endDate: '2026-07-12',
);

// Returns:
// [
//   'total_loads'          => 3820,
//   'total_payload_tonnes' => 11460.5,  // already converted from kg
//   'avg_utilization'      => 82.3,
//   'has_data'             => true,
// ]
```

The production dashboard exposes this as `$bellKpiSummary` and renders it even when
there are no manual ProductionRecord entries.

---

## Pattern — Querying Production for a Period

```php
use App\Models\ProductionRecord;

$records = ProductionRecord::forTeam($teamId)
    ->whereBetween('record_date', [$startDate, $endDate])
    ->with(['machine', 'mineArea'])
    ->orderByDesc('record_date')
    ->get();
```

---

## Pattern — Target Variance Check

```php
use App\Models\ProductionTarget;
use App\Models\ProductionRecord;

$target = ProductionTarget::where('team_id', $team->id)
    ->where('start_date', '<=', now())
    ->where('end_date', '>=', now())
    ->first();

$actual = ProductionRecord::forTeam($team->id)
    ->whereBetween('record_date', [now()->startOfMonth(), now()])
    ->sum('quantity_produced');

$variancePct = $target && $target->target_quantity > 0
    ? round((($actual - $target->target_quantity) / $target->target_quantity) * 100, 1)
    : null;
```

---

## ProductionService Usage

```php
use App\Services\ProductionService;

$service = app(ProductionService::class);

// Statistics for a period
$stats = $service->getProductionStatistics($teamId, $startDate, $endDate);
// Returns: ['total_produced', 'total_target', 'achievement_rate', ...]
```

---

## ProductionDashboard Livewire Component

```
app/Livewire/ProductionDashboard.php

Key public properties:
  $dateFilter    — 'day'|'week'|'month'|'year'
  $mineAreaFilter — int|null
  $viewMode      — 'overview'|'records'|'targets'|'analytics'

Key computed properties:
  $statistics    — from ProductionService::getProductionStatistics()
  $oemKpiSummary — from MachineKpiService::getDailyKpiSummary() (Bell OEM data)
  $productionRecords — paginated ProductionRecord results
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
