---
name: live-map-patterns
description: >
  Mines platform real-time live map patterns. Use when: working with MapEvent or MapEventService,
  building or debugging the LiveMap or FleetMovementReplay Livewire components, handling
  MachineLocationUpdated events, debugging GPS streaming via Reverb/Echo, working with
  MachineLocationUpdateJob, or understanding WebSocket channel authorization for map data.
argument-hint: 'Describe the live map task you need help with'
esm-layer: operational
esm-feeds-to:
  - dispatch-routing-patterns
  - fleet-intelligence-agent
  - geofence-patterns
  - alert-system
  - production-patterns
esm-consumes-from:
  - fleet-management
  - geofence-patterns
  - mine-area-patterns
  - reverb-websocket-patterns
---

# Live Map Patterns

## When to Use

- Working with MapEvent records or MapEventService
- Debugging real-time GPS position streaming (Reverb/Echo)
- Building or debugging LiveMap or FleetMovementReplay Livewire components
- Understanding MachineLocationUpdateJob and MachineLocationUpdated events
- Wiring live map data into dispatch, geofence, or alert systems
- Writing tests for location-based features

---

## Core Models

```
MapEvent  — a timestamped GPS event for a machine (lat, lng, speed, heading, status)
```

---

## Location Update Pipeline

```
GPS device sends location
       ↓
MachineLocationUpdateJob::handle()
  → Machine model: lat/lng/speed/heading updated
  → MapEvent::created (raw event stored)
  → MapEventRecorded::dispatch($event)
       ↓
MapEventService::processEvent($event)
  → GeofenceCrossingDetectionJob::dispatch($machine) — checks geofence entries/exits
  → RouteSpeedMonitoringJob::dispatch($machine)      — checks speed limit
  → MachineLocationUpdated::broadcast() via Reverb
       ↓
LiveMap Livewire component receives broadcast
  → updates machine marker position on map
```

---

## MapEventService API

```php
use App\Services\MapEventService;

$service = app(MapEventService::class);

// Process an incoming GPS reading
$event = $service->processEvent(
    machine: $machine,
    latitude: -25.7641,
    longitude: 28.4521,
    speed: 42.5,             // km/h
    heading: 185,            // degrees
    recordedAt: now(),
);

// Get last known position for a machine
$position = $service->getLastPosition($machine);
// Returns: MapEvent | null

// Replay historical movement for a machine
$replay = $service->getMovementHistory(
    machine: $machine,
    from: now()->subHours(8),
    to: now(),
);
// Returns: Collection<MapEvent> ordered by recorded_at
```

---

## LiveMap Machine Data Payload

`LiveMap::getMachines()` returns enriched machine data. Each machine has:

```php
[
    // Core machine fields (from machines table)
    'id', 'name', 'machine_type', 'manufacturer', 'model',
    'last_location_latitude',  // ← may be overridden by live Bell telemetry
    'last_location_longitude', // ← may be overridden by live Bell telemetry

    // Live telemetry (from MachineTelemetryService)
    'telemetry_status'         => 'Working',   // human-readable status label
    'telemetry_status_key'     => 'working',   // machine-readable key
    'engine_running'           => true,
    'fuel_remaining_percent'   => 72.5,
    'telemetry_operating_hours' => 4821.3,
    'telemetry_load_count'     => 4180,
    'speed_kmh'                => 32.4,
    'heading_degrees'          => 185,         // for map marker rotation
    'last_seen_human'          => '2 minutes ago',
    'last_seen_at'             => '2026-07-12T10:23:00+02:00',
    'is_stale'                 => false,       // true when data is 15–30 min old
    'data_age_minutes'         => 2,
    'telemetry_source'         => 'bell',      // 'bell'|'machine_metric'|'machine'|'none'
]
```

**Map marker heading:** Use `heading_degrees` to rotate SVG markers for directional arrows.
**Stale data warning:** Show a warning icon on markers when `is_stale = true`.
**Offline markers:** Grey out markers when `telemetry_status_key = 'offline'`.

---

## Real-Time Broadcasting

```
Channel: private-team.{teamId}.map
Event:   machine.location.updated

Payload:
{
    "machine_id": 3,
    "lat": -25.7641,
    "lng": 28.4521,
    "speed": 42.5,
    "heading": 185,
    "status": "travelling",
    "recorded_at": "2026-06-09T14:23:00Z"
}
```

---

## LiveMap Livewire Component

```
app/Livewire/LiveMap.php

Key state:
  $machines            — all team machines with live telemetry enrichment
  $geofences           — team geofences (polygons for overlay)
  $mineAreas           — area boundaries for overlay
  $pollInterval        — seconds between wire:poll refreshes (from BELL_UI_POLL_SECONDS)
  $selectedStatus      — filter by telemetry status (working/idling/offline...)
  $selectedMineAreaId  — filter by mine area

Live coordinates: getMachines() prefers Bell telemetry lat/lng over Machine.last_location_*
when Bell current status provides a more recent fix.

Real-time listener:
  #[On('echo-private:team.{teamId}.map,machine.location.updated')]
  public function onLocationUpdate(array $data): void

Alpine.js integration:
  x-data="mapComponent()" — manages Leaflet/MapLibre GL JS map instance
  wire:poll.{pollInterval}s triggers getMachines() refresh
```

---

## FleetMovementReplay Component

```
app/Livewire/FleetMovementReplay.php

Usage: Historical playback of machine movements for investigation
  $machineId  — machine to replay
  $date       — date of replay
  $speed      — playback speed multiplier (1x, 2x, 5x)

Data: MapEvent::where('machine_id', $id)->whereBetween('recorded_at', [$start, $end])
```

---

## Pattern — Live Map Test

```php
#[Test]
public function location_update_creates_map_event(): void
{
    $user    = $this->adminUser();
    $machine = Machine::factory()->create(['team_id' => $user->current_team_id]);

    $service = app(App\Services\MapEventService::class);
    $service->processEvent($machine, -25.7641, 28.4521, 45.0, 180, now());

    $this->assertDatabaseHas('map_events', [
        'machine_id' => $machine->id,
        'latitude'   => -25.7641,
        'longitude'  => 28.4521,
    ]);
    $this->assertEquals(-25.7641, $machine->fresh()->latitude);
}
```

---

## ESM Intelligence Handoff

When a machine stops moving (speed = 0) for > 10 minutes:
- **alert-system**: create 'machine_idle' alert if in active zone
- **dispatch-routing-patterns**: flag as potential queue delay
- **machine-health-patterns**: check if engine is running (stall vs. parked)

When a machine enters an unexpected area:
- **geofence-patterns**: trigger GeofenceCrossingDetectionJob
- **alert-system**: notify team of unauthorized area entry

---

## Commands Reference

```bash
# Run live map tests
php artisan test --compact tests/Feature/LiveMapTest.php

# Check machines with stale GPS (> 15 min since last MapEvent)
php artisan tinker --execute '
App\Models\Machine::with(["mapEvents" => fn($q) => $q->latest()->limit(1)])
    ->get()
    ->filter(fn($m) => ! $m->mapEvents->first() || $m->mapEvents->first()->recorded_at->lt(now()->subMinutes(15)))
    ->map(fn($m) => ["id" => $m->id, "name" => $m->name, "last" => $m->mapEvents->first()?->recorded_at]);
'

# Total map events today
php artisan tinker --execute '
App\Models\MapEvent::whereDate("recorded_at", today())->count();
'
```
