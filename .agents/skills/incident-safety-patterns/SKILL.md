---
name: incident-safety-patterns
description: >
  Mines platform incident and safety management patterns. Use when: creating or querying Incident
  records, working with OperatorFatigue detection, handling ComplianceViolationDetected events,
  wiring safety alerts into the notification pipeline, building safety-related Livewire views,
  implementing MHSA incident obligations, or writing tests for safety workflows.
argument-hint: 'Describe the incident or safety task you need help with'
esm-layer: governance
esm-feeds-to:
  - compliance-reporting-patterns
  - alert-system
  - audit-logging-patterns
  - notification-system
  - community-feed-patterns
esm-consumes-from:
  - iot-sensor-patterns
  - machine-health-patterns
  - live-map-patterns
  - shift-patterns
---

# Incident & Safety Patterns

## When to Use

- Creating or querying Incident records (accidents, near-misses, observations)
- Working with OperatorFatigue model and detection logic
- Handling ComplianceViolationDetected event in safety context
- Writing tests for incident classification or safety notifications
- Integrating safety events with MHSA compliance requirements
- Wiring fatigue detection into the alert pipeline
- Building safety dashboard or incident list views

---

## Core Models

```
Incident        — safety incident (accident, near-miss, unsafe observation, fatality)
OperatorFatigue — AI-detected fatigue event for a machine operator during a shift
```

---

## Incident Classification (MHSA)

```
fatality           — Section 23 MHSA: immediate regulator notification required
serious_injury     — Section 23 MHSA: within 24 hours
dangerous_incident — Section 23 MHSA: within 24 hours (no injury but high risk)
near_miss          — internal reporting only (< 24 hours recommended)
unsafe_condition   — observation, no injury, corrective action required
```

---

## Incident Lifecycle

```
Incident reported (API or FeedPost of type 'safety')
       ↓
Incident::created (status: open)
       ↓
Alert created (type: 'safety_incident', level based on classification)
       ↓
NotificationService::notifyManagers() — critical/serious → immediate
       ↓
Incident investigation assigned
       ↓
ComplianceViolationDetected::dispatch if corrective action overdue
       ↓
Incident status: under_investigation → corrective_action → closed
       ↓
DMRE/compliance report generated (see compliance-reporting-patterns)
```

---

## Pattern — Recording an Incident

```php
POST /api/v1/incidents
{
    "type": "near_miss",
    "description": "Haul truck came within 2m of pedestrian at fuel bay junction.",
    "location": "Fuel Bay — North Entrance",
    "machine_id": 5,
    "operator_id": 12,
    "occurred_at": "2026-06-09T08:45:00Z",
    "witnesses": [14, 17],
    "injuries": false,
    "immediate_action_taken": "Area cordoned, pedestrian controls reviewed"
}
```

---

## OperatorFatigue Detection

```
OperatorFatigue is created by FatigueDetectionAgent (AI) when:
  - Operator has been on shift > 10 hours without break
  - Machine speed patterns show erratic behaviour (sudden braking)
  - Machine idle time patterns suggest micro-sleep events
  - IoT fatigue sensor (where fitted) exceeds threshold

When fatigue detected:
  → Alert created (type: 'operator_fatigue', level: 'critical')
  → Shift supervisor notified immediately
  → OperatorFatigue record created for audit trail
  → Dispatch system prevents new dispatch to that operator
```

---

## Pattern — Incident Test Setup

```php
#[Test]
public function safety_incident_creates_critical_alert(): void
{
    Queue::fake();
    $user    = $this->adminUser();
    $machine = Machine::factory()->create(['team_id' => $user->current_team_id]);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/incidents', [
            'type'        => 'serious_injury',
            'description' => 'Operator injured during maintenance.',
            'occurred_at' => now()->toIso8601String(),
            'machine_id'  => $machine->id,
            'injuries'    => true,
        ])
        ->assertCreated();

    $this->assertDatabaseHas('alerts', [
        'type'  => 'safety_incident',
        'level' => 'critical',
    ]);
}

#[Test]
public function fatality_triggers_immediate_manager_notification(): void
{
    Notification::fake();
    $user    = $this->adminUser();
    $machine = Machine::factory()->create(['team_id' => $user->current_team_id]);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/incidents', [
            'type'        => 'fatality',
            'description' => 'Test fatality report.',
            'occurred_at' => now()->toIso8601String(),
            'machine_id'  => $machine->id,
        ])
        ->assertCreated();

    $this->assertDatabaseHas('notifications', [
        'type'  => 'safety',
        'level' => 'critical',
    ]);
}
```

---

## MHSA Legal Obligations (Section 23)

For `fatality`, `serious_injury`, or `dangerous_incident`:
1. Preserve the scene — do not move equipment without authorization
2. Notify the Principal Inspector immediately (telephonically)
3. Submit written report within 24 hours
4. **CRITICAL:** This platform must generate DMRE incident report automatically (see compliance-reporting-patterns)

---

## Fatigue Monitoring Integration

```php
// Check if operator is cleared for new shift/dispatch
$fatigue = OperatorFatigue::where('operator_id', $operator->id)
    ->where('shift_id', $currentShift->id)
    ->whereNull('cleared_at')
    ->first();

if ($fatigue) {
    // Block dispatch, return 422 with reason
    return response()->json(['error' => 'Operator fatigue detected. Supervisor clearance required.'], 422);
}
```

---

## ESM Intelligence Handoff

When fatality or serious injury is recorded:
- **compliance-reporting-patterns**: auto-generate DMRE report, flag for immediate submission
- **audit-logging-patterns**: create tamper-proof audit record immediately
- **alert-system**: escalate to all managers, do not deduplicate
- **community-feed-patterns**: safety feed post auto-created for team awareness

---

## Commands Reference

```bash
# Run safety tests
php artisan test --compact tests/Feature/IncidentSafetyTest.php

# Check open incidents
php artisan tinker --execute '
App\Models\Incident::whereIn("status",["open","under_investigation"])
    ->with("machine","operator")
    ->get(["id","type","status","occurred_at","machine_id"]);
'

# Check active fatigue flags
php artisan tinker --execute '
App\Models\OperatorFatigue::whereNull("cleared_at")->with("operator","shift")->get();
'
```
