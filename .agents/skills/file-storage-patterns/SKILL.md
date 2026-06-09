---
name: file-storage-patterns
description: >
  Mines platform file storage and S3 infrastructure patterns. Use when: uploading files via
  FileUploadService or FeedAttachmentService, working with S3-backed attachments, generating
  signed URLs, running MigrateFeedAttachmentsToS3Job, using VerifyS3Storage command, auditing
  file upload security, configuring file retention policies, or debugging download failures.
argument-hint: 'Describe the file storage task you need help with'
esm-layer: operational
esm-feeds-to:
  - gdpr-data-privacy-patterns
  - compliance-reporting-patterns
  - audit-logging-patterns
  - security-agent
esm-consumes-from:
  - audit-logging-patterns
---

# File Storage Patterns

## When to Use

- Uploading files (attachments, mine plans, reports, exports) via FileUploadService
- Generating signed S3 URLs for secure time-limited downloads
- Debugging why a file upload failed or a download link expired
- Working with FeedAttachmentService for community feed files
- Running or testing MigrateFeedAttachmentsToS3Job
- Auditing file upload security (MIME type, size limits)
- Understanding the S3 bucket structure for this application
- Implementing data retention (auto-expiry) for uploaded files

---

## FileUploadService API

```php
use App\Services\FileUploadService;

$service = app(FileUploadService::class);

// Upload a file to S3
$path = $service->upload(
    file: $request->file('document'),
    directory: 'reports',          // S3 prefix
    disk: 's3',
    visibility: 'private',         // always private for sensitive files
);
// Returns: 'reports/2026/06/{uuid}.pdf' — relative S3 path

// Generate a signed URL (60 min default)
$url = $service->signedUrl($path, ttl: 3600);

// Delete a file
$service->delete($path);
```

---

## S3 Directory Structure

```
s3://bucket/
  reports/{team_id}/{year}/{uuid}.pdf          — generated reports
  feed-attachments/{team_id}/{uuid}.{ext}      — community feed files
  mine-plans/{team_id}/{area_id}/{uuid}.pdf    — mine plan uploads
  exports/{team_id}/{uuid}.csv                 — data exports
  gdpr-exports/{user_id}/{uuid}.zip            — GDPR data exports (24h TTL)
  avatars/{user_id}/{uuid}.jpg                 — user avatars (public)
  machine-images/{machine_id}/{uuid}.jpg       — machine photos (private)
```

---

## Upload Security Requirements

**CRITICAL — always validate before storing:**

```php
// In Form Request or controller:
$request->validate([
    'file' => [
        'required',
        'file',
        'max:51200',                // 50MB maximum
        'mimes:pdf,csv,xlsx,jpg,png,dwg',  // allowlist only — never 'mimetypes:*'
    ],
]);

// FileUploadService additionally:
// 1. Re-validates MIME type from file content (not extension) using finfo
// 2. Strips EXIF data from images
// 3. Generates a new UUID filename — never uses the original filename
// 4. Stores with private visibility unless explicitly public (avatars only)
```

---

## FeedAttachmentService

```php
use App\Services\FeedAttachmentService;

$service = app(FeedAttachmentService::class);

// Attach a file to a feed post
$attachment = $service->attachToPost(
    post: $feedPost,
    file: $request->file('attachment'),
);
// Returns: FeedAttachment with s3_path populated

// Get the signed download URL for an attachment
$url = $service->getDownloadUrl($attachment); // 60-min signed URL
```

---

## Signed URL Pattern

```php
// Always use signed URLs for private files — never expose the raw S3 path
$signedUrl = Storage::disk('s3')->temporaryUrl(
    $path,
    now()->addMinutes(60),
    ['ResponseContentDisposition' => 'attachment; filename="' . $filename . '"'],
);

// For GDPR exports — shorter TTL
$signedUrl = Storage::disk('s3')->temporaryUrl($path, now()->addMinutes(15));
```

---

## VerifyS3Storage Command

```bash
php artisan storage:verify-s3

# Checks:
# 1. S3 credentials are valid (test PutObject + GetObject + DeleteObject)
# 2. KMS encryption is active on the bucket (checks bucket policy)
# 3. Public access is blocked (confirms bucket public access block settings)
# 4. Versioning is enabled
# 5. Lifecycle rules exist for GDPR export auto-expiry

# Run in CI or after any storage config change
```

---

## Pattern — File Storage Test

```php
#[Test]
public function report_can_be_downloaded_via_signed_url(): void
{
    Storage::fake('s3');

    $user   = $this->adminUser();
    $report = Report::factory()->completed()->create([
        'team_id' => $user->current_team_id,
        's3_path' => 'reports/test.pdf',
    ]);
    Storage::disk('s3')->put('reports/test.pdf', 'fake-content');

    $this->actingAs($user, 'sanctum')
        ->getJson("/api/v1/reports/{$report->id}/download")
        ->assertOk()
        ->assertJsonStructure(['url']);
}

#[Test]
public function upload_rejects_executable_files(): void
{
    $user = $this->adminUser();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/mine-areas/1/mine-plan', [
            'file' => UploadedFile::fake()->create('malware.exe', 100, 'application/x-msdownload'),
        ])
        ->assertUnprocessable();
}
```

---

## ESM Intelligence Handoff

- **gdpr-data-privacy-patterns**: GDPR export files have 24-hour TTL; deletion removes S3 files too
- **compliance-reporting-patterns**: compliance reports stored in S3, accessible via signed URLs
- **audit-logging-patterns**: all file downloads are audit-logged (who downloaded what, when)
- **security-agent**: upload MIME validation blocks malicious file uploads

---

## Commands Reference

```bash
# Run storage tests
php artisan test --compact tests/Feature/FileStorageTest.php

# Verify S3 is accessible
php artisan storage:verify-s3

# Check orphaned S3 files (files in DB with no matching S3 object)
php artisan tinker --execute '
App\Models\FeedAttachment::whereNotNull("s3_path")->get()->each(function($a) {
    if (! Storage::disk("s3")->exists($a->s3_path)) {
        echo "ORPHAN DB record: {$a->id} — path: {$a->s3_path}\n";
    }
});
'

# Migrate feed attachments to S3 (if any remain on local disk)
php artisan queue:work --queue=default --once
# After: MigrateFeedAttachmentsToS3Job dispatched via tinker if needed
```
