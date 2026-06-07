---
name: maintenance-patterns
description: >
  Mines platform maintenance management patterns. Use when: creating maintenance records or
  schedules, writing maintenance API tests, debugging health score calculations, working with
  compliance reports, implementing maintenance completion workflow, or understanding the
  MaintenanceHealthService API.
argument-hint: 'Describe the maintenance task you need help with'
---

# Maintenance Patterns

## When to Use

- Creating or updating MaintenanceRecord/MaintenanceSchedule via API
- Writing tests for maintenance endpoints
- Debugging health score calculations
- Working with ComplianceReport or ComplianceViolation
- Implementing the maintenance completion workflow

---

## Health Score Calculation

```php
// app/Services/MaintenanceHealthService.php
// Score is 0–100, based on:
// - Overdue maintenance (reduces score significantly)
// - Upcoming maintenance within 7 days (minor reduction)
// - Recent completed maintenance (positive signal)
// - Active compliance violations (reduces score)

$service = app(MaintenanceHealthService::class);
$score = $service->calculateHealthScore($machine); // returns float 0–100
```

---

## Pattern — Creating a Maintenance Record

```php
// Via API
POST /api/v1/maintenance/records
{
    "machine_id": 1,
    "type": "preventive",        // preventive|corrective|emergency|inspection
    "description": "Oil change",
    "performed_at": "2026-06-01",
    "performed_by": "user_id",
    "cost": 1500.00,
    "parts_used": ["oil_filter", "engine_oil_5L"]
}
```

---

## Pattern — Completing a Record

```php
// API
POST /api/v1/maintenance/records/{record}/complete
{
    "completion_notes": "All work completed, machine ready",
    "next_service_km": 50000
}
// This triggers MaintenanceRecordObserver::completed()
// Which updates MachineHealthStatus
// Which may fire MaintenanceAlertTriggered if next service is soon
```

---

## Pattern — Maintenance Test Setup

```php
#[Test]
public function completing_maintenance_record_updates_health_status(): void
{
    $user = $this->adminUser();
    $machine = Machine::factory()->create(['team_id' => $user->current_team_id]);
    $record = MaintenanceRecord::factory()->create([
        'machine_id' => $machine->id,
        'team_id'    => $user->current_team_id,
        'status'     => 'pending',
    ]);

    $this->actingAs($user, 'sanctum')
        ->postJson("/api/v1/maintenance/records/{$record->id}/complete", [
            'completion_notes' => 'Done',
        ])
        ->assertOk();

    $this->assertSame('completed', $record->fresh()->status);
}
```

---

## Commands Reference

```bash
# Check overdue maintenance
php artisan tinker --execute '
$service = app(App\Services\MaintenanceHealthService::class);
$team = App\Models\Team::first();
$service->getOverdueMaintenance($team)->count();
'

# Run maintenance tests
php artisan test --compact tests/Feature/MaintenanceDashboardTest.php

# Check due schedules
php artisan tinker --execute '
App\Models\MaintenanceSchedule::withoutGlobalScopes()
    ->where("next_due_at","<=",now()->addDays(7))
    ->get(["machine_id","type","next_due_at"]);
'
```
