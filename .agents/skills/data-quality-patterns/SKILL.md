---
name: data-quality-patterns
description: >
  Mines platform data quality and trust engine patterns. Use when: working with DataQualitySnapshot,
  using DataTrustService, auditing OEM telemetry consistency, working with OrganisationalMemoryService
  or KnowledgeGraphEntry, detecting stale or missing machine data, validating production figures,
  scoring data reliability, or reconciling data across multiple sources.
argument-hint: 'Describe the data quality or trust task you need help with'
esm-layer: intelligence
esm-feeds-to:
  - production-patterns
  - fleet-intelligence-agent
  - ai-agent-patterns
  - compliance-reporting-patterns
  - audit-logging-patterns
esm-consumes-from:
  - iot-sensor-patterns
  - fleet-management
  - oem-integration-patterns
---

# Data Quality Patterns

## When to Use

- Creating or querying DataQualitySnapshot records
- Using DataTrustService to score data reliability
- Detecting stale OEM telemetry or missing machine updates
- Validating production figures against raw sensor data
- Working with OrganisationalMemoryService for persistent AI context
- Working with KnowledgeGraphEntry for cross-domain knowledge connections
- Reconciling Bell Equipment OEM data against internal machine records
- Writing tests for data quality pipelines

---

## Core Models

```
DataQualitySnapshot      — periodic quality score for a data source or machine
KnowledgeGraphEntry      — cross-domain fact stored in the platform's knowledge graph
```

---

## Data Trust Score Dimensions

Every significant data point receives a composite Trust Score (0–100):

```
Completeness   — are all expected fields populated?
Freshness      — how recently was this data updated? (decays over time)
Consistency    — does this data agree with related data sources?
Accuracy       — does the value fall within expected physical bounds?
Reliability    — historical track record of this source being correct

Trust Score = (Completeness × 0.25) + (Freshness × 0.25) +
              (Consistency × 0.20) + (Accuracy × 0.20) +
              (Reliability × 0.10)
```

---

## DataTrustService API

```php
use App\Services\DataTrustService;

$service = app(DataTrustService::class);

// Score a machine's overall data quality
$score = $service->scoreMachine($machine);
// Returns: ['trust_score' => float, 'completeness' => float, 'freshness' => float,
//           'consistency' => float, 'accuracy' => float, 'reliability' => float]

// Score a specific data point
$pointScore = $service->scoreDataPoint(
    source: 'bell_oem',
    value: $reading->engine_hours,
    expectedRange: [0, 50000],
    lastUpdated: $reading->updated_at,
    crossValidationValues: [$machine->engine_hours, $session->total_hours],
);

// Create a snapshot for archival
$service->snapshotTeamQuality($team);
// Saves DataQualitySnapshot for all machines in team
```

---

## OEM Data Reconciliation

```php
// Detect drift between Bell OEM engine hours and internal EngineHourSession totals
$bellHours     = $bellEquipment->total_engine_hours;
$internalHours = EngineHourSession::where('machine_id', $machine->id)->sum('duration_hours');
$drift         = abs($bellHours - $internalHours);

if ($drift > 10) { // > 10 hours drift is suspicious
    DataQualitySnapshot::create([
        'machine_id'   => $machine->id,
        'source'       => 'engine_hours_reconciliation',
        'issue'        => "OEM reports {$bellHours}h, internal sessions total {$internalHours}h (drift: {$drift}h)",
        'trust_score'  => max(0, 100 - ($drift * 2)),
        'flagged_at'   => now(),
    ]);
}
```

---

## Freshness Scoring

```php
// Data freshness decays on a curve:
function freshnessScore(\DateTime $lastUpdated): float
{
    $ageMinutes = now()->diffInMinutes($lastUpdated);
    return match (true) {
        $ageMinutes <= 5    => 100.0,
        $ageMinutes <= 15   => 85.0,
        $ageMinutes <= 60   => 60.0,
        $ageMinutes <= 1440 => 30.0,  // 24 hours
        default             => 0.0,
    };
}
```

---

## KnowledgeGraphEntry Usage

```php
use App\Services\OrganisationalMemoryService;

$memory = app(OrganisationalMemoryService::class);

// Store a cross-domain insight
$memory->record(
    domain: 'fleet_fuel',
    key: "machine:{$machine->id}:avg_litres_per_hour",
    value: 38.5,
    context: ['period' => '2026-Q2', 'sample_size' => 180],
    confidence: 91.0,
);

// Retrieve for AI agent use
$insight = $memory->recall('fleet_fuel', "machine:{$machine->id}:avg_litres_per_hour");

// Store agent learning outcome
$memory->learnFromOutcome(
    agent: 'maintenance-predictor',
    prediction: 'bearing_failure_within_7_days',
    actual: 'bearing_replaced_day_5',
    accuracy: 95.0,
);
```

---

## Pattern — Data Quality Test

```php
#[Test]
public function stale_machine_data_is_flagged_with_low_trust_score(): void
{
    $machine = Machine::factory()->create([
        'last_seen_at' => now()->subHours(25), // stale
    ]);

    $service = app(App\Services\DataTrustService::class);
    $score   = $service->scoreMachine($machine);

    $this->assertLessThan(50, $score['freshness']);
    $this->assertLessThan(70, $score['trust_score']);
}

#[Test]
public function oem_reconciliation_creates_snapshot_on_drift(): void
{
    $machine = Machine::factory()->create();
    BellEquipment::factory()->create([
        'machine_id'          => $machine->id,
        'total_engine_hours'  => 5000,
    ]);
    EngineHourSession::factory()->create([
        'machine_id'     => $machine->id,
        'duration_hours' => 4950, // 50h drift — should flag
    ]);

    $service = app(App\Services\DataTrustService::class);
    $service->reconcileMachine($machine);

    $this->assertDatabaseHas('data_quality_snapshots', [
        'machine_id' => $machine->id,
        'source'     => 'engine_hours_reconciliation',
    ]);
}
```

---

## ESM Intelligence Handoff

Data quality scores gate all AI decisions:
- **ai-agent-patterns**: agents must check trust score before using data in predictions
- **production-patterns**: production records with trust < 70 are flagged for review
- **audit-logging-patterns**: all data quality flags are logged
- **compliance-reporting-patterns**: low-quality data used in compliance reports is flagged

Trust score thresholds for AI decisions:
```
> 80  = use directly in AI model
60–80 = use with caveat, increase prediction uncertainty
< 60  = do not use in AI model, request fresh data
< 40  = escalate to human review
```

---

## Commands Reference

```bash
# Run data quality tests
php artisan test --compact tests/Feature/DataQualityTest.php

# Check machines with low trust scores
php artisan tinker --execute '
$service = app(App\Services\DataTrustService::class);
App\Models\Machine::all()->each(function($m) use ($service) {
    $score = $service->scoreMachine($m);
    if ($score["trust_score"] < 60) {
        echo "LOW TRUST — Machine {$m->id} ({$m->name}): {$score["trust_score"]}\n";
    }
});
'

# View recent data quality snapshots
php artisan tinker --execute '
App\Models\DataQualitySnapshot::latest()->limit(10)->get(["machine_id","source","issue","trust_score","flagged_at"]);
'
```
