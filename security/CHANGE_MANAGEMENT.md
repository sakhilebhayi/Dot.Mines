# Change Management Policy

**Document ID:** CM-001  
**Version:** 1.0  
**Classification:** Internal  
**Owner:** Platform Engineering  
**Review Cycle:** Annual  

---

## 1. Purpose

Ensure all changes to the Mines platform are planned, tested, approved, and documented to minimise risk of service disruption or security incidents.

## 2. Change Categories

| Category | Description | Approval | Testing Required |
|----------|-------------|---------|-----------------|
| **Standard** | Pre-approved routine changes (dependency updates, minor config) | 1 reviewer | CI must pass |
| **Normal** | New features, bug fixes, refactors | 2 reviewers | CI + staging |
| **Emergency** | Critical security patch or P1 fix | 1 senior engineer | CI required, post-deploy review |
| **Major** | Database migrations, infrastructure changes | Lead + DevOps | Full test suite + staging soak |

## 3. Change Process

1. **Request** — Create a pull request (PR) in GitHub with clear description, linked issue, and test plan.
2. **Review** — Required reviewers are automatically assigned via CODEOWNERS.
3. **Testing**
   - CI pipeline must pass: PHPUnit, PHPStan, Pint, Composer audit, NPM audit.
   - Staging deployment and smoke test for Normal/Major changes.
4. **Approval** — PR approved by required reviewers.
5. **Deploy** — Merge to `main` triggers the CD pipeline (`cd-deploy.yml`).
6. **Verify** — Health endpoint and Sentry monitored for 30 minutes post-deploy.
7. **Rollback** — If degraded: run previous container image or `git revert` + emergency deploy.

## 4. Database Migrations

- All migrations must be reversible (implement `down()` method).
- Migrations are reviewed by at least one senior engineer.
- Large data migrations run in chunks to avoid table locks.
- Migration tested in staging against a production-like dataset before production.

## 5. Emergency Changes

- Emergency changes bypass standard approval but require post-deploy review within 24 hours.
- Incident record created in `deploy/INCIDENTS/`.
- Retrospective added to `PLATFORM_ERROR_LOG.md`.

## 6. Change Freeze Periods

- No major changes during peak business hours (08:00–18:00 local time) unless emergency.
- Change freeze during public holidays and end-of-month reporting periods.

---

*Last reviewed: 2026-06-07*
