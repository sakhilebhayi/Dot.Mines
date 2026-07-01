# Enterprise Readiness Audit Plan
**Platform:** Mines Fleet Intelligence Platform  
**Date:** 2026-07-01  
**Branch:** `feat/static-analysis`  
**Total routes audited:** 168 | **Models:** 95 | **Migrations:** 87

---

## Executive Summary

This document is the authoritative checklist for making the platform production-grade. Every
section maps to concrete files, commands, and tests. Work through the sections in order — each
section gates the next.

**Current state:**
- API exception handler ✅ — structured JSON for 401/403/404/422/429/500
- Rate limiting ✅ — 7 named limiters (api, login, webhooks, reports, downloads, uploads, feed-post)
- Sentry DSN ⚠️ — configured but empty (`SENTRY_DSN=`)
- Platform error logbook ❌ — no `platform_error_logs` table yet
- Controller try-catch ❌ — 0 try-catch blocks in API controllers (relies entirely on global handler)
- Frontend error boundaries ❌ — no Livewire error boundary or Alpine.js error guard
- HTTPS enforcement ❌ — not confirmed in nginx config
- SQL injection / mass assignment ⚠️ — partially covered via `$fillable` but needs audit

---

## Part 1 — Error Logbook (implement first)

Everything else in this plan writes to the `platform_error_logs` table. Build this first.

### 1.1 — Migration

```bash
php artisan make:migration create_platform_error_logs_table
```

```php
Schema::create('platform_error_logs', function (Blueprint $table): void {
    $table->id();
    $table->string('error_id', 36)->unique();          // UUID — returned to user as ref
    $table->string('level', 20)->default('error');     // error | warning | critical
    $table->string('category', 60)->default('app');    // app | api | integration | queue | frontend
    $table->string('http_method', 10)->nullable();     // GET | POST | etc.
    $table->text('url')->nullable();                   // full request URL
    $table->string('route_name', 120)->nullable();
    $table->string('http_status', 10)->nullable();     // 404 | 500 | etc.
    $table->string('exception_class', 200)->nullable();
    $table->text('message');                           // exception/error message
    $table->longText('stack_trace')->nullable();       // never exposed to users
    $table->jsonb('context')->nullable();              // request params (PII-stripped)
    $table->string('user_id')->nullable();
    $table->unsignedBigInteger('team_id')->nullable();
    $table->string('ip_address', 45)->nullable();
    $table->string('user_agent')->nullable();
    $table->string('environment', 20)->default('production');
    $table->string('app_version', 40)->nullable();     // git SHA or release tag
    $table->boolean('resolved')->default(false);
    $table->timestamp('resolved_at')->nullable();
    $table->timestamps();

    $table->index(['level', 'created_at']);
    $table->index(['team_id', 'created_at']);
    $table->index(['exception_class', 'created_at']);
    $table->index(['resolved', 'created_at']);
});
```

### 1.2 — PlatformErrorLog Model

```bash
php artisan make:model PlatformErrorLog
```

Key methods:
- `PlatformErrorLog::record(Throwable $e, Request $request): self`
- `PlatformErrorLog::recordFrontend(array $payload, Request $request): self`
- PII stripping: remove `password`, `token`, `secret`, `credentials` from context before storing

### 1.3 — ErrorLoggerService

```bash
php artisan make:class app/Services/ErrorLoggerService.php
```

Responsibilities:
- Generate UUID `error_id` for each event
- Strip PII from request params
- Truncate stack traces > 10KB
- Write to `platform_error_logs`
- Optionally forward to Sentry if `SENTRY_DSN` is set

---

## Part 2 — Global Exception Handler

**File:** `bootstrap/app.php`

### 2.1 — Catch-all for web (Livewire + web routes)

All unhandled web exceptions must render a clean error page — no stack trace exposed.
In non-production, show the exception detail. In production, show the `error_id` only:

```php
$exceptions->render(function (Throwable $e, Request $request) {
    // Log everything that reaches this point
    $log = ErrorLoggerService::record($e, $request);

    // API: return appropriate HTTP status (not always 404)
    if ($request->expectsJson() || $request->is('api/*')) {
        $status = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500;
        return response()->json([
            'message' => app()->isProduction()
                ? 'An unexpected error occurred.'
                : $e->getMessage(),
            'error_ref' => $log->error_id,  // safe to expose — just a UUID
        ], $status);
    }

    // Web: render clean Blade error view
    return response()->view('errors.platform', [
        'error_ref' => $log->error_id,
        'status'    => method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500,
    ], method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500);
});
```

> **Note on "all errors → 404":** Mapping every error to 404 is a recognised technique to
> prevent information leakage (attackers cannot distinguish "not found" from "server error").
> The implementation above uses the real HTTP status for API responses (so callers can retry
> correctly on 5xx but stop on 4xx) and shows a generic page for web. If you want a strict
> single-status policy, change `$status` to `404` for web views only.

### 2.2 — Error view

Create `resources/views/errors/platform.blade.php` — a branded "Something went wrong"
page with the `error_ref` UUID visible so users can report issues.

---

## Part 3 — API Endpoint Audit

### 3.1 — Authentication coverage

All 168 routes must be verified. Run this check after every deployment:

```bash
php artisan route:list --except-vendor | grep -v "auth:sanctum\|auth\|login\|register\|password\|verify"
```

Expected output: only public routes (home, login, register, password reset, verify, webhooks).

**Critical gaps found during audit:**

| Route | Issue | Fix |
|---|---|---|
| `GET /api/v1/live-locations` | No named route — verify middleware | Add `->name('api.live-locations')` |
| Webhook routes | `throttle:webhooks` but no HMAC verify on all | Verify `WebhookController` validates signature on ALL POST webhook routes |

### 3.2 — Rate limiter values (in `app/Providers/AppServiceProvider.php`)

Audit and document the current limits. Recommended production values:

| Limiter | Current | Recommended | Notes |
|---|---|---|---|
| `api` | verify | 120/min per user | Global API gate |
| `login` | verify | 5/min per IP | Brute force protection |
| `webhooks` | verify | 30/min per IP | Prevents webhook abuse |
| `reports` | verify | 10/min per user | PDF generation is expensive |
| `downloads` | verify | 20/min per user | S3 bandwidth protection |
| `uploads` | verify | 10/min per user | Payload size gate too |
| `feed-post` | verify | 15/min per user | Anti-spam |

Action: run `php artisan tinker --execute 'dump(app(\Illuminate\Cache\RateLimiter::class))'`
to inspect current values and update this table.

### 3.3 — Input validation audit

All POST/PUT/PATCH routes must use a dedicated `FormRequest` class (not `$request->validate()`
inline). Run:

```bash
grep -rn "request->validate(" app/Http/Controllers/ --include="*.php"
```

Any hit must be moved to a `FormRequest`. Inline validation bypasses the audit trail and makes
testing harder.

### 3.4 — Mass-assignment audit

```bash
grep -rn "fillable\|guarded" app/Models/ --include="*.php" | grep -v "\$fillable\|\$guarded"
```

Every model must have either `$fillable` (allowlist) or `$guarded = ['id']` at minimum.
Models with `$guarded = []` are unsafe — flag immediately.

### 3.5 — SQL injection audit

The platform uses Eloquent. Raw queries must be verified:

```bash
grep -rn "DB::select\|DB::statement\|whereRaw\|selectRaw\|orderByRaw\|groupByRaw" app/ --include="*.php"
```

For every hit: confirm the query uses parameterised bindings (`?` or named `:param`), not
string concatenation.

---

## Part 4 — Security Checklist

### 4.1 — OWASP Top 10 coverage matrix

| # | Risk | Status | Action |
|---|---|---|---|
| A01 | Broken Access Control | ⚠️ Partial | Verify all policies cover every model; run `PolicyCoverageTest` |
| A02 | Cryptographic Failures | ⚠️ Partial | `credentials` encrypted ✅; `api_key`/`api_secret` columns still plaintext — encrypt them too |
| A03 | Injection | ✅ Eloquent ORM | Audit raw queries (see §3.5) |
| A04 | Insecure Design | ✅ | Multi-tenant team isolation enforced; Bell team lock implemented |
| A05 | Security Misconfiguration | ❌ | `SENTRY_DSN` empty; `APP_DEBUG=true` in dev (check prod); HTTPS not enforced in app layer |
| A06 | Vulnerable Components | ✅ | npm + Composer audits pass (as of 2026-07-01) |
| A07 | Identification & Authentication Failures | ✅ Fortify | 2FA available; rate limiting on login |
| A08 | Software & Data Integrity Failures | ⚠️ | Webhook HMAC validation — confirm coverage on all webhook routes |
| A09 | Security Logging & Monitoring Failures | ❌ | Sentry DSN empty; `platform_error_logs` not yet built |
| A10 | Server-Side Request Forgery | ⚠️ | HTTP client calls in services — verify no user-controlled URLs reach `Http::get()` |

### 4.2 — Sensitive data in logs

```bash
grep -rn "Log::info\|Log::debug" app/ --include="*.php" | grep -i "password\|token\|secret\|credential\|key"
```

Any hit that logs a credential must be removed immediately.

### 4.3 — `.env` production checklist

```
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:...        # 32-byte random key — never reuse dev key in prod
LOG_LEVEL=warning
SENTRY_DSN=<set>          # Enable error tracking
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=strict
FORCE_HTTPS=true
BELL_SSO_CLIENT_SECRET=<set>
```

### 4.4 — HTTP security headers

Add to `nginx.conf` (or `app/Http/Middleware/`):

```
Strict-Transport-Security: max-age=31536000; includeSubDomains
X-Content-Type-Options: nosniff
X-Frame-Options: SAMEORIGIN
X-XSS-Protection: 1; mode=block
Referrer-Policy: strict-origin-when-cross-origin
Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; ...
Permissions-Policy: geolocation=(), microphone=(), camera=()
```

Create `app/Http/Middleware/SecurityHeaders.php` and register in `bootstrap/app.php`.

### 4.5 — CORS configuration

File: `config/cors.php`. Verify:
- `allowed_origins` does not contain `['*']` in production
- `supports_credentials` is `true` for Sanctum SPA flows
- `allowed_origins_patterns` restricted to known frontend domains

---

## Part 5 — Frontend Audit

### 5.1 — Livewire error handling

All Livewire components must handle failures gracefully. Currently 0 components have error
state handling for network failures.

**Add to every component that calls external data:**

```php
// In Livewire component
public string $errorMessage = '';

public function loadData(): void
{
    try {
        // ... data loading
    } catch (\Throwable $e) {
        $this->errorMessage = app()->isProduction()
            ? 'Failed to load data. Please refresh.'
            : $e->getMessage();
        // Log to platform_error_logs via ErrorLoggerService
    }
}
```

```blade
@if($errorMessage)
    <div class="bg-red-900/20 border border-red-700 rounded p-4 text-red-300 text-sm">
        ⚠ {{ $errorMessage }}
    </div>
@endif
```

### 5.2 — Alpine.js error guard

Wrap all Alpine.js initialisations that make HTTP calls:

```html
<div x-data="{ error: null }"
     x-init="fetch('/api/...').then(r => r.json()).then(d => { ... }).catch(e => { error = e.message })">
    <template x-if="error">
        <p class="text-red-400 text-sm" x-text="error"></p>
    </template>
</div>
```

### 5.3 — Frontend error reporting endpoint

Create `POST /api/v1/frontend/errors` — allows the JavaScript layer to report client-side
exceptions to `platform_error_logs` with `category = 'frontend'`.

```bash
php artisan make:controller Api/FrontendErrorController
```

Rate limit: `throttle:5,1` (5 per minute per user) to prevent log flooding.

### 5.4 — Chart.js / Alpine.js null-data guards

All chart initialisations in Blade views (machine-detail, production-dashboard, reports)
must guard against empty datasets:

```javascript
// Before
datasets: [{ data: {{ json_encode($bellFuelHistory->pluck('fuel_remaining_percent')) }} }]

// After — guard against empty
@if($bellFuelHistory->isNotEmpty())
    // chart initialisation
@else
    <div class="text-slate-400 text-sm text-center py-8">No data available for this period.</div>
@endif
```

### 5.5 — Blade XSS audit

Run the existing XSS audit tool:

```bash
cat deploy/blade-xss-audit.txt
```

Then grep for unescaped output:

```bash
grep -rn "{!!\|raw(" resources/views/ --include="*.blade.php" | grep -v "<!--" | grep -v "slot\|component"
```

Every `{!!` must be justified — only use for known-safe HTML (Markdown rendered content,
trusted admin input). User-generated content must always use `{{ }}`.

---

## Part 6 — Queue & Background Job Audit

### 6.1 — Failed job monitoring

```bash
php artisan queue:failed | head -20
```

In production, failed jobs must alert via Slack/email. Add to `AppServiceProvider`:

```php
Queue::failing(function (JobFailed $event) {
    PlatformErrorLog::recordQueueFailure($event);
    // Optionally: dispatch alert notification
});
```

### 6.2 — Job timeout coverage

Every job must declare `$timeout`. Jobs without a timeout can block workers indefinitely.

```bash
grep -rn "class.*implements ShouldQueue" app/Jobs/ --include="*.php" -l | xargs grep -L "timeout"
```

Any hit: add `public int $timeout = 60;` (or appropriate value).

### 6.3 — Job idempotency

Jobs that are retried must be idempotent (safe to run twice). Key jobs to audit:

- `SyncIntegrationJob` — uses upsert ✅
- `SyncBellFleetDataJob` — uses upsert ✅
- `GenerateReportJob` — check for duplicate report creation ⚠️

### 6.4 — Horizon health check

```bash
php artisan horizon:status
```

Add a scheduled health check command that alerts if Horizon is paused or has > 50 failed jobs:

```bash
php artisan make:command CheckHorizonHealthCommand
```

Schedule every 5 minutes alongside existing jobs.

---

## Part 7 — Database Integrity Audit

### 7.1 — Missing indexes

```bash
php artisan tinker --execute '
$tables = \Schema::getTables();
foreach ($tables as $t) {
    $cols = \Schema::getColumns($t["name"] ?? $t);
    $indexes = \Schema::getIndexes($t["name"] ?? $t);
    // Report tables with foreign key columns that have no index
}
'
```

Key columns that must be indexed (check migrations):

| Table | Column | Index needed |
|---|---|---|
| `machines` | `external_id` | Yes — used in Bell sync upsert |
| `machines` | `team_id, external_id` | Composite — team isolation lookup |
| `platform_error_logs` | `level, created_at` | Yes — dashboards filter by level |
| `integration_sync_logs` | `integration_id, started_at` | Yes — already added ✅ |

### 7.2 — Orphaned records

```bash
php artisan tinker --execute '
// Bell equipment not linked to any machine
$orphaned = \App\Models\BellEquipment::whereNull("machine_id")->count();
echo "Orphaned Bell equipment: $orphaned\n";

// Integrations without a team
$orphanedIntegrations = \App\Models\Integration::whereNotIn("team_id", \App\Models\Team::pluck("id"))->count();
echo "Orphaned integrations: $orphanedIntegrations\n";
'
```

### 7.3 — Soft-delete coverage

Tables that store user-facing data must use soft-deletes to support GDPR right-to-erasure
workflows and accidental deletion recovery.

```bash
grep -rL "SoftDeletes" app/Models/ --include="*.php" | xargs -I{} basename {}
```

Review every model that is NOT soft-deletable and confirm it is either:
- Append-only (history tables — correct)
- A lookup/config table (correct)
- A user-facing record (flag for soft-delete)

---

## Part 8 — Enterprise Readiness Checks

### 8.1 — Multi-tenant data isolation test

Every API endpoint test must include a cross-team isolation assertion. Pattern:

```php
public function test_team_a_cannot_access_team_b_resource(): void
{
    $teamA = User::factory()->withPersonalTeam()->create();
    $teamB = User::factory()->withPersonalTeam()->create();
    $resource = Resource::factory()->create(['team_id' => $teamB->currentTeam->id]);

    $this->actingAs($teamA, 'sanctum')
        ->getJson("/api/v1/resources/{$resource->id}")
        ->assertStatus(404);  // not 403 — do not confirm existence
}
```

```bash
grep -rn "team_b\|otherTeam\|other_team\|cross.*team\|team.*isolation" tests/ --include="*.php" | wc -l
```

Target: at least one isolation test per resource type (machines, alerts, geofences, etc.).

### 8.2 — Subscription gate coverage

All premium features must be gated behind subscription checks. Verify:

```bash
grep -rn "SubscriptionPlan\|checkFeature\|hasFeature\|plan\|subscription" app/Http/Controllers/ app/Livewire/ --include="*.php" | grep -v "test\|Test" | head -20
```

Features that require subscription gates:
- AI analytics
- Integration management (number of integrations per plan)
- Report generation (PDF)
- Advanced geofencing
- Bell telemetry access

### 8.3 — GDPR / POPIA compliance checklist

| Requirement | Status | File |
|---|---|---|
| Data export (Right to access) | ✅ | `ExportUserDataJob` |
| Data deletion (Right to erasure) | ✅ | `DeleteUserDataJob` |
| Consent logging | ⚠️ | Verify `GdprRequest` covers all PII fields |
| Credential encryption at rest | ✅ | `Integration.credentials` encrypted |
| PII in logs | ❌ | Audit all `Log::` calls (see §4.2) |
| Data retention policy enforced | ⚠️ | `PurgeExpiredSoftDeletesJob` — verify schedule |
| Cross-border transfer policy | ❌ | Document if S3 bucket region = data residency |

### 8.4 — Performance baseline

```bash
# Run Horizon stats
php artisan horizon:list

# Check slowest queries (enable query logging in AppServiceProvider for one cycle)
DB::listen(function ($query) {
    if ($query->time > 500) {  // > 500ms
        Log::warning('Slow query', ['sql' => $query->sql, 'time' => $query->time]);
    }
});
```

Target SLAs for enterprise:
- API p95 response time < 200ms
- Livewire component mount < 300ms
- Report generation < 30s (async queue)
- Bell sync cycle < 60s per integration

### 8.5 — Cache coverage audit

```bash
grep -rn "Cache::remember\|QueryCacheService" app/ --include="*.php" | wc -l
```

Key queries that must be cached (TTL suggestions):

| Query | TTL | Key pattern |
|---|---|---|
| `BellTeamInsightsService::getTeamOverview()` | 5 min | `bell.overview.{team_id}.{month}` |
| Machine list per team | 60s | `machines.list.{team_id}` |
| Active alerts count | 30s | `alerts.active.{team_id}` |
| Daily KPI aggregates | 10 min | `kpi.daily.{equipment_key}.{date}` |

---

## Part 9 — Deployment & CI/CD Checklist

### 9.1 — Pre-deployment gates (automated, run in CI)

```bash
# 1. Static analysis (zero errors at level max)
vendor/bin/phpstan analyse --level=max

# 2. Code style
vendor/bin/pint --test

# 3. Full test suite
php artisan test --compact

# 4. Composer audit
composer audit

# 5. npm audit
npm audit

# 6. Secrets scan
# (gitleaks runs in pre-commit hook — also run in CI)
gitleaks detect --source . --report-path gitleaks-report.json
```

### 9.2 — Post-deployment commands (production)

```bash
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
php artisan optimize
php artisan storage:link
php artisan integration:encrypt-credentials    # Phase 1 migration
php artisan horizon:terminate                  # graceful worker restart
```

### 9.3 — Health check endpoint

Create `GET /health` (public, no auth):

```php
return response()->json([
    'status'      => 'ok',
    'db'          => DB::connection()->getPdo() ? 'ok' : 'error',
    'cache'       => Cache::store()->set('_health', 1, 5) ? 'ok' : 'error',
    'queue'       => ... horizon status check,
    'version'     => config('app.version'),
    'timestamp'   => now()->toIso8601String(),
]);
```

Return `200` if all subsystems healthy, `503` if any critical subsystem fails.
Used by load balancers, monitoring, and Kubernetes liveness probes.

---

## Part 10 — Audit Execution Plan

Work through the items below in order. Each row maps to a concrete git commit.

| # | Area | File(s) | Test(s) | Priority |
|---|---|---|---|---|
| 1 | `platform_error_logs` migration + model + service | `database/migrations/*`, `app/Models/PlatformErrorLog.php`, `app/Services/ErrorLoggerService.php` | `PlatformErrorLogTest` | 🔴 Critical |
| 2 | Global exception handler writes to logbook | `bootstrap/app.php` | `ExceptionHandlerTest` | 🔴 Critical |
| 3 | `resources/views/errors/platform.blade.php` | Blade view | Manual | 🔴 Critical |
| 4 | HTTP security headers middleware | `app/Http/Middleware/SecurityHeaders.php` | `SecurityHeadersTest` | 🔴 Critical |
| 5 | Sentry DSN configured in production `.env` | `.env.production` | N/A | 🔴 Critical |
| 6 | `GET /health` endpoint | `app/Http/Controllers/HealthController.php` | `HealthCheckTest` | 🔴 Critical |
| 7 | All jobs have `$timeout` | All `app/Jobs/*.php` | PHPStan | 🟠 High |
| 8 | `Queue::failing()` hook logs to `platform_error_logs` | `app/Providers/AppServiceProvider.php` | `QueueFailureTest` | 🟠 High |
| 9 | Rate limiter values documented and tuned | `app/Providers/AppServiceProvider.php` | Existing rate limit tests | 🟠 High |
| 10 | XSS audit — all `{!!` occurrences justified | All blade views | `BladeXssTest` | 🟠 High |
| 11 | SQL injection audit — all raw queries parameterised | Controllers + services | `SqlInjectionAuditTest` | 🟠 High |
| 12 | Livewire component error states | All Livewire components | Livewire tests | 🟡 Medium |
| 13 | `POST /api/v1/frontend/errors` endpoint | `FrontendErrorController` | `FrontendErrorTest` | 🟡 Medium |
| 14 | Team isolation test — one per resource type | `tests/Feature/*IsolationTest.php` | PHPUnit | 🟡 Medium |
| 15 | Cache coverage for Bell insights + machine list | Services | Cache tests | 🟡 Medium |
| 16 | `CheckHorizonHealthCommand` with alerting | `app/Console/Commands/` | Manual | 🟡 Medium |
| 17 | GDPR PII-in-logs audit | All `Log::` call sites | N/A | 🟡 Medium |
| 18 | CORS `allowed_origins` restricted to prod domains | `config/cors.php` | `CorsTest` | 🟡 Medium |
| 19 | `platform_error_logs` dashboard (admin view) | Livewire component | Livewire test | 🟢 Low |
| 20 | Soft-delete coverage for all user-facing models | Migrations + models | DB tests | 🟢 Low |

---

## Part 11 — Platform Error Log Dashboard (future)

Once `platform_error_logs` is populated, build a read-only Livewire admin component at
`/admin/error-logs` (admin-only route):

**Columns shown:**
- Error ID (truncated UUID)
- Level badge
- Category
- HTTP status
- Route
- Exception class
- Message (truncated)
- User / Team
- Created at
- Resolved status + button

**Filters:**
- Level (error / warning / critical)
- Category (app / api / integration / queue / frontend)
- Date range
- Team
- Resolved / Unresolved

**Actions:**
- Mark resolved
- View full stack trace (admin only)
- Export CSV

---

## Appendix — Commands Quick Reference

```bash
# Run full audit pipeline
php artisan test --compact
vendor/bin/phpstan analyse --level=max --no-progress
vendor/bin/pint --test
composer audit
npm audit

# Encrypt existing integration credentials (run once on first deploy)
php artisan integration:encrypt-credentials

# Bell historical backfill (run once on first deploy)
php artisan bell:backfill-history --from=2026-05-01

# Check for orphaned records
php artisan tinker --execute '...'   # see §7.2

# Health check
curl -s https://your-domain.com/health | jq
```
