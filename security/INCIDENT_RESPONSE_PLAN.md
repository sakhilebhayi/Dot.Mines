# Incident Response Plan

**Document ID:** IRP-001  
**Version:** 1.0  
**Classification:** Confidential  
**Owner:** Platform Engineering  
**Review Cycle:** Annual  

---

## 1. Incident Severity Levels

| Severity | Definition | Response Time | Examples |
|----------|-----------|--------------|---------|
| **P1 — Critical** | Full service outage or confirmed data breach | 15 min | DB down, secret leaked to public repo |
| **P2 — High** | Partial outage or security incident with active exploitation | 1 hour | Queue failure, API unresponsive |
| **P3 — Medium** | Degraded performance or potential security issue | 4 hours | Elevated error rate, failed health check |
| **P4 — Low** | Minor issue, no business impact | 24 hours | Single failed job, log warning |

---

## 2. Incident Response Phases

### Phase 1 — Detection & Triage (0–15 min)

1. Incident detected via Sentry alert, Horizon failure, health endpoint, or manual report.
2. On-call engineer acknowledges the alert and creates an incident record.
3. Severity assessed using the table above.
4. For P1/P2: notify team lead immediately.

### Phase 2 — Containment (15 min–1 hour)

1. Isolate affected systems (disable compromised accounts, revoke tokens, block IPs).
2. Enable maintenance mode if required: `php artisan down`.
3. Capture diagnostic artefacts: Sentry traces, Horizon job history, DB slow query logs.
4. For data breach: notify Data Protection Officer (DPO) within 1 hour.

### Phase 3 — Eradication & Recovery

1. Identify root cause via `PLATFORM_ERROR_LOG.md` and Sentry traces.
2. Deploy fix to staging, run `php artisan test --compact`, deploy to production.
3. Rotate any compromised secrets using `scripts/rotate-aws-iam-key.sh`.
4. Re-enable service: `php artisan up`.
5. Monitor Horizon queues and health endpoint for 30 minutes post-recovery.

### Phase 4 — Post-Incident Review (within 48 hours)

1. Write a post-mortem document in `deploy/INCIDENTS/`.
2. Document: timeline, root cause, impact, actions taken, preventive measures.
3. Update `PLATFORM_ERROR_LOG.md` with incident entry.
4. Add new risk items to `RISK_REGISTER.md` if applicable.
5. Follow-up tasks tracked in the engineering backlog.

---

## 3. Security Incident — Secret Leakage Protocol

1. Immediately revoke the leaked credential (API key, password, token).
2. Run `scripts/purge-secrets.sh` to remove from git history.
3. Rotate the credential via the relevant service provider.
4. Update `scripts/secrets-to-redact.txt` with the leaked value pattern.
5. Notify affected customers within 72 hours if PII was exposed (GDPR Art. 33).

---

## 4. Data Breach Protocol (GDPR)

1. Assess scope: what data, how many users, which teams affected.
2. Notify DPO and legal team within 1 hour of confirmation.
3. GDPR notification to supervisory authority required within 72 hours (Art. 33).
4. User notification required if high risk to individuals (Art. 34).
5. Document the breach and response in `deploy/INCIDENTS/`.

---

## 5. Communication Templates

**Internal P1 Slack message:**
```
🔴 P1 INCIDENT — [Service] is DOWN
Time: [HH:MM UTC]
Impact: [Description]
On-call: @[name]
Status page: [URL]
```

**External user notification:**
```
Subject: Service Disruption Notice — Mines Platform

We are currently experiencing a service disruption affecting [feature].
Our team is actively working to restore full functionality.
Estimated resolution: [time or "investigating"].
We apologise for the inconvenience.
```

---

*Last reviewed: 2026-06-07*
