---
name: shift-patterns
description: >
  Mines platform shift management patterns. Use when: creating or closing Shift records, working
  with ShiftTemplate, debugging EngineHourSession calculations, using ShiftService, building
  ShiftTemplateManager Livewire component, running PerformShiftChange or SendShiftDigest
  commands, or scoping production and fleet KPIs to a specific shift.
argument-hint: 'Describe the shift management task you need help with'
esm-layer: operational
esm-feeds-to:
  - production-patterns
  - fleet-intelligence-agent
  - maintenance-patterns
  - reporting-patterns
esm-consumes-from:
  - fleet-management
  - mine-area-patterns
---

# Shift Patterns

## When to Use

- Creating or closing Shift records
- Working with ShiftTemplate for recurring shift schedules
- Calculating engine hours accumulated during a shift (EngineHourSession)
- Scoping production, fuel, or machine metrics to a specific shift
- Running the shift change process (PerformShiftChange command)
- Sending shift digest emails (SendShiftDigest command)
- Building or debugging ShiftTemplateManager Livewire component

---

## Core Models

```
Shift            — a single active shift instance (start → end, crew)
ShiftTemplate    — recurring shift definition (name, start time, duration, days)
EngineHourSession — engine-hours logged per machine per shift
```

---

## Shift States

```
scheduled → active → completed
              ↑
     PerformShiftChange activates next template
```

---

## ShiftService API

```php
use App\Services\ShiftService;

$service = app(ShiftService::class);

// Start a new shift from a template
$shift = $service->startShift($shiftTemplate, $team, startedAt: now());

// Close the current active shift
$service->closeShift($shift, closedAt: now());
// → marks shift completed
// → closes all open EngineHourSessions for this shift
// → triggers SendShiftDigest if configured

// Get current active shift for a team
$active = $service->getActiveShift($team);

// Get shift KPI summary
$summary = $service->getShiftSummary($shift);
// Returns: ['bcm' => float, 'tons' => float, 'machines_active' => int,
//           'fuel_consumed' => float, 'engine_hours' => float]
```

---

## EngineHourSession

```
Machine powers on  → EngineHourSession::created (shift_id, machine_id, started_at)
Machine powers off → EngineHourSession updated (ended_at, duration_hours calculated)

Total shift engine hours:
$hours = EngineHourSession::where('shift_id', $shift->id)
    ->sum('duration_hours');
```

---

## Pattern — Creating a Shift Template

```php
POST /api/v1/shift-templates
{
    "name": "Day Shift",
    "start_time": "06:00",
    "duration_hours": 10,
    "days": ["monday","tuesday","wednesday","thursday","friday"],
    "crew_size": 12,
    "is_active": true
}
```

---

## Pattern — Shift Test Setup

```php
#[Test]
public function shift_start_creates_shift_record(): void
{
    $user     = $this->adminUser();
    $template = ShiftTemplate::factory()->create(['team_id' => $user->current_team_id]);

    $service = app(App\Services\ShiftService::class);
    $shift   = $service->startShift($template, $user->currentTeam, now());

    $this->assertDatabaseHas('shifts', [
        'team_id'     => $user->current_team_id,
        'template_id' => $template->id,
        'status'      => 'active',
    ]);
    $this->assertNotNull($shift->started_at);
}

#[Test]
public function closing_shift_ends_all_engine_sessions(): void
{
    $user    = $this->adminUser();
    $shift   = Shift::factory()->active()->create(['team_id' => $user->current_team_id]);
    $session = EngineHourSession::factory()->create([
        'shift_id'   => $shift->id,
        'started_at' => now()->subHours(4),
        'ended_at'   => null,
    ]);

    $service = app(App\Services\ShiftService::class);
    $service->closeShift($shift, now());

    $this->assertNotNull($session->fresh()->ended_at);
    $this->assertSame('completed', $shift->fresh()->status);
}
```

---

## PerformShiftChange Command

```bash
# Run manually (also runs via scheduler)
php artisan shift:change

# This command:
# 1. Finds the currently active shift
# 2. Closes it (ShiftService::closeShift)
# 3. Finds the next scheduled ShiftTemplate for the current time
# 4. Opens a new Shift (ShiftService::startShift)
# 5. Notifies relevant users via NotificationService
```

---

## SendShiftDigest Command

```bash
php artisan shift:digest

# For each team with digest subscriptions:
# - Collects shift KPI summary
# - Generates digest email (production, fuel, alerts for shift)
# - Sends to all DigestSubscription members for that team
```

---

## Shift-Scoped Queries Pattern

```php
// Always scope production/fuel/metrics to a shift using shift_id
$shiftProduction = ProductionRecord::where('shift_id', $shift->id)
    ->where('team_id', $team->id)
    ->selectRaw('SUM(bcm) as bcm, SUM(tons) as tons, COUNT(*) as loads')
    ->first();

$shiftFuel = FuelTransaction::where('shift_id', $shift->id)
    ->where('team_id', $team->id)
    ->sum('quantity_litres');
```

---

## ShiftTemplateManager Livewire Component

```
app/Livewire/ShiftTemplateManager.php
  — CRUD for ShiftTemplate records
  — Preview: shows upcoming shift schedule
  — Activate/deactivate templates
  — Requires: manage_shifts permission
```

---

## ESM Intelligence Handoff

At shift close:
- **production-patterns**: compare shift BCM against shift target
- **maintenance-patterns**: trigger overdue maintenance check for machines used
- **reporting-patterns**: optionally auto-generate shift summary report
- **fleet-intelligence-agent**: update machine utilization stats

---

## Commands Reference

```bash
# Run shift tests
php artisan test --compact tests/Feature/ShiftTest.php

# Check active shift
php artisan tinker --execute '
$service = app(App\Services\ShiftService::class);
$team    = App\Models\Team::first();
$active  = $service->getActiveShift($team);
echo $active ? "Active: {$active->name} since {$active->started_at}" : "No active shift";
'

# List shift templates
php artisan tinker --execute '
App\Models\ShiftTemplate::where("is_active", true)->get(["name","start_time","duration_hours","days"]);
'
```
