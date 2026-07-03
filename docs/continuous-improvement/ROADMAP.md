# Roadmap — Product & Engineering

> Long-term vision for the Mines Fleet Management Platform.
> Updated after every significant strategic decision.

---

## Vision

Build the most capable, extensible, and reliable fleet management platform for mining operations — combining real-time OEM telemetry, AI-driven optimization, and enterprise-grade observability into a single unified system.

---

## Now (Q3 2026) — Stabilisation & Production Readiness

**Goal**: Make the platform production-ready for the first customer deployment.

| Item | Priority | Status |
|---|---|---|
| Migrate from SQLite to MySQL/PostgreSQL | Critical | 🔴 Open |
| Add CI pipeline (GitHub Actions) | Critical | 🔴 Open |
| Configure Sentry DSN | High | 🔴 Open |
| Add `/health` endpoint | High | 🔴 Open |
| Implement MFA (TOTP via Fortify) | High | 🔴 Open |
| Add CSP + Secure headers middleware | High | 🔴 Open |
| Fix API rate limiting consistency | High | 🟡 In Progress |
| Resolve PHPStan baseline errors | Medium | 🟡 In Progress |
| Feature test coverage to 60% | High | 🟡 In Progress |
| Load test Bell sync pipeline | High | 🔴 Open |
| Add Supervisor config for `bell:watch-locations` | Medium | 🟡 Planned |
| Deploy uptime monitoring | High | 🔴 Open |

---

## Next (Q4 2026) — Scale & Expand

**Goal**: Scale to 500+ machines, onboard additional OEM providers.

| Item | Priority | Notes |
|---|---|---|
| Volvo CareTrack adapter | High | Awaiting API credentials |
| Caterpillar VisionLink adapter | High | Requires dealer authorization |
| Komatsu KOMTRAX adapter | Medium | Awaiting API credentials |
| Partition `bell_equipment_location_history` by month | High | After MySQL migration |
| Secrets vault migration (AWS Secrets Manager) | Medium | — |
| API idempotency keys | Medium | — |
| Feature test coverage to 80% | High | — |
| k6 load test suite | High | Benchmark P95 < 200ms |
| `MachineDetail.php` OEM agnosticism refactor | Medium | Remove Bell imports |
| `Reports.php` OEM agnosticism refactor | Medium | Route through services |
| TelemetrySnapshot DTO | Low | Replace array contracts |

---

## Later (Q1 2027) — Enterprise Features

| Item | Priority | Notes |
|---|---|---|
| Webhook push support (Bell, future OEMs) | High | Reduces polling load |
| Multi-site / multi-region deployment | High | Kubernetes + RDS Multi-AZ |
| Predictive maintenance v2 (ML model) | High | Train on historical fault data |
| Operator performance analytics | Medium | Per-operator efficiency scoring |
| Fuel theft detection (AI anomaly) | Medium | Bell fuel level pattern analysis |
| SOC 2 Type II readiness | High | Compliance milestone |
| Public API (partner integrations) | Medium | REST + Webhook |
| White-label / multi-tenant support | Medium | Separate team DBs per customer |
| Annual third-party penetration test | High | External security firm |
| Mobile app (iOS / Android) | Low | React Native or Flutter |

---

## Backlog (Future)

- Operator fatigue monitoring (ISO 39001 compliance)
- MHSA digital inspection reports
- ESG carbon footprint reporting from fuel data
- Autonomous dispatch optimization (AI-driven cycle assignment)
- Integration with mine planning software (Deswik, Surpac)
- P&L per machine (fuel cost + payload revenue)
- Real-time haul road condition alerts (IoT + GPS speed correlation)
