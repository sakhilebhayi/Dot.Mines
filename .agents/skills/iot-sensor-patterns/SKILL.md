---
name: iot-sensor-patterns
description: >
  Mines platform IoT sensor and telemetry patterns. Use when: validating IoT sensor data feeds,
  detecting stale or anomalous sensor readings, working with IoTSensor or SensorReading models,
  debugging SensorReadingRecorded or SensorStatusChanged events, writing tests for sensor
  thresholds, understanding IoTSensorService, or wiring sensor anomalies into the alert pipeline.
argument-hint: 'Describe the IoT sensor task you need help with'
esm-layer: operational
esm-feeds-to:
  - machine-health-patterns
  - alert-system
  - production-patterns
  - dispatch-routing-patterns
  - data-quality-patterns
esm-consumes-from:
  - data-quality-patterns
  - machine-health-patterns
---

# IoT Sensor Patterns

## When to Use

- Creating or querying IoTSensor / SensorReading records
- Debugging why sensor anomalies are not triggering alerts
- Writing tests for sensor threshold logic
- Understanding the anomaly detection pipeline
- Working with `SensorReadingRecorded` or `SensorStatusChanged` events
- Diagnosing stale telemetry from a sensor device

---

## Sensor Types

```
fuel        — tank level sensor (litres remaining)
engine      — RPM, oil pressure, coolant temperature
temperature — ambient and component temperature (°C)
pressure    — tyre pressure, hydraulic pressure (kPa / bar)
gps         — GPS fix quality, speed (m/s)
vibration   — chassis vibration (g-force)
tyre        — tyre temperature + pressure combined unit
```

---

## Core Models

```
IoTSensor      — a physical sensor device attached to a machine or area
SensorReading  — a timestamped measurement from a sensor
```

---

## Anomaly Detection Pipeline

```
SensorReading::created
       ↓
IoTSensorService::evaluateReading($reading)
       ↓
  Is value outside threshold?
  Is value a sudden spike (> 3σ from 30-min rolling avg)?
       ↓
  yes → SensorReadingRecorded::dispatch($reading, anomaly: true)
       ↓
  SendSensorAlertNotification listener
       ↓
  Alert created (type: 'sensor_anomaly', level: depends on severity)
  + Notification dispatched to team managers
```

---

## Pattern — Recording a Sensor Reading

```php
use App\Services\IoTSensorService;

$service = app(IoTSensorService::class);
$reading = $service->record(
    sensor: $sensor,
    value: 94.5,          // raw value in sensor unit
    unit: 'celsius',
    recordedAt: now(),
);
// Internally: creates SensorReading + evaluates anomaly + fires events
```

---

## Pattern — Configuring Thresholds

```php
// IoTSensor model threshold fields:
// min_threshold — below this = anomaly (e.g. low tyre pressure)
// max_threshold — above this = anomaly (e.g. high temperature)
// critical_min  — critical severity below this
// critical_max  — critical severity above this

$sensor->update([
    'min_threshold' => 80.0,   // PSI
    'max_threshold' => 120.0,
    'critical_min'  => 60.0,
    'critical_max'  => 140.0,
]);
```

---

## Pattern — Sensor Test Setup

```php
#[Test]
public function sensor_anomaly_creates_alert(): void
{
    Queue::fake();
    Event::fake([SensorReadingRecorded::class]);

    $user   = $this->adminUser();
    $sensor = IoTSensor::factory()->create([
        'team_id'       => $user->current_team_id,
        'type'          => 'temperature',
        'max_threshold' => 90.0,
    ]);
    $machine = Machine::factory()->create(['team_id' => $user->current_team_id]);
    $sensor->machine()->associate($machine)->save();

    $service = app(App\Services\IoTSensorService::class);
    $service->record($sensor, 105.0, 'celsius', now()); // above max_threshold

    Event::assertDispatched(SensorReadingRecorded::class);
    $this->assertDatabaseHas('alerts', [
        'type'   => 'sensor_anomaly',
        'status' => 'active',
    ]);
}

#[Test]
public function sensor_readings_are_isolated_between_teams(): void
{
    $userA = $this->adminUser();
    $userB = $this->createUserInSeparateTeam();

    $sensor = IoTSensor::factory()->create(['team_id' => $userA->current_team_id]);
    SensorReading::factory()->count(3)->create(['sensor_id' => $sensor->id]);

    $this->actingAs($userB, 'sanctum')
        ->getJson('/api/v1/sensors')
        ->assertJsonCount(0, 'data');
}
```

---

## IoTSensorController Reference

```
GET    /api/v1/sensors              — list sensors for current team
POST   /api/v1/sensors              — create sensor
GET    /api/v1/sensors/{sensor}     — get sensor + last reading
POST   /api/v1/sensors/{sensor}/readings — post a new reading (device ingestion endpoint)
GET    /api/v1/sensors/{sensor}/readings — paginated reading history
```

---

## Staleness Detection

A sensor is considered stale if:
```php
$lastReading = $sensor->readings()->latest('recorded_at')->first();
$isStale = ! $lastReading || $lastReading->recorded_at->lt(now()->subMinutes(15));
// → SensorStatusChanged::dispatch($sensor, status: 'offline') if stale
```

---

## ESM Intelligence Handoff

When sensor anomaly is detected:
- **machine-health-patterns**: re-evaluate machine health score immediately
- **alert-system**: create alert and notify team
- **data-quality-patterns**: flag reading for trust score review
- **maintenance-patterns**: if anomaly is engine/pressure → trigger maintenance recommendation

---

## Commands Reference

```bash
# Run sensor tests
php artisan test --compact tests/Feature/IoTSensorTest.php

# Check stale sensors
php artisan tinker --execute '
App\Models\IoTSensor::withoutGlobalScopes()
    ->whereDoesntHave("readings", fn($q) => $q->where("recorded_at", ">=", now()->subMinutes(15)))
    ->get(["id","name","type","machine_id"]);
'

# Check recent anomalies
php artisan tinker --execute '
App\Models\SensorReading::where("is_anomaly", true)
    ->latest()
    ->limit(10)
    ->get(["sensor_id","value","unit","recorded_at"]);
'
```
