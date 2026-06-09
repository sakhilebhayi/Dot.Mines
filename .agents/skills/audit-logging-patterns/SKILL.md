---
name: audit-logging-patterns
description: >
  Mines platform audit logging and forensic tracing patterns. Use when: writing audit log entries,
  querying AuditLog or ActivityLog records, working with AuditService, debugging AgentPerformanceLog,
  verifying FeedAuditLog or BellIntegrationAuditLog, checking Sentry health via CheckSentryHealthCommand,
  understanding log retention policy, or building compliance evidence from audit trails.
argument-hint: 'Describe the audit or forensic tracing task you need help with'
esm-layer: governance
esm-feeds-to:
  - compliance-reporting-patterns
  - gdpr-data-privacy-patterns
  - security-agent
  - observability-audit-agent
esm-consumes-from:
  - rbac-patterns
  - billing-subscription-patterns
  - fleet-management
---

# Audit Logging Patterns

## When to Use

- Writing a new AuditLog entry for a security-sensitive action
- Querying AuditLog or ActivityLog for forensic investigation
- Using AuditService to record structured events
- Debugging why an audit entry is missing
- Working with AgentPerformanceLog for AI decision tracing
- Verifying FeedAuditLog (moderation actions) or BellIntegrationAuditLog (OEM sync events)
- Building compliance evidence reports
- Checking Sentry connectivity (CheckSentryHealthCommand)

---

## Core Models

```
AuditLog              — platform-level security and data change events
ActivityLog           — user action log (page views, feature usage)
AgentPerformanceLog   — AI agent decision trace (input, output, confidence, duration)
FeedAuditLog          — moderation action log (approve, reject, flag)
BellIntegrationAuditLog — OEM sync events (success/failure per machine)
NotificationDeliveryLog — email delivery outcomes per notification
SentEmail             — raw sent email record (recipient, subject, status)
```

---

## AuditService API

```php
use App\Services\AuditService;

$service = app(AuditService::class);

// Log a security-relevant action
$service->log(
    event: 'machine.deleted',
    subject: $machine,          // Eloquent model being acted on
    actor: $user,               // User performing the action
    metadata: [
        'reason' => 'Decommissioned',
        'ip'     => request()->ip(),
    ],
);

// Log an agent decision
$service->logAgentDecision(
    agent: 'maintenance-predictor',
    decision: 'schedule_maintenance',
    confidence: 87.5,
    evidence: ['engine_hours' => 4800, 'last_service' => '2026-03-01'],
    outcome: 'recommended',
);
```

---

## Mandatory Audit Events

These events **must always** be audit-logged:

```
user.login             user.logout          user.failed_login
user.created           user.deleted         user.role_changed
machine.created        machine.deleted      machine.transferred
team.created           team.settings_changed
payment.processed      subscription.changed
gdpr.export_requested  gdpr.deletion_requested
report.generated       report.downloaded
integration.synced     integration.failed
```

---

## Pattern — Querying Audit Logs

```php
// Forensic query: all actions on a machine in last 30 days
$logs = AuditLog::where('subject_type', Machine::class)
    ->where('subject_id', $machine->id)
    ->where('created_at', '>=', now()->subDays(30))
    ->with('actor')
    ->orderByDesc('created_at')
    ->get();

// Security query: failed logins in last hour
$failedLogins = AuditLog::where('event', 'user.failed_login')
    ->where('created_at', '>=', now()->subHour())
    ->selectRaw('metadata->>"$.ip" as ip, COUNT(*) as attempts')
    ->groupBy('ip')
    ->having('attempts', '>=', 5)
    ->get();
// → If any IP has >= 5 failures in 1 hour → alert security
```

---

## Agent Decision Tracing

```php
// Every AI agent recommendation must be traced
AgentPerformanceLog::create([
    'agent_name'     => 'fuel-predictor',
    'action'         => 'predict_consumption',
    'input_context'  => json_encode(['machine_id' => 3, 'shift_hours' => 8]),
    'output'         => json_encode(['predicted_litres' => 320.5]),
    'confidence'     => 84.2,
    'duration_ms'    => 142,
    'decision_score' => 84,    // feeds enterprise-decision-intelligence
    'evidence'       => json_encode(['historical_avg' => 310, 'recent_trend' => '+3%']),
]);
```

---

## Pattern — Audit Log Test

```php
#[Test]
public function machine_deletion_is_audit_logged(): void
{
    $user    = $this->adminUser();
    $machine = Machine::factory()->create(['team_id' => $user->current_team_id]);

    $this->actingAs($user, 'sanctum')
        ->deleteJson("/api/v1/machines/{$machine->id}")
        ->assertNoContent();

    $this->assertDatabaseHas('audit_logs', [
        'event'        => 'machine.deleted',
        'actor_id'     => $user->id,
        'subject_type' => Machine::class,
        'subject_id'   => $machine->id,
    ]);
}
```

---

## Log Retention Policy

```
AuditLog        — 7 years (legal/compliance requirement)
ActivityLog     — 1 year (analytics use)
AgentPerformanceLog — 90 days (AI calibration)
FeedAuditLog    — 2 years (moderation accountability)
NotificationDeliveryLog — 90 days
SentEmail       — 90 days

PurgeOldAuditLogsJob enforces these automatically (runs monthly).
```

---

## Sentry Health Check

```bash
php artisan sentry:health-check
# Verifies Sentry DSN is reachable and events are being received
# Sends a test event and confirms delivery
```

---

## ESM Intelligence Handoff

- **compliance-reporting-patterns**: audit log is the primary compliance evidence source
- **gdpr-data-privacy-patterns**: all DSAR actions must have an audit_log entry
- **security-agent**: failed login patterns, privilege escalation events
- **observability-audit-agent**: forensic timeline reconstruction

---

## Commands Reference

```bash
# Run audit tests
php artisan test --compact tests/Feature/AuditLogTest.php

# Recent critical audit events
php artisan tinker --execute '
App\Models\AuditLog::whereIn("event", ["user.failed_login","machine.deleted","user.role_changed"])
    ->latest()
    ->limit(20)
    ->get(["event","actor_id","subject_type","subject_id","created_at"]);
'

# Check agent decision confidence distribution
php artisan tinker --execute '
App\Models\AgentPerformanceLog::selectRaw(
    "agent_name, AVG(confidence) as avg_confidence, COUNT(*) as decisions"
)->groupBy("agent_name")->get();
'
```
