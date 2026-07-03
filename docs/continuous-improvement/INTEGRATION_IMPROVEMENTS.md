# Integration Improvements

> Track OEM integration health, adapter enhancements, and new provider support.

---

## Current Integration Score: 88/100

---

## Architecture Principles

The platform uses an integration-agnostic service layer. Adding a new OEM requires only:

1. Create an adapter implementing `ManufacturerAdapterInterface`
2. Register it in `AdapterRegistry`
3. Map the provider's data to `machine_metrics` (generic) or a provider-specific history table
4. Add fault code resolver to `MachineFaultCodeService` (if applicable)
5. Add KPI source block to `MachineKpiService` (if applicable)

**No changes to UI, business logic, analytics, or reporting are required.**

---

## Bell Equipment (ISO 15143-3) — Fully Integrated

| Feature | Status | Notes |
|---|---|---|
| Fleet snapshot (ISO 15143-3 XML) | ✅ Complete | Every 5 minutes |
| Location history | ✅ Complete | Every 5 minutes; configurable down to 5s |
| Operating hours history | ✅ Complete | Every 5 minutes |
| Fuel level + consumption | ✅ Complete | Every 5 minutes |
| Idle hours | ✅ Complete | Every 5 minutes |
| Load count + payload | ✅ Complete | Every 5 minutes |
| Active fault codes / caution codes | ✅ Complete | Auto-creates Alerts |
| Engine condition + DEF level | ✅ Complete | Per sync |
| DPF regeneration hours | ✅ Complete | Per sync |
| Daily KPIs (loads, payload, utilization) | ✅ Complete | Derived per sync |
| Health score | ✅ Complete | Computed from DEF, engine condition, fault codes |
| SSO OAuth2 token auth | ✅ Complete | Cached per sync; Password Credentials grant |
| Historical backfill | ✅ Complete | `bell:backfill-history` command |
| Real-time GPS broadcast (Reverb) | ✅ Complete | Every location update → `MachineLocationUpdated` |

**Open Improvements**:

- **INT-001**: Add Bell webhook push support when Bell exposes one (replaces polling)
- **INT-002**: Implement `BellHealthAlertJob` to fire platform alerts when health score drops below threshold

---

## Volvo CareTrack — Planned

| Feature | Status |
|---|---|
| Adapter stub | ✅ Registered in AdapterRegistry |
| API credentials | ❌ Not configured |
| Data mapping | ❌ Not implemented |
| Status | 🔵 Awaiting API access |

---

## Caterpillar VisionLink — Planned

| Feature | Status |
|---|---|
| Adapter stub | ✅ Registered |
| API credentials | ❌ Not configured |
| Dealer authorization | ❌ Requires dealer code |
| Status | 🔵 Awaiting API access |

---

## Komatsu KOMTRAX — Planned

| Feature | Status |
|---|---|
| Adapter stub | ✅ Registered |
| API credentials | ❌ Not configured |
| Status | 🔵 Awaiting API access |

---

## Generic OEM Adapter

- **Status**: ✅ Available
- **Purpose**: Any REST JSON API with Bearer or Basic auth can be integrated without custom code
- **Configuration**: Via `IntegrationManager` UI (credential schema rendered dynamically)
- **Limitation**: Cannot extract manufacturer-specific signals (fault codes, DEF, etc.) without custom adapter

---

## Integration Reliability

| Item | Status | Recommendation |
|---|---|---|
| Retry on API failure | ✅ Good | Exponential backoff on all sync jobs |
| Overlap prevention | ✅ Good | `ShouldBeUnique` on Bell ISO15143 job |
| Credential encryption | ✅ Good | AES-256 via `encrypted:array` cast |
| Token refresh | ✅ Good | Bearer token cached per sync cycle |
| Sync audit trail | ✅ Good | `bell_integration_audit_logs` + `integration_sync_logs` |
| Sync health dashboard | ⚠️ Partial | Audit log in DB; no UI visualisation |
| Jitter on retry | ❌ Missing | Add random jitter to backoff to prevent thundering herd |
| Webhook validation | ❌ N/A | No providers use webhooks yet |
| Historical reconciliation | ⚠️ Manual | `bell:backfill-history` command exists; not automated |

---

## New Integration Onboarding Checklist

When adding a new OEM integration:

- [ ] Create `app/Services/Integration/{Provider}Adapter.php` implementing `ManufacturerAdapterInterface`
- [ ] Register in `app/Services/Integration/AdapterRegistry.php`
- [ ] Define credential schema (`credentialSchema()` method)
- [ ] Map telemetry to `machine_metrics` columns
- [ ] Add fault code resolver to `MachineFaultCodeService::getActiveFaultCodes()`
- [ ] Add KPI source to `MachineKpiService::getDailyKpiSummary()`
- [ ] Write integration test covering: connection test, machine upsert, team isolation
- [ ] Add `.env` documentation for new credentials
- [ ] Update this document
