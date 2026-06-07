# Vendor Management Policy

**Document ID:** VM-001  
**Version:** 1.0  
**Classification:** Internal  
**Owner:** Platform Engineering  
**Review Cycle:** Annual  

---

## 1. Purpose

Manage the security, compliance, and operational risks introduced by third-party vendors and service providers.

## 2. Vendor Inventory

| Vendor | Service | Data Shared | Classification | SLA | Review Cycle |
|--------|---------|-------------|---------------|-----|-------------|
| AWS | S3 storage, infrastructure | Fleet files, backups | Restricted | 99.99% | Annual |
| Sentry | Error monitoring | Stack traces (no PII) | Confidential | 99.9% | Annual |
| Paystack | Payment processing | Billing data | Restricted | 99.5% | Annual |
| Bell Equipment | IoT / OEM data (via Bell API) | Machine telemetry | Confidential | Best-effort | Annual |
| GitHub | Source control, CI/CD | Application code | Confidential | 99.9% | Annual |
| SendGrid / SMTP | Transactional email | User email address | Restricted | 99.9% | Annual |

## 3. Vendor Onboarding Requirements

Before engaging a new vendor that processes Mines platform data:

1. Security questionnaire completed.
2. SOC 2 Type II report or equivalent certification reviewed.
3. Data Processing Agreement (DPA) signed.
4. Data residency requirements confirmed (EU preferred).
5. Documented in this register.

## 4. Vendor Risk Assessment

Vendors are rated by:
- **Data sensitivity** — what data is shared?
- **Criticality** — what happens if the vendor is unavailable?
- **Security posture** — do they have SOC 2 / ISO 27001?

High-criticality vendors (AWS, Paystack) are reviewed annually by the engineering lead.

## 5. Vendor Offboarding

When a vendor relationship ends:
1. Revoke all API keys and access tokens.
2. Request data deletion confirmation in writing.
3. Update this register with offboarding date.
4. Remove vendor configuration from application.

## 6. Open Source Dependencies

- All OSS dependencies are tracked in `composer.json` and `package.json`.
- `composer audit` and `npm audit` run on every CI pipeline run.
- Critical CVEs must be remediated within 72 hours.

---

*Last reviewed: 2026-06-07*
