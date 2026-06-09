---
name: gdpr-data-privacy-patterns
description: >
  Mines platform GDPR and POPIA data privacy patterns. Use when: handling data subject requests
  (export or deletion), working with GdprRequest model, implementing data retention policies,
  testing DeleteUserDataJob or ExportUserDataJob, verifying consent tracking, auditing PII
  storage, or ensuring compliance with POPIA/GDPR personal data obligations.
argument-hint: 'Describe the data privacy task you need help with'
esm-layer: governance
esm-feeds-to:
  - compliance-reporting-patterns
  - audit-logging-patterns
  - compliance-agent
esm-consumes-from:
  - audit-logging-patterns
  - file-storage-patterns
---

# GDPR / POPIA Data Privacy Patterns

## When to Use

- Processing a data subject access request (DSAR) — export or deletion
- Implementing or testing retention policy purge jobs
- Auditing what personal data (PII) is stored and where
- Understanding GdprRequest lifecycle
- Verifying consent is captured before processing personal data
- Writing tests for DeleteUserDataJob or ExportUserDataJob

---

## Applicable Regulations

```
POPIA  — Protection of Personal Information Act (South Africa, primary)
GDPR   — EU General Data Protection Regulation (secondary, for EU users)

Key obligations:
  - Lawful basis for processing personal data
  - Right of access (data export)
  - Right to erasure (data deletion)
  - Data retention limits — do not keep data longer than necessary
  - Breach notification — within 72 hours to regulator
```

---

## GdprRequest Lifecycle

```
User submits request (export or deletion)
       ↓
GdprRequest::created (status: pending)
       ↓
  type === 'export'   → ExportUserDataJob dispatched (queue: default)
  type === 'deletion' → DeleteUserDataJob dispatched (queue: default)
       ↓
Job completes → GdprRequest status: completed
  export → signed S3 URL emailed to user (24-hour expiry)
  deletion → all PII anonymised / deleted
       ↓
AuditLog entry created for compliance evidence
```

---

## PII Inventory (key tables)

```
users           — name, email, phone, profile data
audit_logs      — user actions (retain 7 years, then purge)
notification_delivery_logs — email addresses (retain 90 days)
feed_posts      — user-authored content (anonymise on deletion)
feed_mentions   — user references
sent_emails     — recipient addresses (retain 90 days)
machine_metrics — may contain operator IDs (retain per data policy)
```

---

## Pattern — Submitting a DSAR

```php
// API — authenticated user requests their own data export
POST /api/v1/gdpr/requests
{
    "type": "export"     // export|deletion
}
// Returns GdprRequest with status: pending
// Job dispatched immediately
```

---

## DeleteUserDataJob Pattern

```php
// app/Jobs/DeleteUserDataJob.php
// Anonymises rather than hard-deletes where referential integrity matters:

$user->update([
    'name'  => 'Deleted User',
    'email' => "deleted_{$user->id}@anonymised.invalid",
    'phone' => null,
]);
$user->feedPosts()->update(['body' => '[Content removed]', 'author_id' => null]);
$user->tokens()->delete();        // revoke all Sanctum tokens
$user->notifications()->delete(); // remove personal notifications
```

---

## Retention Purge Jobs

```
PurgeExpiredSoftDeletesJob  — runs weekly, hard-deletes records soft-deleted > 90 days
PurgeOldAuditLogsJob        — runs monthly, removes audit_logs older than 7 years
PurgeOldFeedPostsJob        — runs monthly, removes archived posts older than retention period
```

---

## Pattern — Privacy Test Setup

```php
#[Test]
public function user_can_request_data_export(): void
{
    Queue::fake();
    $user = User::factory()->create();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/gdpr/requests', ['type' => 'export'])
        ->assertCreated();

    $this->assertDatabaseHas('gdpr_requests', [
        'user_id' => $user->id,
        'type'    => 'export',
        'status'  => 'pending',
    ]);
    Queue::assertPushed(ExportUserDataJob::class);
}

#[Test]
public function deletion_request_anonymises_user_pii(): void
{
    $user = User::factory()->create(['email' => 'real@example.com']);
    $job  = new DeleteUserDataJob($user);
    $job->handle();

    $this->assertStringContainsString('anonymised.invalid', $user->fresh()->email);
    $this->assertNotEquals('real@example.com', $user->fresh()->email);
}
```

---

## Consent Verification

Before processing any marketing or non-essential communication:
```php
// Verify user has opted in before sending
if (! $user->hasConsentedTo('marketing_emails')) {
    return; // silently skip — never throw, never log PII in error
}
```

---

## ESM Intelligence Handoff

- **audit-logging-patterns**: every DSAR action is audit-logged
- **compliance-reporting-patterns**: POPIA compliance evidence
- **file-storage-patterns**: export files stored in S3 with 24-hour signed URLs

---

## Commands Reference

```bash
# Run privacy tests
php artisan test --compact tests/Feature/GdprTest.php

# Check pending DSAR requests
php artisan tinker --execute '
App\Models\GdprRequest::where("status","pending")->get(["id","user_id","type","created_at"]);
'

# Run retention purge manually (safe — logs what it would delete)
php artisan tinker --execute '
$job = new App\Jobs\PurgeExpiredSoftDeletesJob();
$job->handle();
echo "Done";
'

# Check audit log retention
php artisan tinker --execute '
$oldest = App\Models\AuditLog::orderBy("created_at")->first();
echo $oldest?->created_at ?? "No records";
'
```
