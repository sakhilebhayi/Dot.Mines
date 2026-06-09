---
name: reporting-patterns
description: >
  Mines platform reporting engine patterns. Use when: generating PDF or CSV reports, working with
  the Report model, using ReportGenerator or Reports Livewire components, debugging GenerateReportJob,
  understanding ReportPolicy, implementing scheduled reports, building executive or compliance
  output, or integrating report delivery with the notification system.
argument-hint: 'Describe the reporting task you need help with'
esm-layer: governance
esm-feeds-to:
  - compliance-reporting-patterns
  - esg-reporting-patterns
  - financial-intelligence-agent
  - audit-logging-patterns
esm-consumes-from:
  - production-patterns
  - shift-patterns
  - maintenance-patterns
  - fuel-patterns
  - compliance-reporting-patterns
  - esg-reporting-patterns
---

# Reporting Patterns

## When to Use

- Generating a new report (PDF or CSV) for any domain
- Debugging GenerateReportJob failures or missing output
- Working with the Report model lifecycle (pending → generating → completed)
- Building a new report type in ReportGenerator or Reports Livewire
- Implementing scheduled report delivery
- Understanding ReportPolicy (who can generate what)
- Linking a generated report to the notification system

---

## Core Models

```
Report — a generated report record (stores type, status, S3 path, team, requester)
```

---

## Report Types

```
production_daily       — BCM/tons by machine per day
production_monthly     — Monthly production summary vs target
maintenance_compliance — Outstanding and completed maintenance records
fuel_consumption       — Fuel usage per machine / per tank
fleet_utilization      — Machine uptime, idle time, availability %
safety_incident        — Incidents and near-misses for period
compliance_dmre        — DMRE regulatory submission format
esg_emissions          — Carbon emissions from fuel consumption
executive_summary      — Cross-domain board-level summary
```

---

## Report Lifecycle

```
User requests report (API or Livewire)
       ↓
Report::created (status: pending)
       ↓
GenerateReportJob dispatched (queue: default)
       ↓
Job collects data → renders PDF via generate_pdf.py or CSV via League\Csv
       ↓
Output uploaded to S3 (path: reports/{team_id}/{report_id}.pdf)
       ↓
Report status → completed, s3_path updated
       ↓
ReportController generates signed URL on download request
       ↓
Optional: notification sent to requester via NotificationService
```

---

## Pattern — Requesting a Report

```php
// API
POST /api/v1/reports
{
    "type": "production_monthly",
    "period_start": "2026-06-01",
    "period_end": "2026-06-30",
    "format": "pdf",             // pdf|csv
    "filters": {
        "mine_area_id": 2,       // optional
        "machine_id": null
    }
}
// Returns Report with status: pending
```

---

## Pattern — Downloading a Report

```php
// API
GET /api/v1/reports/{report}/download
// Returns { "url": "https://s3.amazonaws.com/..." } (signed, 60-min expiry)

// Or via web controller with streaming
GET /reports/{report}/download
// ReportDownloadController: validates policy, generates signed URL, redirects
```

---

## Pattern — Report Test Setup

```php
#[Test]
public function manager_can_request_report(): void
{
    Queue::fake();
    $user = $this->managerUser();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/reports', [
            'type'         => 'production_monthly',
            'period_start' => '2026-06-01',
            'period_end'   => '2026-06-30',
            'format'       => 'pdf',
        ])
        ->assertCreated();

    Queue::assertPushed(GenerateReportJob::class);
    $this->assertDatabaseHas('reports', [
        'team_id' => $user->current_team_id,
        'type'    => 'production_monthly',
        'status'  => 'pending',
    ]);
}

#[Test]
public function operator_cannot_request_report(): void
{
    $user = $this->operatorUser(); // does not have generate_reports permission

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/reports', ['type' => 'production_monthly', 'format' => 'pdf'])
        ->assertForbidden();
}

#[Test]
public function reports_are_isolated_between_teams(): void
{
    $userA = $this->adminUser();
    $userB = $this->createUserInSeparateTeam();
    Report::factory()->create(['team_id' => $userA->current_team_id]);

    $this->actingAs($userB, 'sanctum')
        ->getJson('/api/v1/reports')
        ->assertJsonCount(0, 'data');
}
```

---

## ReportGenerator Livewire Component

```
app/Livewire/ReportGenerator.php
  — form to select type, period, format, filters
  — submits to API → shows pending status
  — polls (or Echo listen) for status change
  — Download button appears when status = completed

app/Livewire/Reports.php
  — report history list for current team
  — filter by type, status, date
  — re-download or re-generate expired reports
```

---

## Scheduled Reports

```php
// routes/console.php — scheduled report dispatch example
Schedule::call(function () {
    Team::where('reports_enabled', true)->each(function (Team $team) {
        GenerateReportJob::dispatch($team, 'production_daily', now()->subDay());
    });
})->dailyAt('06:00');
```

---

## ESM Intelligence Handoff

Reports are the primary output channel for:
- **compliance-reporting-patterns**: DMRE and MHSA formatted reports
- **esg-reporting-patterns**: ESG emission summaries
- **financial-intelligence-agent**: cost-per-ton, budget variance reports
- **audit-logging-patterns**: all report generation actions are audit-logged

---

## Commands Reference

```bash
# Run report tests
php artisan test --compact tests/Feature/ReportTest.php

# Check stalled reports (pending > 30 min)
php artisan tinker --execute '
App\Models\Report::where("status","pending")
    ->where("created_at","<",now()->subMinutes(30))
    ->get(["id","type","team_id","created_at"]);
'

# Retry failed report jobs
php artisan queue:retry all
```
