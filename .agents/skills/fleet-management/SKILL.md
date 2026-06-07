---
name: fleet-management
description: >
  Mines platform fleet management patterns. Use when: adding new machine types, writing machine
  API tests, debugging GPS location pipelines, working with BellEquipment sync, building
  machine-related Livewire components, understanding HasTeamFilters on Machine, or implementing
  machine area assignment logic.
argument-hint: 'Describe the fleet task you need help with'
---

# Fleet Management Patterns

## When to Use

- Creating or modifying Machine model or migration
- Writing tests for machine API endpoints
- Debugging GPS location pipeline issues
- Working with Bell Equipment OEM integration
- Building machine-related Livewire components
- Implementing haul dispatch or route planning features

---

## Machine Model Key Traits

```php
// app/Models/Machine.php
// Traits: HasTeamFilters → adds global scope filtering by current_team_id
// Key relations:
$machine->mineArea();           // BelongsTo MineArea
$machine->latestMaintenanceRecord(); // HasOne (latest)
$machine->activeAlerts();           // HasMany where status=active
$machine->currentStatus();          // HasOne BellEquipmentCurrentStatus
$machine->metrics();                // HasMany MachineMetric
```

**Critical:** `Machine` has `HasTeamFilters` → route binding returns **404** for cross-team access.

---

## Pattern — Location Update Pipeline

```
GPS Data → POST /api/v1/machines/{machine}/location
          → MachineController::updateLocation()
          → Machine::update(['lat','lng','last_seen_at'])
          → MachineLocationUpdated::dispatch($machine)
          → Broadcast to team channel (LiveMap listens)
```

---

## Pattern — Machine API Test Setup

```php
// Full admin with team
private function adminUser(): User
{
    $user = User::factory()->withPersonalTeam()->create();
    TeamRoleService::provisionTeam($user->currentTeam, $user);
    return $user;
}

// Create machine scoped to user's team
$machine = Machine::factory()->create(['team_id' => $user->current_team_id]);

// Test with Sanctum
$this->actingAs($user, 'sanctum')
    ->getJson("/api/v1/machines/{$machine->id}")
    ->assertOk();
```

---

## Pattern — Eager Loading for Fleet Queries

```php
// Avoid N+1 when loading machine lists
$machines = Machine::with([
    'mineArea',
    'currentStatus',
    'latestMaintenanceRecord',
    'activeAlerts',
])->paginate(20);
```

---

## Commands Reference

```bash
# Check Bell sync
php artisan tinker --execute 'App\Jobs\SyncBellFleetDataJob::dispatchSync();'

# Check machine counts
php artisan tinker --execute 'App\Models\Machine::withoutGlobalScopes()->count();'

# Run fleet tests
php artisan test --compact tests/Feature/FleetMineAreaAssignmentTest.php
```
