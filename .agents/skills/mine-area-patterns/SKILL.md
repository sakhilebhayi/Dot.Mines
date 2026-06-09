---
name: mine-area-patterns
description: >
  Mines platform mine area and spatial intelligence patterns. Use when: creating or updating
  MineArea records, working with MachineAreaAssignment, using MineAreaService, handling
  MinePlanUpload, building MineAreaManager or MineAreaDetail Livewire components, or
  implementing area-based machine filtering and productivity calculations.
argument-hint: 'Describe the mine area task you need help with'
esm-layer: operational
esm-feeds-to:
  - production-patterns
  - dispatch-routing-patterns
  - geofence-patterns
  - fleet-intelligence-agent
esm-consumes-from:
  - geofence-patterns
  - fleet-management
  - file-storage-patterns
---

# Mine Area Patterns

## When to Use

- Creating or querying MineArea records (pits, dumps, workshops, fuel bays)
- Assigning machines to mine areas (MachineAreaAssignment)
- Uploading or downloading mine plans (MinePlanUpload)
- Calculating area-level productivity or congestion
- Building MineAreaManager / MineAreaDetail Livewire components
- Wiring area context into geofences or dispatch routing

---

## Core Models

```
MineArea             — named area in the mine (with type + boundary polygon)
MachineAreaAssignment — which machine is currently assigned to which area
MinePlanUpload        — uploaded mine plan file (PDF/DWG) for an area
```

---

## Area Types

```
pit           — active excavation face
dump          — waste or ore dump destination
workshop      — maintenance and repair area
fuel_bay      — refuelling station area
parking       — machine parking / standby area
haul_road     — road-only zone (no assignment, used for geofences)
office        — administration area
```

---

## Pattern — Creating a Mine Area

```php
POST /api/v1/mine-areas
{
    "name": "North Pit A",
    "type": "pit",
    "description": "Primary excavation face — Level 3",
    "boundary": {
        "type": "Polygon",
        "coordinates": [[
            [28.4521, -25.7641],
            [28.4610, -25.7641],
            [28.4610, -25.7700],
            [28.4521, -25.7700],
            [28.4521, -25.7641]
        ]]
    },
    "target_bcm_per_hour": 60.0
}
```

---

## Pattern — Assigning a Machine to an Area

```php
use App\Services\MineAreaService;

$service = app(MineAreaService::class);
$assignment = $service->assignMachine(
    machine: $machine,
    area: $mineArea,
    assignedBy: $user,
    startAt: now(),
);
// Returns MachineAreaAssignment
// Fires an event → updates LiveMap assignment layer
```

---

## HasTeamFilters

`MineArea` uses `HasTeamFilters` → all queries automatically scope to `current_team_id`.

**CRITICAL:** Attempting to query areas from another team returns **404**, not **403**.

---

## Area Productivity Calculation

```php
$service = app(App\Services\MineAreaService::class);

// Get current productivity for an area
$stats = $service->getAreaProductivity($area, now()->startOfDay(), now());
// Returns: ['bcm' => float, 'tons' => float, 'machines_active' => int, 'bcm_per_hour' => float]

// Detect congestion (too many machines in area)
$congestion = $service->getCongestionScore($area);
// Returns 0–100; > 70 = recommend reallocation
```

---

## Mine Plan Upload

```php
// Upload a new mine plan via API
POST /api/v1/mine-areas/{area}/mine-plan
Content-Type: multipart/form-data
{
    "file": <PDF or DWG file>,
    "version": "Rev 4.2",
    "effective_date": "2026-06-01"
}
// File stored in S3 via FileUploadService
// MinePlanUpload record created

// Download with signed URL
GET /mine-plan/{upload}/download
// MinePlanDownloadController generates a 60-minute signed S3 URL
```

---

## Pattern — Mine Area Test Setup

```php
#[Test]
public function machine_assignment_creates_assignment_record(): void
{
    $user    = $this->adminUser();
    $machine = Machine::factory()->create(['team_id' => $user->current_team_id]);
    $area    = MineArea::factory()->create(['team_id' => $user->current_team_id]);

    $this->actingAs($user, 'sanctum')
        ->postJson("/api/v1/mine-areas/{$area->id}/assign", ['machine_id' => $machine->id])
        ->assertCreated();

    $this->assertDatabaseHas('machine_area_assignments', [
        'machine_id'   => $machine->id,
        'mine_area_id' => $area->id,
    ]);
}

#[Test]
public function mine_areas_are_isolated_between_teams(): void
{
    $userA = $this->adminUser();
    $userB = $this->createUserInSeparateTeam();
    MineArea::factory()->create(['team_id' => $userA->current_team_id]);

    $this->actingAs($userB, 'sanctum')
        ->getJson('/api/v1/mine-areas')
        ->assertJsonCount(0, 'data');
}
```

---

## MineAreaManager Livewire Component

```
app/Livewire/MineAreaManager.php
  — lists all areas with current machine count
  — supports drag-and-drop machine reallocation
  — real-time congestion warnings (> 70 congestion score)

app/Livewire/MineAreaDetail.php
  — shows individual area: productivity chart, assigned machines, mine plan
  — MachineAreaAssignment history table
```

---

## ESM Intelligence Handoff

When area congestion > 70:
- **dispatch-routing-patterns**: recommend rerouting trucks to alternate dump
- **production-patterns**: flag area as productivity bottleneck
- **geofence-patterns**: verify geofence boundaries are correct for area

---

## Commands Reference

```bash
# Run mine area tests
php artisan test --compact tests/Feature/MineAreaTest.php

# List areas and current assignment counts
php artisan tinker --execute '
App\Models\MineArea::withCount("currentAssignments")
    ->get(["id","name","type","current_assignments_count"]);
'

# Check unassigned machines
php artisan tinker --execute '
App\Models\Machine::whereDoesntHave("currentAreaAssignment")
    ->where("status","operational")
    ->get(["id","name","type"]);
'
```
