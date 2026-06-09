# Bell OEM Integration Patterns

## Skill Purpose

Provides a complete Bell Equipment ISO 15143-3 (AEMP 2.0) and Fleetmatic REST API telemetry intelligence layer. Activating this skill gives any agent deep knowledge of the Bell data pipeline — from raw API pull through to intelligence output — so it can reason about fleet data confidence, maintenance readiness, production accuracy, ESG calculations, and dispatch optimisation.

---

## When to Activate This Skill

- Working with Bell Equipment telemetry data (GPS, fuel, hours, payload, engine condition)
- Debugging why a Bell machine's data is stale or missing
- Adding a new Bell API signal endpoint
- Investigating `SyncBellFleetDataJob` or `SyncBellHistoricalDataJob` failures
- Writing tests for Bell integration services
- Implementing alert logic driven by Bell telemetry
- Building production KPIs sourced from Bell load count / payload data
- Calculating ESG metrics (CO₂, fuel intensity) from Bell fuel consumption
- Understanding the Bell machine → `machines` table bridge
- Resolving Bell SSO token authentication failures

---

## Agents That Use This Skill

| Agent | What It Learns from Bell |
|---|---|
| `fleet-intelligence-agent` | Utilisation, distance, idle time, GPS locations |
| `production-intelligence-agent` | Payload totals, load counts, BCM per shift |
| `maintenance-guardian` | Engine condition, caution codes, regen hours |
| `dispatch-optimization-agent` | Live GPS, idle ratio, cycle times |
| `machine-health-agent` | Health score (engine condition, regen, caution codes) |
| `data-integrity-agent` | Cross-validation of OEM vs platform machine records |
| `esg-sustainability-agent` | CO₂ from fuel, tonnes-per-litre KPIs |
| `alert-guardian` | Fuel low, engine warning, machine offline events |
| `platform-guardian` | Bell integration health score |
| `enterprise-decision-intelligence` | Evidence layer for machine-level decisions |

---

## Architecture

```
Bell SSO (OAuth2 Password Credentials)
    │
    ▼
BellIso15143Service                 → /Fleet snapshot (every 15 min)
    │                                   All machines in one XML response
    │                                   ISO 15143-3 AEMP 2.0 format
    │
    ▼
bell_equipment                      (master record per machine)
bell_equipment_current_status       (latest snapshot per machine)
bell_equipment_telemetry_history    (append-only full snapshot rows)
bell_equipment_daily_kpis           (calculated daily aggregate KPIs)
bell_fleet_snapshots                (raw JSON of each fleet pull)
bell_integration_audit_logs         (success/failure per sync run)
    │
    ▼
bridgeToMachine()                   (maps bell_equipment → machines)
    └── fires MachineLocationUpdated (WebSocket → live map)

BellHistoricalTelemetryService      → Per-machine per-signal REST API
    │                                   (Bell Fleetmatic endpoints)
    │
    ├── syncLocations()             → bell_equipment_location_history
    │                                   + BellLocationUpdated event
    ├── syncCumulativeOperatingHours() → bell_equipment_operating_hours_history
    ├── syncCumulativeFuelUsed()    → bell_equipment_fuel_usage_history
    ├── syncFuelUsed24h()           → bell_equipment_fuel_usage_history
    ├── syncCumulativeIdleHours()   → bell_equipment_idle_hours_history
    ├── syncFuelRemainingRatio()    → bell_equipment_fuel_usage_history
    │                                   + bell_fuel_levels
    │                                   + BellFuelLowDetected (< 20%)
    ├── syncCumulativeLoadCount()   → bell_equipment_load_count_history
    ├── syncCumulativePayloadTotals() → bell_equipment_load_count_history
    │                                   + bell_payload_totals (in tonnes)
    ├── syncCautionCodes()          → bell_equipment_caution_codes
    ├── syncDefRemaining()          → bell_equipment_health_history
    │                                   + bell_def_levels
    ├── syncEngineCondition()       → bell_equipment_health_history
    │                                   + BellEngineWarningDetected (non-normal)
    ├── syncActiveRegenerationHours() → bell_equipment_health_history
    │                                   + bell_regeneration_hours
    └── syncDistance()              → bell_distance_travelled

BellMachineIntelligenceService      → Aggregates telemetry into KPIs
    ├── computeMachineSnapshot()    → health score, utilisation, recommendations
    ├── computeFleetSnapshots()     → fleet-wide sorted by health score
    ├── computeEsgMetrics()         → fuel litres, CO₂ kg, L/hr
    └── detectOfflineMachines()     → fires BellMachineOfflineDetected
```

---

## Database Tables

### Core Bell Tables (from ISO15143-3 snapshot sync)

| Table | Primary Use | Updated By |
|---|---|---|
| `bell_equipment` | Master record per machine | `BellIso15143Service` |
| `bell_equipment_current_status` | Latest telemetry snapshot | `BellIso15143Service` |
| `bell_equipment_telemetry_history` | Full row per snapshot | `BellIso15143Service` |
| `bell_equipment_daily_kpis` | Calculated daily KPIs | `BellIso15143Service` |
| `bell_fleet_snapshots` | Raw JSON per sync run | `BellIso15143Service` |
| `bell_integration_audit_logs` | Sync success/failure log | `BellIso15143Service` |

### Per-Signal History Tables (from Fleetmatic REST API)

| Table | Signal | Key Columns |
|---|---|---|
| `bell_equipment_location_history` | Locations | lat, lng, heading, speed, recorded_at |
| `bell_equipment_operating_hours_history` | CumulativeOperatingHours | operating_hours, recorded_at |
| `bell_equipment_fuel_usage_history` | CumulativeFuelUsed, FuelUsed24h, FuelRemainingRatio | fuel_used_cumulative, fuel_remaining_percent |
| `bell_equipment_idle_hours_history` | CumulativeIdleHours | idle_hours, recorded_at |
| `bell_equipment_load_count_history` | CumulativeLoadCount, CumulativePayloadTotals | load_count, cumulative_payload |
| `bell_equipment_health_history` | DEFRemaining, EngineCondition, RegenHours | engine_condition, def_remaining_percent, active_regen_hours |
| `bell_equipment_caution_codes` | CautionCodes | fault_code, severity, occurred_at, resolved_at |

### Dedicated Intelligence Tables (dedicated per-signal, normalised)

| Table | Signal | Key Columns |
|---|---|---|
| `bell_distance_travelled` | Distance | distance_km, snapshot_time |
| `bell_payload_totals` | CumulativePayloadTotals | payload_tonnes, snapshot_time |
| `bell_def_levels` | DEFRemaining | def_remaining_percent, snapshot_time |
| `bell_fuel_levels` | FuelRemainingRatio | fuel_remaining_percent, snapshot_time |
| `bell_regeneration_hours` | CumulativeActiveRegenerationHours | regeneration_hours, snapshot_time |

All tables use `equipment_key` (FK → `bell_equipment.equipment_key`) as the join key.

### Bell–Machine Bridge

`bell_equipment` links to `machines` via:
- `bell_equipment.machine_id` (FK → `machines.id`)
- `bell_equipment.machine_matched_at` (when the match was confirmed)

Matching priority in `BellIso15143Service::bridgeToMachine()`:
1. Cached `machine_id` (fastest — reuses confirmed link)
2. `machines.serial_number = bell_equipment.serial_number`
3. `machines.external_id = bell_equipment.equipment_id`

---

## API Endpoints

### ISO 15143-3 Fleet Snapshot

```
GET {BELL_ISO15143_API_URL}/Fleet
Authorization: Bearer {sso_token}
Content-Type: application/xml
```

Returns all equipment in a single XML document. Polled every 15 minutes by `SyncBellFleetDataJob`.

### Per-Machine Per-Signal Endpoints

```
GET {base_url}/Fleet/Equipment/{OEM ISO Identifier}/{Signal}/{startDateUTC}/{endDateUTC}
Authorization: Bearer {sso_token}
```

**Signals available:**

| Signal | Frequency | Job |
|---|---|---|
| `Locations` | Every 5 min | `SyncBellLocationsJob` |
| `EngineCondition` | Every 5 min | `SyncBellEngineConditionJob` |
| `DEFRemaining` | Every 5 min | `SyncBellEngineConditionJob` |
| `CautionCodes` | Every 5 min | `SyncBellEngineConditionJob` |
| `CumulativePayloadTotals` | Every 15 min | `SyncBellPayloadJob` |
| `CumulativeLoadCount` | Every 15 min | `SyncBellPayloadJob` |
| `CumulativeFuelUsed` | Hourly | `SyncBellFuelJob` |
| `FuelUsedInThePreceding24Hours` | Hourly | `SyncBellFuelJob` |
| `FuelRemainingRatio` | Hourly | `SyncBellFuelJob` |
| `CumulativeOperatingHours` | Hourly | `SyncBellOperatingHoursJob` |
| `CumulativeIdleHours` | Hourly | `SyncBellOperatingHoursJob` |
| `CumulativeActiveRegenerationHours` | Hourly | `SyncBellOperatingHoursJob` |
| `Distance` | Hourly | `SyncBellOperatingHoursJob` |

---

## Authentication

Bell SSO uses OAuth2 Password Credentials grant:

```php
// From config/integrations.php
$ssoTokenUrl  = config('integrations.bell_sso.token_url');     // BELL_SSO_TOKEN_URL
$clientId     = config('integrations.bell_sso.client_id');     // BELL_SSO_CLIENT_ID
$clientSecret = config('integrations.bell_sso.client_secret'); // BELL_ISO15143_CLIENT_SECRET
$username     = config('integrations.bell_sso.username');      // BELL_SSO_USERNAME
$password     = config('integrations.bell_sso.password');      // BELL_SSO_PASSWORD
$scope        = config('integrations.bell_sso.scope');         // ISO_Exports

// Token request
Http::withBasicAuth($clientId, $clientSecret)->asForm()->post($ssoTokenUrl, [
    'grant_type' => 'password',
    'username'   => $username,
    'password'   => $password,
    'scope'      => $scope,
]);
```

Token is cached in-memory for the duration of a single sync run. Each service class stores `private ?string $bearerToken`.

Falls back to HTTP Basic Auth when `BELL_SSO_TOKEN_URL` is not configured.

---

## Events

| Event | Fired When | Payload |
|---|---|---|
| `BellTelemetryReceived` | After any machine's full sync cycle produces new records | equipment, signal, new_records count |
| `BellLocationUpdated` | New location record inserted | equipment, lat, lng, heading, speed, recorded_at |
| `BellFuelLowDetected` | Fuel remaining ≤ 20% | equipment, fuel_remaining_percent, detected_at |
| `BellEngineWarningDetected` | Engine condition is not 'Normal' or 'OK' | equipment, condition_status, detected_at |
| `BellMachineOfflineDetected` | No telemetry for > 2 hours | equipment, last_seen_at, offline_minutes |
| `BellPayloadThresholdExceeded` | Payload exceeds configured threshold | equipment, payload_tonnes, threshold_tonnes |
| `BellMachineHealthChanged` | Health score changes by ≥ 10 points | equipment, previous_score, new_score, reason |
| `MachineLocationUpdated` | After `bridgeToMachine()` writes GPS to `machines` | machine, location array |

### Wiring Events to Alerts

```php
// To wire BellFuelLowDetected into the alert pipeline, add a listener:
// In AppServiceProvider or a dedicated listener:

Event::listen(BellFuelLowDetected::class, function (BellFuelLowDetected $event) {
    Alert::create([
        'team_id'     => $event->equipment->machine?->team_id,
        'type'        => 'fuel_low',
        'severity'    => 'high',
        'title'       => "Fuel Low: {$event->equipment->equipment_id}",
        'message'     => "Fuel at {$event->fuelRemainingPercent}%",
        'data'        => ['equipment_id' => $event->equipment->equipment_id],
    ]);
});
```

---

## Services

### BellIso15143Service

Full ISO 15143-3 snapshot sync. Use for fleet-wide snapshot processing.

```php
$service = new BellIso15143Service(
    config('integrations.bell_iso15143.api_url'),
    config('integrations.bell_iso15143.api_username'),
    config('integrations.bell_iso15143.api_password'),
    config('integrations.bell_sso.token_url'),
    config('integrations.bell_sso.client_id'),
    config('integrations.bell_sso.client_secret'),
);

$result = $service->sync();
// Returns: ['success' => bool, 'processed' => int, 'inserted' => int, 'updated' => int, 'error' => string|null]
```

### BellHistoricalTelemetryService

Per-machine, per-signal REST API sync. Two calling patterns:

```php
// Sync all signals for all machines (full hourly run):
$service->syncHistoricalData(hours: 1);

// Sync a single signal for all machines (per-signal jobs):
$service->syncSignal('Locations', hours: 0.25);   // last 15 min
$service->syncSignal('EngineCondition', hours: 0.25);
$service->syncSignal('CumulativePayloadTotals', hours: 0.5);
// Returns: ['fetched' => int, 'inserted' => int, 'skipped' => int]
```

### BellMachineIntelligenceService

Read-only aggregation service. Produces health scores and recommendations.

```php
$intel = new BellMachineIntelligenceService;

// Single machine snapshot:
$snapshot = $intel->computeMachineSnapshot($bellEquipment);
// Returns: health_score, utilisation_percent, idle_ratio_percent, recommendations[], confidence

// Fleet-wide sorted by health score (worst first):
$fleet = $intel->computeFleetSnapshots();

// ESG metrics:
$esg = $intel->computeEsgMetrics($bellEquipment);
// Returns: estimated_fuel_litres, estimated_co2_kg, fuel_per_operating_hour

// Detect and fire BellMachineOfflineDetected for machines silent > 2h:
$offline = $intel->detectOfflineMachines(thresholdMinutes: 120);
```

---

## Health Score Dimensions

The Bell Machine Health Score (0–100) is a weighted composite:

| Dimension | Weight | 100 | 55 | 10 |
|---|---|---|---|---|
| Engine Condition | 30% | Normal/OK | Warning | Error/Fault |
| Open Caution Codes | 25% | 0 codes | 1–2 codes | 5+ codes |
| Idle Hours Ratio | 15% | 0–15% idle | 15–35% | >35% |
| Fuel Efficiency Score | 15% | >40% fuel | 21–40% | ≤20% |
| Regen Hours Ratio | 15% | 0–5% regen | 5–10% | >10% |

---

## ESG Calculations

```php
// Carbon intensity (IPCC standard factor for diesel)
$co2Kg = $fuelLitres * 2.68;

// Fleet fuel intensity
$fuelPerBcm = $totalFuelLitres / $totalBcmMoved;

// Tonnes per litre
$tonnesPerLitre = $totalPayloadTonnes / $totalFuelLitres;
```

---

## Known Integration Patterns

### Testing Bell Services

```php
use Illuminate\Support\Facades\Http;

// Fake all Bell API calls
Http::fake([
    '*/Fleet' => Http::response($this->validFleetXml(), 200, ['Content-Type' => 'application/xml']),
    '*/Fleet/Equipment/*/Locations/*' => Http::response($this->validLocationXml(), 200),
    '*/connect/token' => Http::response(['access_token' => 'test-token', 'expires_in' => 3600], 200),
]);

// Run sync and assert
$result = (new BellHistoricalTelemetryService(...))->syncSignal('Locations', hours: 1);
$this->assertGreaterThan(0, $result['inserted']);
```

### Checking Data Freshness

```php
// Check when a machine last reported telemetry
$status = BellEquipmentCurrentStatus::where('equipment_key', $key)->first();
$staleSince = $status?->updated_date 
    ? now()->diffInMinutes(Carbon::parse($status->updated_date))
    : null;

// Machine considered offline if no update in 2+ hours
if ($staleSince > 120) {
    // Data quality issue — flag for data-integrity-agent
}
```

### Tracing a Machine from Bell to Platform

```php
// 1. Find Bell record
$bell = BellEquipment::where('equipment_id', 'ASA B50E#9086')->firstOrFail();

// 2. Find linked machine
$machine = $bell->machine; // via machine_id FK

// 3. Check when bridge was established
echo $bell->machine_matched_at;

// 4. Latest telemetry
$status = $bell->currentStatus;
echo $status->last_telemetry_date;
```

### Querying Intelligence Layer

```php
$intel = new BellMachineIntelligenceService;
$snapshots = $intel->computeFleetSnapshots();

// Machines with health score < 60 (needs attention)
$critical = $snapshots->filter(fn ($s) => $s['health_score'] < 60);

// Machines with >35% idle ratio (dispatch inefficiency)
$highIdle = $snapshots->filter(fn ($s) => $s['idle_ratio_percent'] > 35);

// All machines and their recommendations
foreach ($snapshots as $snapshot) {
    foreach ($snapshot['recommendations'] as $rec) {
        echo "[{$snapshot['equipment_id']}] {$rec}\n";
    }
}
```

---

## Common Errors and Fixes

| Error | Cause | Fix |
|---|---|---|
| `sync()` returns `success: false` | SSO token request failed | Check `BELL_SSO_*` env vars; verify Bell SSO is reachable |
| `bell_historical.base_url not configured` | Missing env var | Set `BELL_ISO15143_API_URL` in `.env` |
| `No Machine match for equipment_id=` | Machine not yet registered in platform | Create a `Machine` record with matching `serial_number` or `external_id` |
| `Interface SessionHandlerInterface not found` | Missing PHP extension | Install `php-session` extension |
| `simplexml_load_string() undefined` | Missing PHP extension | Install `php-simplexml` extension |
| `vw_bell_fleet_current_status` view error on migration | SQLite drops views on table alter | Migration drops and recreates both views — check migration order |
| `BellLocationUpdated` event not dispatching | GPS coordinates are null | Only dispatches when `lat !== null && lng !== null` |

---

## Bell Integration Readiness Scorecard

| Component | Weight | Measures |
|---|---|---|
| Telemetry Coverage | 20% | % of machines reporting within last 2h |
| Data Freshness | 15% | Average age of last telemetry record |
| Data Quality | 15% | % of records passing validation |
| Machine Health Accuracy | 15% | Correlation of health score to actual maintenance events |
| Production Accuracy | 15% | Payload totals vs shift production records |
| Dispatch Accuracy | 10% | Location/GPS data age vs dispatch cycle time |
| ESG Coverage | 10% | % of machines with fuel consumption data |

**Target: 95%+ Bell Integration Readiness**

To compute the current score, query:

```php
$intel = new BellMachineIntelligenceService;
$offline = $intel->detectOfflineMachines(120);
$totalMachines = BellEquipment::count();
$onlineMachines = $totalMachines - count($offline);
$coverageScore = $totalMachines > 0 ? ($onlineMachines / $totalMachines) * 100 : 0;
```
