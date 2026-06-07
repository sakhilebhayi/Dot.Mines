---
name: geofence-patterns
description: >
  Mines platform geofence management patterns. Use when: creating or updating geofences, writing
  geofence API tests, debugging geofence crossing detection, understanding geofence polygon
  coordinates format, working with GeofenceEntry records, calculating tonnage stats, or building
  geofence-related Livewire components.
argument-hint: 'Describe the geofence task you need help with'
---

# Geofence Patterns

## When to Use

- Creating geofences via API (store, update, delete)
- Writing tests for geofence CRUD and isolation
- Debugging GeofenceCrossingDetectionJob not firing
- Working with geofence coordinate polygon format
- Building the GeofenceManager or GeofenceDetail UI

---

## Geofence Model Key Facts

```php
// app/Models/Geofence.php
// Traits: HasTeamFilters → cross-team access returns 404 (not 403)
// Types: 'pit', 'stockpile', 'dump', 'facility'
// Status: 'active', 'inactive'
// Coordinates: JSON array of [longitude, latitude] pairs (GeoJSON polygon format)
```

---

## Coordinates Format (GeoJSON Polygon)

```php
// Coordinates are an array of [lng, lat] pairs — NOTE: longitude FIRST
// The polygon must be closed (first = last point)
$coordinates = [
    [27.5, -27.5],       // [lng, lat]
    [27.51, -27.5],
    [27.51, -27.49],
    [27.5, -27.49],
    [27.5, -27.5],       // close polygon
];

// When sending via API, pass as JSON string:
'coordinates' => json_encode($coordinates)
```

---

## Pattern — Geofence API Test Payload

```php
private function validGeofencePayload(): array
{
    return [
        'name'             => 'Test Pit Zone',
        'type'             => 'pit',           // pit|stockpile|dump|facility
        'description'      => 'Test area',
        'coordinates'      => json_encode([
            [27.5, -27.5], [27.51, -27.5],
            [27.51, -27.49], [27.5, -27.49],
            [27.5, -27.5],
        ]),
        'center_latitude'  => -27.5,
        'center_longitude' => 27.5,
        'area_sqm'         => 50000,
        'perimeter_m'      => 900,
    ];
}
```

---

## Pattern — Crossing Detection

```php
// The job checks if machine GPS coordinates fall inside geofence polygons
// Fires:
// - GeofenceEntryDetected::dispatch($machine, $geofence) on entry
// - GeofenceExitDetected::dispatch($machine, $geofence) on exit
// Both listeners → SendGeofenceBreachNotification

// To debug:
php artisan tinker --execute 'App\Jobs\GeofenceCrossingDetectionJob::dispatchSync();'
```

---

## Pattern — Tonnage Stats Test

```php
#[Test]
public function tonnage_stats_returns_correct_data(): void
{
    $user = $this->adminUser();
    $geofence = Geofence::factory()->create(['team_id' => $user->current_team_id]);

    // Create entry records with tonnage
    // GeofenceEntry::factory()->create([...])

    $this->actingAs($user, 'sanctum')
        ->getJson("/api/v1/geofences/{$geofence->id}/tonnage-stats?date_from=2026-01-01&date_to=2026-12-31")
        ->assertOk()
        ->assertJsonStructure(['data' => ['total_tonnage', 'entries_count', 'by_machine']]);
}
```

---

## Commands Reference

```bash
# Run geofence crossing detection
php artisan tinker --execute 'App\Jobs\GeofenceCrossingDetectionJob::dispatch();'

# Count geofences per team
php artisan tinker --execute 'App\Models\Geofence::withoutGlobalScopes()->selectRaw("team_id, count(*) c")->groupBy("team_id")->get();'

# Run geofence tests
php artisan test --compact tests/Feature/GeofenceManagerTest.php tests/Feature/GeofenceCrossingDetectionJobTest.php
```
