---
name: fleet-management
description: >
  Mines platform fleet management patterns. Use when: adding new machine types, writing machine
  API tests, debugging GPS location pipelines, working with BellEquipment sync, building
  machine-related Livewire components, understanding HasTeamFilters on Machine, implementing
  machine area assignment logic, debugging machine status showing incorrectly, or fixing
  live telemetry not updating on fleet cards.
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
- Debugging machine status (Idling/Working/Travelling/Parked/Offline)
- Fixing fleet overview cards not showing live telemetry status

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

## Machine Status Engine

Status is derived by `MachineTelemetryService::deriveStatus()` — **never** read from `Machine.status` for display.

| Status | Criteria |
|--------|----------|
| `working` | Engine on + speed ≤ 3 km/h + payload > 500 kg |
| `travelling` | Engine on + speed > 3 km/h |
| `idling` | Engine on + speed ≤ 3 km/h + no significant payload |
| `parked` | Engine off |
| `offline` | No telemetry for > 30 minutes |
| `maintenance` | `Machine.status = 'maintenance'` (manual override) |
| `loading` | Set externally by MapEventService on geofence entry |
| `dumping` | Set externally by MapEventService on geofence exit |

**Data source priority:**
1. Bell `BellEquipmentCurrentStatus` + `BellEquipmentLocationHistory` (speed)
2. `MachineMetric` (all other OEM/IoT adapters)
3. `Machine.status` field (fallback, no real-time signal)

### Telemetry Snapshot Keys

```php
$tel = app(MachineTelemetryService::class)->forMachine($machine->id);

$tel['status']                // 'working' | 'travelling' | 'idling' | 'parked' | 'offline' | 'maintenance'
$tel['status_label']          // 'Working' | 'Travelling' | ...
$tel['status_color']          // Tailwind colour: 'emerald' | 'cyan' | 'amber' | 'slate' | 'red' | 'orange'
$tel['engine_running']        // bool|null
$tel['fuel_remaining_percent']// float|null (0–100)
$tel['operating_hours']       // float|null (cumulative engine hours)
$tel['idle_hours']            // float|null (cumulative idle hours)
$tel['working_hours']         // float|null = operating_hours − idle_hours
$tel['load_count']            // int|null (Bell only)
$tel['payload']               // float|null (kg)
$tel['speed_kmh']             // float|null
$tel['heading_degrees']       // float|null (0–360)
$tel['engine_rpm']            // float|null (MachineMetric source)
$tel['coolant_temperature']   // float|null °C
$tel['engine_temperature']    // float|null °C
$tel['battery_voltage']       // float|null V
$tel['is_stale']              // bool — true when data is 15–30 min old
$tel['data_age_minutes']      // int|null
$tel['telemetry_source']      // 'bell' | 'machine_metric' | 'machine' | 'none'
```

---

## Pattern — Location Update Pipeline

```
Bell ISO15143-3 snapshot (every 5 min)
   → BellIso15143Service::sync()
   → BellEquipmentCurrentStatus (upsert)
   → Machine::update(['lat','lng','last_seen_at','operating_hours','status'])
   → MachineLocationUpdated::dispatch($machine)
   → Broadcast to team channel (LiveMap listens)

Bell Locations API (every 5 min via SyncBellLocationsJob)
   → BellHistoricalTelemetryService::syncSignal('Locations')
   → BellEquipmentLocationHistory (insert — speed + heading)
   → MachineTelemetryService reads latest speed for status derivation
```

---

## Pattern — Machine API Test Setup

```php
private function adminUser(): User
{
    $user = User::factory()->withPersonalTeam()->create();
    TeamRoleService::provisionTeam($user->currentTeam, $user);
    return $user;
}

$machine = Machine::factory()->create(['team_id' => $user->current_team_id]);

$this->actingAs($user, 'sanctum')
    ->getJson("/api/v1/machines/{$machine->id}")
    ->assertOk();
```

---

## Pattern — Fleet Card: Show Live Status (not DB status)

```blade
{{-- CORRECT: use live telemetry --}}
@php $tel = $telemetryMap[$machine->id] ?? null; @endphp
<span>{{ $tel['status_label'] ?? ucfirst($machine->status) }}</span>

{{-- WRONG: DB status is only active/idle/maintenance --}}
<span>{{ ucfirst($machine->status) }}</span>
```

---

## Pattern — Eager Loading for Fleet Queries

```php
$machines = Machine::with([
    'mineArea',
    'currentStatus',
    'latestMaintenanceRecord',
    'activeAlerts',
])->paginate(20);
```

---

## Known Status Issues & Fixes

| Symptom | Root Cause | Fix |
|---------|------------|-----|
| All machines show "Idling" | Fleet card uses `Machine.status='idle'` (DB) not live telemetry | Use `$telemetryMap[$machine->id]['status_label']` |
| Machines stationary show "Working" | Old `deriveStatus` had no idling detection | Updated: engine on + speed=0 → 'idling' |
| Status shows "Active" not "Travelling" | DB status only has active/idle/maintenance | Use MachineTelemetryService |

---

## Commands Reference

```bash
# Trigger Bell ISO15143-3 sync immediately
php artisan tinker --execute 'App\Jobs\SyncBellFleetDataJob::dispatchSync();'

# Sync yesterday's production into ProductionRecords from Bell OEM KPIs
php artisan tinker --execute 'App\Jobs\SyncBellProductionRecordsJob::dispatch(30);'

# Check machine counts
php artisan tinker --execute 'App\Models\Machine::withoutGlobalScopes()->count();'

# Run fleet tests
php artisan test --compact tests/Feature/FleetMineAreaAssignmentTest.php
php artisan test --compact tests/Feature/BellIso15143ServiceTest.php
```
