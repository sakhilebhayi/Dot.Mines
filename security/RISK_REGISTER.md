# Risk Register

**Document ID:** RR-001  
**Version:** 1.0  
**Classification:** Confidential  
**Owner:** Platform Engineering  
**Review Cycle:** Quarterly  

---

## Risk Rating Matrix

| Likelihood \ Impact | Low (1) | Medium (2) | High (3) | Critical (4) |
|---|---|---|---|---|
| **Unlikely (1)** | 1 | 2 | 3 | 4 |
| **Possible (2)** | 2 | 4 | 6 | 8 |
| **Likely (3)** | 3 | 6 | 9 | 12 |
| **Almost Certain (4)** | 4 | 8 | 12 | 16 |

**Thresholds:** Low 1–3 · Medium 4–6 · High 7–12 · Critical 13–16

---

## Active Risk Register

| ID | Risk | Category | Likelihood | Impact | Score | Rating | Mitigation | Owner | Status |
|----|------|----------|-----------|--------|-------|--------|-----------|-------|--------|
| R-001 | Dependency CVE in Composer/NPM packages | Security | 3 | 3 | 9 | High | Automated `composer audit` + `npm audit` in CI; weekly Dependabot alerts | Engineering | Mitigated |
| R-002 | Secret leakage via git commit | Security | 2 | 4 | 8 | High | `gitleaks` pre-commit hook + CI scan; secret rotation playbook | Engineering | Mitigated |
| R-003 | SQL injection via user input | Security | 1 | 4 | 4 | Medium | Laravel query builder; parameterised queries; PHPStan + Semgrep scans | Engineering | Mitigated |
| R-004 | Cross-Site Scripting (XSS) in Blade views | Security | 2 | 3 | 6 | Medium | Blade auto-escaping; `scan:blade-unescaped` CI step; CSP headers | Engineering | Mitigated |
| R-005 | Unauthorised cross-team data access | Security | 2 | 4 | 8 | High | Team-scoped RBAC; `ensure_team` middleware; policy-based authorisation | Engineering | Mitigated |
| R-006 | Webhook replay attack (Paystack) | Security | 2 | 3 | 6 | Medium | HMAC-SHA512 signature validation; 5-min timestamp window; rate limiting | Engineering | Mitigated |
| R-007 | Queue job failure causing data loss | Availability | 2 | 3 | 6 | Medium | Retry logic; failed job tracking via Horizon; alerting on queue depth | Engineering | Mitigated |
| R-008 | Database unavailability | Availability | 1 | 4 | 4 | Medium | Health endpoint monitoring; automated backups; RDS Multi-AZ in prod | DevOps | Open |
| R-009 | Unencrypted PII in logs | Compliance | 2 | 3 | 6 | Medium | `LogRedactionService` masks sensitive fields; log-level controls | Engineering | Mitigated |
| R-010 | GDPR non-compliance — data not exportable/deletable | Compliance | 1 | 4 | 4 | Medium | `ExportUserDataJob` + `DeleteUserDataJob`; GDPR controller with audit trail | Engineering | Mitigated |
| R-011 | Brute-force login attacks | Security | 3 | 2 | 6 | Medium | Rate limiter (`throttle:login` 5/min); lockout; TOTP 2FA available | Engineering | Mitigated |
| R-012 | Memory exhaustion in report generation | Performance | 2 | 2 | 4 | Medium | `lazyById(500)` chunking in `GenerateReportJob`; 300s job timeout | Engineering | Mitigated |
| R-013 | Third-party integration credential exposure | Security | 1 | 4 | 4 | Medium | Encrypted credentials in DB; never logged; rotation playbook | Engineering | Open |
| R-014 | Kubernetes cluster misconfiguration | Infrastructure | 2 | 3 | 6 | Medium | RBAC on K8s cluster; network policies; HPA for auto-scaling | DevOps | Planned |
| R-015 | Insider threat — admin data exfiltration | Security | 1 | 4 | 4 | Medium | Audit log on all sensitive operations; immutable audit trail | Engineering | Mitigated |

---

## Risk Treatment Summary

| Rating | Count | Treatment |
|--------|-------|-----------|
| Critical | 0 | — |
| High | 2 | Mitigated |
| Medium | 13 | 11 mitigated, 2 planned/open |
| Low | 0 | — |

---

*Last reviewed: 2026-06-07*  
*Next review due: 2026-09-07*
