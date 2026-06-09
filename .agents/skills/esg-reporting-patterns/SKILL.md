---
name: esg-reporting-patterns
description: >
  Mines platform ESG and sustainability reporting patterns. Use when: calculating carbon emissions
  from fuel consumption, working with FuelConsumptionMetric, generating ESG reports, tracking
  energy efficiency KPIs, implementing emission forecasting, calculating diesel consumption per BCM,
  building sustainability dashboards, or preparing ESG data for investor reporting.
argument-hint: 'Describe the ESG or sustainability task you need help with'
esm-layer: governance
esm-feeds-to:
  - reporting-patterns
  - financial-intelligence-agent
  - compliance-reporting-patterns
esm-consumes-from:
  - fuel-patterns
  - production-patterns
  - shift-patterns
  - fleet-management
---

# ESG Reporting Patterns

## When to Use

- Calculating carbon emissions from diesel fuel consumption
- Working with FuelConsumptionMetric for per-machine or fleet-level energy data
- Generating ESG reports (PDF or structured data export)
- Tracking fuel efficiency KPIs (litres per BCM, litres per ton)
- Implementing emission forecasting for future periods
- Preparing ESG data for investor or regulatory submission
- Building sustainability dashboard components
- Integrating ESG metrics with financial cost calculations

---

## Core Models

```
FuelConsumptionMetric — time-series fuel consumption record per machine per period
```

---

## Emission Calculation Standards

```
Diesel emission factor: 2.68 kg CO₂ per litre (SANS/IPCC default for mining)

CO₂ (kg)    = litres_consumed × 2.68
CO₂ (tonnes) = CO₂_kg / 1000

Emission intensity:
  kg CO₂ per BCM   = total_co2_kg / total_bcm
  kg CO₂ per ton   = total_co2_kg / total_tons
  kg CO₂ per hour  = total_co2_kg / total_operating_hours
```

---

## Key ESG KPIs

```
Total CO₂ emissions (kg / tonnes)  — primary environmental impact metric
Fuel efficiency (L/BCM)            — lower = better
Fuel efficiency (L/ton)            — lower = better
Energy intensity (MJ/BCM)          — 1L diesel ≈ 38.6 MJ
Fleet idle ratio (%)               — idle hours / total hours × 100
Carbon intensity vs target (%)     — actual vs carbon budget
Year-over-year emission change (%) — trend metric for investors
```

---

## Pattern — Calculating Fleet ESG Metrics

```php
use App\Models\FuelConsumptionMetric;
use App\Models\ProductionRecord;

// Get period totals
$fuelConsumed = FuelConsumptionMetric::where('team_id', $team->id)
    ->whereBetween('period_start', [$startDate, $endDate])
    ->sum('litres_consumed');

$production = ProductionRecord::where('team_id', $team->id)
    ->whereBetween('recorded_at', [$startDate, $endDate])
    ->selectRaw('SUM(bcm) as bcm, SUM(tons) as tons')
    ->first();

// Emissions
$co2Kg     = $fuelConsumed * 2.68;
$co2Tonnes = $co2Kg / 1000;

// Intensities
$litresPerBcm = $production->bcm > 0 ? round($fuelConsumed / $production->bcm, 4) : null;
$litresPerTon = $production->tons > 0 ? round($fuelConsumed / $production->tons, 4) : null;
$kgCo2PerBcm  = $production->bcm > 0 ? round($co2Kg / $production->bcm, 4) : null;
```

---

## Pattern — Per-Machine Emission Profile

```php
// ESG breakdown by machine
FuelConsumptionMetric::where('team_id', $team->id)
    ->whereBetween('period_start', [$startDate, $endDate])
    ->with('machine')
    ->selectRaw('machine_id, SUM(litres_consumed) as total_litres')
    ->groupBy('machine_id')
    ->get()
    ->map(fn($m) => [
        'machine'         => $m->machine->name,
        'litres'          => $m->total_litres,
        'co2_kg'          => round($m->total_litres * 2.68, 2),
        'co2_tonnes'      => round($m->total_litres * 2.68 / 1000, 4),
    ]);
```

---

## Pattern — ESG Forecast

```php
// Simple projection: rolling average of last 90 days × 365/90
$last90DayFuel = FuelConsumptionMetric::where('team_id', $team->id)
    ->where('period_start', '>=', now()->subDays(90))
    ->sum('litres_consumed');

$annualisedFuel       = ($last90DayFuel / 90) * 365;
$annualisedCo2Tonnes  = round($annualisedFuel * 2.68 / 1000, 2);
```

---

## Pattern — ESG Test

```php
#[Test]
public function esg_report_calculates_correct_co2_emissions(): void
{
    $user = $this->adminUser();
    FuelConsumptionMetric::factory()->create([
        'team_id'         => $user->current_team_id,
        'litres_consumed' => 1000.0,
        'period_start'    => now()->startOfMonth(),
        'period_end'      => now()->endOfMonth(),
    ]);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/reports', [
            'type'         => 'esg_emissions',
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end'   => now()->endOfMonth()->toDateString(),
            'format'       => 'pdf',
        ])
        ->assertCreated();

    // The report job should run and produce:
    // co2_kg = 1000 × 2.68 = 2680 kg
    // co2_tonnes = 2.68
}
```

---

## ESG Report Structure (for investor / DMRE submission)

```
1. Executive Summary
   - Total CO₂ emissions (period vs prior period)
   - Fleet fuel efficiency trend
   - Key initiatives and targets

2. Emissions Inventory
   - Per machine breakdown
   - By mine area
   - By shift / time of day

3. Energy Intensity
   - Litres per BCM
   - Litres per ton
   - MJ per operating hour

4. Fleet Idle Analysis
   - Total idle hours
   - CO₂ emitted during idle (avoidable emissions)
   - Recommended idle reduction (dispatch optimization)

5. Targets vs Actuals
   - Carbon budget: actual vs target
   - Intensity improvement: % vs prior year

6. Forecasts
   - 12-month annualised projection
   - Scenario: if idle time reduced by 20%
```

---

## ESM Intelligence Handoff

- **fuel-patterns**: raw fuel transaction data → FuelConsumptionMetric aggregation
- **production-patterns**: BCM/tons for intensity calculations
- **financial-intelligence-agent**: cost-per-BCM includes carbon cost for ESG cost accounting
- **reporting-patterns**: ESG data rendered into PDF via GenerateReportJob
- **compliance-reporting-patterns**: environmental violations linked to ESG metrics

---

## Commands Reference

```bash
# Run ESG tests
php artisan test --compact tests/Feature/ESGReportTest.php

# Calculate current fleet CO₂ for this month
php artisan tinker --execute '
$fuel = App\Models\FuelConsumptionMetric::whereMonth("period_start", now()->month)->sum("litres_consumed");
$co2  = round($fuel * 2.68 / 1000, 2);
echo "This month: {$fuel}L consumed → {$co2} tonnes CO₂\n";
'

# Check machines with worst fuel efficiency (highest L/BCM)
php artisan tinker --execute '
App\Models\FuelConsumptionMetric::whereMonth("period_start", now()->month)
    ->with("machine")
    ->selectRaw("machine_id, SUM(litres_consumed) as fuel, SUM(bcm_produced) as bcm")
    ->groupBy("machine_id")
    ->get()
    ->sortByDesc(fn($m) => $m->bcm > 0 ? $m->fuel / $m->bcm : 0)
    ->take(5)
    ->each(fn($m) => printf("Machine %d: %.2fL/BCM\n", $m->machine_id, $m->bcm > 0 ? $m->fuel/$m->bcm : 0));
'
```
