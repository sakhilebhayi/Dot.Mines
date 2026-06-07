# Backup Policy

**Document ID:** BP-001  
**Version:** 1.0  
**Classification:** Internal  
**Owner:** DevOps / Platform Engineering  
**Review Cycle:** Annual  

---

## 1. Purpose

Ensure all critical data can be recovered in the event of accidental deletion, corruption, ransomware, or infrastructure failure.

## 2. What is Backed Up

| Asset | Method | Script / Service |
|-------|--------|----------------|
| PostgreSQL database | `pg_dump` + WAL archiving | `scripts/backup-db.sh` |
| AWS S3 files (reports, attachments) | S3 versioning + cross-region replication | AWS console |
| Application configuration | GitHub repository | Source control |
| Secrets | AWS Secrets Manager / GitHub Secrets | — |

## 3. Backup Schedule

| Backup Type | Schedule | Retention | Destination |
|-------------|---------|----------|------------|
| Full database dump | Daily at 02:00 UTC | 30 days | `s3://mines-backups/db/` |
| Incremental WAL | Every 15 minutes | 7 days | `s3://mines-backups/wal/` |
| S3 file versions | Continuous | 30 days | Same bucket (versioned) |
| Application code | Every commit | Indefinite | GitHub |

## 4. Encryption

- All backups are encrypted with AES-256 using AWS KMS-managed keys.
- The KMS key policy is defined in `deploy/s3-bucket-policy-enforce-kms.json`.
- Backup bucket access is restricted by `deploy/backup-role-policy.json`.

## 5. Restoration Procedures

### 5.1 Database Restoration

```bash
# List available backups
aws s3 ls s3://mines-backups/db/

# Download and restore
./scripts/restore-db.sh s3://mines-backups/db/mines_backup_YYYY-MM-DD.sql.gz
```

### 5.2 File Restoration (S3)

```bash
# List versions
aws s3api list-object-versions --bucket mines-files --prefix reports/

# Restore specific version
aws s3api copy-object \
  --copy-source mines-files/reports/file.pdf?versionId=VERSION_ID \
  --bucket mines-files \
  --key reports/file.pdf
```

## 6. Backup Monitoring

- Backup success/failure is logged and monitored.
- A failed backup triggers an alert within 1 hour.
- Weekly confirmation that the latest backup exists and is accessible.

## 7. Restoration Testing

- Monthly: restore the previous day's database backup to a test environment and verify data integrity.
- Results documented in the internal operations log.
- If restore fails, escalate to P2 incident.

## 8. Compliance

- Backup retention periods are aligned with GDPR data retention requirements.
- User data deletion requests (`DeleteUserDataJob`) propagate to backup exclusion lists within 30 days.
- Audit logs are backed up and retained for 2 years (per the data retention policy).

---

*Last reviewed: 2026-06-07*
