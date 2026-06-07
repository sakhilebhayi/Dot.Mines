# Information Security Policy

**Document ID:** ISP-001  
**Version:** 1.0  
**Classification:** Internal  
**Owner:** Platform Engineering  
**Review Cycle:** Annual  

---

## 1. Purpose

This policy establishes the framework for protecting the confidentiality, integrity, and availability of information assets on the Mines fleet management platform.

## 2. Scope

Applies to all personnel, contractors, and systems that access, process, or store Mines platform data including:
- Production, staging, and development environments
- All cloud infrastructure (AWS S3, Redis, database services)
- All team members, contractors, and third-party integrators

## 3. Information Classification

| Level | Description | Examples |
|-------|-------------|---------|
| **Restricted** | Highest sensitivity; strict access controls | API keys, passwords, PII, financial data |
| **Confidential** | Business-sensitive; need-to-know | Fleet telemetry, production reports, audit logs |
| **Internal** | General internal use | Application source code, internal documentation |
| **Public** | Safe for external disclosure | Marketing materials, public API docs |

## 4. Access Control

- All access follows the principle of least privilege.
- Role-based access control (RBAC) is enforced via the `admin`, `fleet_manager`, `operator`, `viewer` roles.
- Privileged access requires multi-factor authentication (MFA).
- Access is reviewed quarterly and revoked within 24 hours of personnel departure.
- All API access is authenticated via Laravel Sanctum tokens.

## 5. Data Protection

- All data in transit uses TLS 1.2+.
- Secrets and credentials are stored in environment variables / secrets managers — never in source code.
- Git history is scanned via `gitleaks` in the pre-commit hook and CI pipeline.
- Sensitive log fields are redacted via `LogRedactionService`.
- S3 buckets enforce server-side encryption (AES-256/KMS).

## 6. Vulnerability Management

- Composer and NPM dependencies are audited on every CI run.
- PHPStan static analysis runs on every pull request.
- Semgrep and OWASP ZAP scans run in CI.
- Critical vulnerabilities must be remediated within 72 hours; high within 7 days.

## 7. Incident Response

See [INCIDENT_RESPONSE_PLAN.md](./INCIDENT_RESPONSE_PLAN.md).

## 8. Compliance

This policy aligns with ISO/IEC 27001:2022 and supports SOC 2 Type II readiness.

## 9. Violations

Violations of this policy may result in disciplinary action up to and including termination and legal proceedings.

---

*Last reviewed: 2026-06-07*
