# Release Checklist

> Every production release must pass all gates below before deployment.
> No gate may be skipped without explicit written approval from the engineering lead.

---

## Pre-Deployment Gates

### 1. Code Quality
- [ ] All PHPStan errors resolved (zero new errors; baseline not grown)
- [ ] Laravel Pint passes with zero violations (`./vendor/bin/pint --test`)
- [ ] No commented-out code blocks committed
- [ ] No hardcoded credentials, secrets, or API keys in diff

### 2. Tests
- [ ] All PHPUnit tests pass (`php artisan test --no-coverage`)
- [ ] No tests skipped without documented justification
- [ ] New features have corresponding feature tests
- [ ] Critical paths have regression tests

### 3. Security
- [ ] `composer audit` — zero vulnerabilities
- [ ] `npm audit` — zero high/critical vulnerabilities
- [ ] No new routes added without authentication middleware
- [ ] No `mass assignment` vulnerabilities introduced (check `$fillable` / `$guarded`)
- [ ] No sensitive data in log output (verified with `ErrorLoggerService`)
- [ ] All user inputs validated with Form Requests or explicit `$request->validate()`

### 4. Database
- [ ] All migrations are reversible (`down()` method is correct)
- [ ] No migration alters an existing column without a fallback
- [ ] New indexes added for any column used in `WHERE` / `ORDER BY`
- [ ] No migration drops a column without a deprecation period (soft-delete approach)
- [ ] Large table migrations have been tested against a production-sized dataset

### 5. Performance
- [ ] No new N+1 queries introduced (verified with Debugbar or query count in tests)
- [ ] New queries on hot paths use eager loading or caching
- [ ] No unbounded collection queries (all lists paginated)
- [ ] Queue jobs are idempotent (safe to re-run on retry)

### 6. API
- [ ] No breaking changes to existing endpoints (or new API version created)
- [ ] New endpoints have validation, authentication, and rate limiting
- [ ] OpenAPI annotations updated for new/changed endpoints

### 7. Documentation
- [ ] `docs/continuous-improvement/CHANGELOG.md` updated with this release
- [ ] `docs/continuous-improvement/KNOWN_ISSUES.md` updated (resolved issues marked)
- [ ] `docs/continuous-improvement/PLATFORM_SCORECARD.md` reviewed
- [ ] `.env.example` updated if new env vars added
- [ ] New env vars documented in relevant `config/` file

### 8. Monitoring & Observability
- [ ] New features log meaningful events at appropriate levels
- [ ] New background jobs log start/complete/failure
- [ ] New integrations have audit log entries
- [ ] Sentry DSN configured (if deploying to new environment)

### 9. Accessibility & UX
- [ ] New UI components reviewed for keyboard navigation
- [ ] Loading states present for all async operations
- [ ] Empty states present for all list/data views
- [ ] Error states present for all form submissions

### 10. Mobile Responsiveness
- [ ] New pages/components tested at 375px (iPhone SE viewport)
- [ ] No horizontal scroll introduced on mobile

---

## Deployment Procedure

```bash
# 1. Backup database
php artisan db:backup  # or use the backup-db.sh script

# 2. Put application in maintenance mode
php artisan down --render=errors/platform --retry=60

# 3. Pull latest code
git pull origin main

# 4. Install dependencies
composer install --no-dev --optimize-autoloader
npm ci && npm run build

# 5. Run migrations
php artisan migrate --force

# 6. Clear and warm caches
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# 7. Restart queue workers
php artisan queue:restart

# 8. Bring application back up
php artisan up

# 9. Verify health check
curl https://mines.infodot.co.za/health
```

---

## Rollback Procedure

```bash
# 1. Revert to previous release tag
git checkout <previous-tag>

# 2. Restore database backup
php artisan db:restore --file=<backup-file>

# 3. Re-run deployment procedure from step 4
```

**Always test rollback in staging before relying on it in production.**

---

## Post-Deployment Verification

- [ ] Application health check returns `200 OK`
- [ ] Login and dashboard load without errors
- [ ] Bell sync job runs successfully
- [ ] No new errors in `platform_error_logs`
- [ ] Sentry shows no new critical errors
- [ ] Horizon queue workers are running
