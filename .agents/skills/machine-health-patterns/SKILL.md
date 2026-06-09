---
name: machine-health-patterns
description: >
  Mines platform machine health and predictive maintenance intelligence patterns. Use when:
  calculating or debugging machine health scores, working with MachineHealthStatus or
  HealthMetric models, using MachineHealthController, understanding MachineStatusMonitoringJob,
  handling MachineOffline events, wiring sensor data into health scoring, or predicting
  machine failures.
argument-hint: 'Describe the machine health task you need help with'
esm-layer: operational
esm-feeds-to:
  - maintenance-patterns
  - alert-system
  - production-patterns
  - dispatch-routing-patterns
  - fleet-intelligence-agent
esm-consumes-from:
  - iot-sensor-patterns
  - maintenance-patterns
  - fleet-management
---

# Machine Health Patterns

## When to Use

- Calculating or reading MachineHealthStatus scores
- Working with HealthMetric time-series data
- Debugging why a machine health score changed unexpectedly
- Writing tests for MachineHealthController
- Understanding MachineStatusMonitoringJob trigger conditions
- Wiring IoT sensor anomalies into health score recalculation
- Predicting machine failures before they occur

---

## Core Models

```
MachineHealthStatus — current composite health score for a machine (0–100)
HealthMetric        — time-series health dimension readings
```

---

## Health Score Dimensions

```
engine_health      (0–100)  — based on RPM stability, oil pressure, temp
tyre_health        (0–100)  — tyre pressure + temperature sensors
hydraulic_health   (0–100)  — hydraulic pressure readings
maintenance_score  (0–100)  — from MaintenanceHealthService
utilization_score  (0–100)  — operating hours vs. designed capacity
sensor_score       (0–100)  — sensor anomaly frequency (fewer anomalies = higher)

Composite score = weighted average:
  engine_health     × 0.30
  maintenance_score × 0.25
  tyre_health       × 0.15
  hydraulic_health  × 0.15
  utilization_score × 0.10
  sensor_score      × 0.05
```

---

## Health Score Thresholds

```
90–100  Excellent     — no action required
70–89   Good          — monitor
50–69   Fair          — schedule maintenance soon
30–49   Poor          — urgent maintenance required
0–29    Critical      — ground machine immediately
```

---

## Pattern — Reading Machine Health

```php
// Via API
GET /api/v1/machines/{machine}/health
// Returns: MachineHealthStatus with all dimension scores + composite score + trend

// Programmatic
$status = MachineHealthStatus::where('machine_id', $machine->id)->first();
echo $status->composite_score; // e.g. 73.4
echo $status->trend;           // 'improving' | 'stable' | 'declining'
```

---

## Pattern — Updating Health Score

```php
use App\Services\MaintenanceHealthService;

// Full recalculation triggered by:
// 1. SensorReading created (IoTSensorService calls this)
// 2. MaintenanceRecord completed
// 3. MachineStatusMonitoringJob (runs every 5 min)

$service = app(MaintenanceHealthService::class);
$score   = $service->calculateHealthScore($machine);
// Also saves to MachineHealthStatus and appends to HealthMetric history
```

---

## MachineStatusMonitoringJob

```
Runs every 5 minutes (scheduled)
  For each team machine:
    → Recalculates composite health score
    → If score dropped > 10 points since last check → Alert created (level: high)
    → If score < 30 → Alert created (level: critical) + MachineOffline event
    → If machine has not sent GPS for 30+ min → MachineOffline::dispatch($machine)
```

---

## Pattern — Machine Health Test

```php
#[Test]
public function critical_health_score_creates_alert(): void
{
    Queue::fake();
    $user    = $this->adminUser();
    $machine = Machine::factory()->create(['team_id' => $user->current_team_id]);

    MachineHealthStatus::factory()->create([
        'machine_id'      => $machine->id,
        'composite_score' => 22.0,   // below critical threshold of 30
    ]);

    // Trigger the monitoring job
    $job = new App\Jobs\MachineStatusMonitoringJob();
    $job->handle();

    $this->assertDatabaseHas('alerts', [
        'machine_id' => $machine->id,
        'type'       => 'critical_health',
        'status'     => 'active',
    ]);
}

#[Test]
public function health_score_reflects_overdue_maintenance(): void
{
    $machine = Machine::factory()->create();
    MaintenanceSchedule::factory()->overdue()->create(['machine_id' => $machine->id]);

    $service = app(App\Services\MaintenanceHealthService::class);
    $score   = $service->calculateHealthScore($machine);

    $this->assertLessThan(70, $score); // overdue maintenance reduces score
}
```

---

## MachineDetail Health Tab

```
app/Livewire/MachineDetail.php → $activeTab = 'health'

Displays:
  - Composite score gauge (0–100)
  - Dimension breakdown (radar chart)
  - Health trend chart (last 30 days)
  - Active alerts linked to this machine
  - Upcoming maintenance (from MaintenanceHealthService)
  - Sensor readings summary (from IoTSensorService)
```

---

## ESM Intelligence Handoff

When composite score drops below 50:
- **maintenance-patterns**: trigger urgent maintenance schedule recommendation
- **dispatch-routing-patterns**: remove machine from active dispatch queue
- **production-patterns**: flag as potential production loss risk
- **alert-system**: create alert, notify team managers

When health trend = 'declining' for 3+ consecutive readings:
- **ai-agent-patterns**: trigger MaintenancePredictorAgent for failure prediction
- **fleet-intelligence-agent**: update fleet availability score

---

## Commands Reference

```bash
# Run machine health tests
php artisan test --compact tests/Feature/MachineHealthTest.php

# Find machines with critical health scores
php artisan tinker --execute '
App\Models\MachineHealthStatus::where("composite_score","<",30)
    ->with("machine")
    ->get(["machine_id","composite_score","trend","updated_at"]);
'

# Manually trigger health recalculation for a machine
php artisan tinker --execute '
$machine = App\Models\Machine::find(1);
$service = app(App\Services\MaintenanceHealthService::class);
$score   = $service->calculateHealthScore($machine);
echo "Health score: {$score}";
'
```
