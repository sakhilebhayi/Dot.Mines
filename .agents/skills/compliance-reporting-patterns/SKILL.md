---
name: compliance-reporting-patterns
description: >
  Mines platform compliance reporting patterns. Use when: creating ComplianceReport or
  ComplianceViolation records, generating MHSA or DMRE regulatory reports, handling
  ComplianceViolationDetected events, wiring compliance into the notification pipeline,
  writing tests for compliance endpoints, building compliance dashboards, or implementing
  corrective action workflows.
argument-hint: 'Describe the compliance reporting task you need help with'
esm-layer: governance
esm-feeds-to:
  - reporting-patterns
  - audit-logging-patterns
  - notification-system
  - gdpr-data-privacy-patterns
esm-consumes-from:
  - incident-safety-patterns
  - maintenance-patterns
  - audit-logging-patterns
  - shift-patterns
---

# Compliance Reporting Patterns

## When to Use

- Creating or querying ComplianceReport / ComplianceViolation records
- Generating MHSA or DMRE regulatory reports for submission
- Handling ComplianceViolationDetected event (wiring notifications)
- Writing tests for compliance API endpoints
- Building compliance dashboard or violation list views
- Implementing corrective action assignment and tracking
- Understanding the compliance reporting pipeline end-to-end

---

## Core Models

```
ComplianceReport    — a formal compliance report (MHSA, DMRE, internal audit)
ComplianceViolation — a detected non-compliance item requiring corrective action
```

---

## Compliance Report Types

```
mhsa_section_23    — Serious injury / dangerous incident (Section 23 MHSA)
dmre_monthly       — Monthly DMRE operational submission
maintenance_audit  — Internal maintenance compliance audit
safety_inspection  — Scheduled safety inspection record
environmental      — Environmental compliance check
popia_audit        — POPIA data processing compliance
```

---

## Compliance Violation Lifecycle

```
Triggering event occurs (e.g. ComplianceViolationDetected)
       ↓
ComplianceViolation::created (status: open, severity: low|medium|high|critical)
       ↓
SendComplianceViolationNotification → managers notified
       ↓
Corrective action assigned to responsible person
       ↓
Corrective action completed → ComplianceViolation status: resolved
       ↓
If not resolved within due_date → escalated (status: overdue)
       ↓
ComplianceReport generated with all violations for the period
```

---

## Pattern — Creating a Compliance Violation

```php
// Via API
POST /api/v1/compliance/violations
{
    "type": "maintenance_overdue",
    "severity": "high",
    "description": "Machine MX-004 overdue for 500-hour service by 12 days.",
    "machine_id": 4,
    "regulation": "MHSA Section 11",
    "due_date": "2026-06-16",
    "assigned_to": 7
}
```

---

## Pattern — Generating a Compliance Report

```php
// Via API
POST /api/v1/compliance/reports
{
    "type": "mhsa_section_23",
    "period_start": "2026-06-01",
    "period_end": "2026-06-30",
    "include_violations": true,
    "include_incidents": true,
    "format": "pdf"
}
// Dispatches GenerateReportJob with compliance data context
// DMRE format: structured PDF matching DMRE submission template
```

---

## ComplianceViolationDetected Event

```php
// Fired by:
// - MaintenanceRecordObserver when service is overdue
// - IncidentController when MHSA-notifiable incident is created
// - IoTSensorService when critical anomaly persists > 30 min

// Handled by:
// - SendComplianceViolationNotification listener
//   → NotificationService::notifyManagers() with level: high/critical

// Wiring:
// routes/console.php or AppServiceProvider:
Event::listen(ComplianceViolationDetected::class, SendComplianceViolationNotification::class);
```

---

## Pattern — Compliance Test

```php
#[Test]
public function overdue_maintenance_creates_compliance_violation(): void
{
    Queue::fake();
    Event::fake([ComplianceViolationDetected::class]);

    $machine = Machine::factory()->create();
    MaintenanceSchedule::factory()->create([
        'machine_id'   => $machine->id,
        'next_due_at'  => now()->subDays(15), // 15 days overdue
        'status'       => 'overdue',
    ]);

    // Trigger compliance check (normally run by scheduled job)
    $service = app(App\Services\MaintenanceHealthService::class);
    $service->checkComplianceViolations($machine->team);

    Event::assertDispatched(ComplianceViolationDetected::class);
    $this->assertDatabaseHas('compliance_violations', [
        'machine_id' => $machine->id,
        'type'       => 'maintenance_overdue',
    ]);
}

#[Test]
public function manager_can_resolve_compliance_violation(): void
{
    $user      = $this->managerUser();
    $violation = ComplianceViolation::factory()->create([
        'team_id' => $user->current_team_id,
        'status'  => 'open',
    ]);

    $this->actingAs($user, 'sanctum')
        ->patchJson("/api/v1/compliance/violations/{$violation->id}/resolve", [
            'resolution_notes' => 'Maintenance completed and verified.',
        ])
        ->assertOk();

    $this->assertSame('resolved', $violation->fresh()->status);
}
```

---

## MHSA Section 23 Obligations

For any `fatality`, `serious_injury`, or `dangerous_incident`:

```
1. Immediate telephonic notification to Principal Inspector
2. Platform auto-generates MHSA Section 23 report (PDF)
3. Submit written report within 24 hours of incident
4. Preserve scene (platform flags machine as 'scene_preserved')
5. ComplianceReport record created with submitted_at timestamp
```

---

## Corrective Action Tracking

```php
// Check violations approaching due date
ComplianceViolation::where('team_id', $team->id)
    ->where('status', 'open')
    ->where('due_date', '<=', now()->addDays(3))
    ->with(['assignedTo', 'machine'])
    ->get();
// → Generates reminder notifications for assigned persons
```

---

## ESM Intelligence Handoff

- **reporting-patterns**: compliance reports output via GenerateReportJob
- **audit-logging-patterns**: all compliance actions are audit-logged for evidence
- **incident-safety-patterns**: incidents trigger ComplianceViolationDetected automatically
- **notification-system**: all critical violations notify managers immediately

---

## Commands Reference

```bash
# Run compliance tests
php artisan test --compact tests/Feature/ComplianceReportTest.php

# Check open violations by severity
php artisan tinker --execute '
App\Models\ComplianceViolation::selectRaw("severity, COUNT(*) as count")
    ->where("status","open")
    ->groupBy("severity")
    ->get();
'

# Check overdue violations (past due_date and still open)
php artisan tinker --execute '
App\Models\ComplianceViolation::where("status","open")
    ->where("due_date","<",now())
    ->with(["machine","assignedTo"])
    ->get(["id","type","severity","due_date","machine_id","assigned_to"]);
'
```
