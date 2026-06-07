---
name: alert-system
description: >
  Mines platform alert system patterns. Use when: creating new alert types, wiring an event to
  an alert, writing tests for alert generation, debugging why alerts are not being acknowledged,
  checking alert notification delivery, understanding RealTimeAlertService, or building
  alert-related UI components.
argument-hint: 'Describe the alert task you need help with'
---

# Alert System Patterns

## When to Use

- Creating a new alert type or severity level
- Wiring an event to automatically generate an alert
- Writing PHPUnit tests for alert generation/acknowledgement
- Debugging missing or duplicate alerts
- Building alert list or alert badge UI components

---

## Alert Model Constants

```php
// app/Models/Alert.php
const STATUS_ACTIVE       = 'active';
const STATUS_ACKNOWLEDGED = 'acknowledged';
const STATUS_RESOLVED     = 'resolved';

const LEVEL_LOW      = 'low';
const LEVEL_MEDIUM   = 'medium';
const LEVEL_HIGH     = 'high';
const LEVEL_CRITICAL = 'critical';
```

**Critical:** `Alert` has `HasTeamFilters` → cross-team access returns **404** (not 403).

---

## Pattern — Creating an Alert Programmatically

```php
use App\Services\RealTimeAlertService;

$service = app(RealTimeAlertService::class);
$service->createAlert(
    machine: $machine,
    type: 'overspeed',
    level: Alert::LEVEL_HIGH,
    message: "Machine exceeded speed limit: {$speed} km/h",
    context: ['speed' => $speed, 'limit' => $limit],
);
// This also fires AlertTriggered::dispatch($alert)
```

---

## Pattern — Alert Test Setup

```php
#[Test]
public function alert_is_created_for_speed_violation(): void
{
    Queue::fake();
    $user = $this->adminUser();
    $machine = Machine::factory()->create(['team_id' => $user->current_team_id]);

    // Trigger the alerting condition
    // Assert alert was created
    $this->assertDatabaseHas('alerts', [
        'machine_id' => $machine->id,
        'type'       => 'overspeed',
        'status'     => Alert::STATUS_ACTIVE,
    ]);
}
```

---

## Pattern — Acknowledgement Test

```php
#[Test]
public function operator_can_acknowledge_alert(): void
{
    $user = $this->operatorUser(); // has acknowledge_alerts permission
    $alert = Alert::factory()->create([
        'team_id' => $user->current_team_id,
        'status'  => Alert::STATUS_ACTIVE,
    ]);

    $this->actingAs($user, 'sanctum')
        ->postJson("/api/v1/alerts/{$alert->id}/acknowledge")
        ->assertOk();

    $this->assertSame(Alert::STATUS_ACKNOWLEDGED, $alert->fresh()->status);
}
```

---

## Deduplication Pattern

Before creating an alert, always check for an existing open one:
```php
$existing = Alert::where([
    'machine_id' => $machine->id,
    'type'       => $type,
    'status'     => Alert::STATUS_ACTIVE,
])->first();

if ($existing) {
    return $existing; // don't create duplicate
}
```

---

## Commands Reference

```bash
# Dispatch AlertGenerationJob
php artisan tinker --execute 'App\Jobs\AlertGenerationJob::dispatch();'

# Count active alerts per team
php artisan tinker --execute 'App\Models\Alert::withoutGlobalScopes()->where("status","active")->selectRaw("team_id,count(*) c")->groupBy("team_id")->get();'

# Run alert tests
php artisan test --compact tests/Feature/AlertGenerationJobTest.php tests/Feature/AlertsComponentTest.php
```
