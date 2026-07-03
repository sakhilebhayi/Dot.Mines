# Security Improvements

> Track all security findings, hardening progress, and penetration testing results.

---

## Current Security Score: 82/100

---

## Open Findings

### SEC-001 — No Multi-Factor Authentication (MFA)
- **Severity**: High
- **Category**: Authentication
- **Finding**: No TOTP or hardware key MFA is implemented. Login relies solely on password.
- **OWASP**: A07:2021 Identification and Authentication Failures
- **Recommendation**: Implement TOTP via `laravel/fortify` (already in stack, just disabled). Add Authenticator app support.
- **Effort**: 3 days
- **Status**: 🔴 Open

### SEC-002 — `SESSION_SECURE_COOKIE=false` in `.env`
- **Severity**: High
- **Category**: Session Management
- **Finding**: Session cookies are not marked `Secure`, allowing transmission over HTTP.
- **OWASP**: A02:2021 Cryptographic Failures
- **Recommendation**: Set `SESSION_SECURE_COOKIE=true` in production. Force HTTPS via middleware.
- **Effort**: 0.5 days
- **Status**: 🔴 Open (production configuration)

### SEC-003 — API Rate Limiting Inconsistent
- **Severity**: High
- **Category**: API Security
- **Finding**: Auth routes are rate-limited but not all API endpoints. High-volume endpoints (telemetry, fleet list) are vulnerable to scraping and DoS.
- **OWASP**: A04:2021 Insecure Design
- **Recommendation**: Apply global `throttle:60,1` middleware to `api.php` route group. Tighten auth routes to 5/min.
- **Effort**: 1 day
- **Status**: 🟡 In Progress

### SEC-004 — SSRF Not Fully Audited
- **Severity**: Medium
- **Category**: Server-Side Request Forgery
- **Finding**: Outgoing HTTP calls in Bell integration services pass URLs from configuration. If config is user-controllable (admin UI), SSRF is possible.
- **OWASP**: A10:2021 SSRF
- **Recommendation**: Validate all outgoing URLs against an allowlist. Use `Http::withOptions(['curl' => [CURLOPT_PROTOCOLS => CURLPROTO_HTTPS]])` for external calls.
- **Effort**: 2 days
- **Status**: 🔴 Open

### SEC-005 — No Automated Dependency Scanning in CI
- **Severity**: Medium
- **Category**: Supply Chain
- **Finding**: Composer and npm vulnerabilities are patched manually. New CVEs could go unnoticed between manual checks.
- **OWASP**: A06:2021 Vulnerable and Outdated Components
- **Recommendation**: Add `composer audit` + `npm audit` to GitHub Actions CI. Enable Dependabot alerts on the repository.
- **Effort**: 1 day
- **Status**: 🟡 Planned

### SEC-006 — Secrets Not in a Dedicated Vault
- **Severity**: Medium
- **Category**: Secrets Management
- **Finding**: All secrets in `.env` file. If the file is leaked, all credentials are compromised.
- **OWASP**: A02:2021 Cryptographic Failures
- **Recommendation**: Use AWS Secrets Manager or HashiCorp Vault for production secrets. Use `aws-secrets-manager` Laravel package.
- **Effort**: 3 days
- **Status**: 🔵 Backlog

### SEC-007 — GitHub Actions Secrets Not Audited
- **Severity**: Medium
- **Category**: CI/CD Security
- **Finding**: No audit of what secrets exist in GitHub repository settings.
- **Recommendation**: Document all required secrets in `docs/RUNBOOK.md`. Remove unused secrets quarterly.
- **Effort**: 1 day
- **Status**: 🔴 Open

### SEC-008 — No Content Security Policy (CSP) Header
- **Severity**: Medium
- **Category**: Secure Headers
- **Finding**: No `Content-Security-Policy` header set. XSS payloads can execute freely in the browser.
- **OWASP**: A03:2021 Injection
- **Recommendation**: Add `SecurityHeaders` middleware setting CSP, HSTS, X-Frame-Options, X-Content-Type-Options.
- **Effort**: 1 day
- **Status**: 🔴 Open

### SEC-009 — No Formal Penetration Test
- **Severity**: Low (process)
- **Category**: Testing
- **Finding**: No documented penetration test has been performed.
- **Recommendation**: Schedule an annual third-party pentest before production launch.
- **Effort**: External engagement
- **Status**: 🔵 Backlog

---

## Resolved Findings

| ID | Finding | Resolved | Session |
|---|---|---|---|
| — | npm CVEs: form-data (CRLF), shell-quote (critical), ws (DoS) | 2026-07-01 | Security patches |
| — | Composer CVEs: guzzle, psr7, jmespath | 2026-07-01 | Security patches |
| — | Integration credentials stored as plaintext in DB | 2026-07-01 | Credential encryption |
| — | `MachineLocationUpdated::dispatch` inside DB transaction (Pusher failure rolled back data) | 2026-07-01 | Bell integration fix |
| — | Bell SSO token request silently failing (blank client secret) | 2026-07-01 | Bell integration fix |

---

## Hardening Roadmap

| Quarter | Goal |
|---|---|
| Q3 2026 | MFA, Secure Cookie, CSP headers, Rate limiting |
| Q4 2026 | SSRF audit, Secrets vault migration, CI scanning |
| Q1 2027 | Third-party penetration test, SOC 2 readiness assessment |
