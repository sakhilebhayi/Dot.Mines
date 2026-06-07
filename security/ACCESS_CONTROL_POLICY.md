# Access Control Policy

**Document ID:** ACP-001  
**Version:** 1.0  
**Classification:** Internal  
**Owner:** Platform Engineering  
**Review Cycle:** Annual  

---

## 1. Purpose

Define how access to Mines platform systems, data, and infrastructure is granted, reviewed, and revoked.

## 2. Principles

- **Least Privilege** — users receive only the minimum access required for their role.
- **Separation of Duties** — critical operations require more than one person.
- **Need to Know** — data access is restricted to those with a legitimate business need.
- **Zero Trust** — every request is authenticated and authorised regardless of network location.

## 3. Role Definitions

| Role | Permissions |
|------|-------------|
| `admin` | Full team access — all read/write operations, user management |
| `fleet_manager` | Machine management, reports, alerts, geofences |
| `operator` | View machines, submit production records, view map |
| `viewer` | Read-only access to dashboards and reports |

Custom roles and permissions can be created per team via the RBAC system (`roles` / `permissions` tables).

## 4. Authentication Requirements

- All user accounts must be protected by a strong password (min. 12 characters).
- Multi-factor authentication (MFA/TOTP) is available and strongly recommended.
- Session tokens expire after 2 hours of inactivity.
- API tokens are scoped and revocable via Laravel Sanctum.

## 5. Provisioning

- Access is provisioned via team invitations; the inviting admin assigns the appropriate role.
- Default role for new team members is `viewer` unless explicitly overridden.
- Service account API tokens must be documented and reviewed quarterly.

## 6. Deprovisioning

- User accounts are deactivated within 24 hours of personnel departure.
- API tokens are revoked immediately upon request or personnel change.
- Soft-deleted data follows the data retention schedule.

## 7. Access Reviews

- Quarterly review of all active accounts and their assigned roles.
- Immediate review triggered by: security incident, role change, personnel departure.
- Findings are documented in the audit log (`audit_logs` table).

## 8. Privileged Access

- Infrastructure access (AWS, database, Redis) is restricted to senior engineers.
- All privileged sessions are logged.
- Production database access requires a time-limited token.

---

*Last reviewed: 2026-06-07*
