# Business Continuity Plan

**Document ID:** BCP-001  
**Version:** 1.0  
**Classification:** Confidential  
**Owner:** Platform Engineering  
**Review Cycle:** Annual  

---

## 1. Recovery Objectives

| Metric | Target | Notes |
|--------|--------|-------|
| **RTO** (Recovery Time Objective) | 4 hours | Time to restore service after outage |
| **RPO** (Recovery Point Objective) | 1 hour | Maximum acceptable data loss |

## 2. Critical Systems

| System | Criticality | Dependency | Recovery Priority |
|--------|------------|-----------|-----------------|
| PostgreSQL database | Critical | All features | P1 — restore first |
| Redis | High | Sessions, queues, cache | P2 |
| Application (Kubernetes) | High | API + UI | P2 |
| AWS S3 | Medium | Reports, attachments | P3 |
| Laravel Horizon | Medium | Background jobs | P3 |

## 3. Backup Strategy

| Data | Frequency | Retention | Location | Encryption |
|------|----------|----------|----------|-----------|
| PostgreSQL full dump | Daily at 02:00 UTC | 30 days | AWS S3 (`backup-role-policy.json`) | AES-256/KMS |
| PostgreSQL WAL archiving | Continuous | 7 days | AWS S3 | AES-256/KMS |
| S3 bucket versioning | On every write | 30 days | AWS S3 (versioned) | AES-256/KMS |
| Redis snapshot | Hourly | 24 hours | Redis persistence | AES-256 |

Backup restoration is tested monthly via `scripts/restore-db.sh`.

## 4. Failover Procedures

### 4.1 Database Failure

1. Check health endpoint: `GET /health` → `checks.database`.
2. If `error`, check PostgreSQL logs and RDS console.
3. Promote read replica if primary is unrecoverable.
4. Restore from backup: `scripts/restore-db.sh`.
5. Update `DB_HOST` environment variable and restart application.

### 4.2 Application Pod Failure (Kubernetes)

1. Kubernetes HPA automatically replaces failed pods.
2. If cluster-level failure: redeploy from `deploy/k8s/deployment.yaml`.
3. Monitor via `kubectl get pods -n mines-production`.

### 4.3 Cache/Queue Failure

1. Health endpoint reports cache/queue status.
2. Restart Redis service or failover to replica.
3. Jobs that were in-flight will be retried via Horizon (3 attempts).

### 4.4 Complete Region Outage

1. Activate cross-region replica if configured.
2. Update DNS to point to failover region.
3. Estimated RTO for full region failover: 4–8 hours.

## 5. Communication During Outage

1. Internal: Slack `#incidents` channel (see Incident Response Plan).
2. External: Update status page; notify enterprise customers via email within 1 hour of P1.

## 6. Testing Schedule

| Test | Frequency | Last Tested |
|------|----------|------------|
| Database backup restore | Monthly | — |
| Kubernetes pod recovery | Quarterly | — |
| Full disaster recovery drill | Annually | — |

---

*Last reviewed: 2026-06-07*
