---
title: "Dot.Mines — Platform Wiki"
version: 0.2.0
status: draft
owners: [Mining Platform Lead]
platform-id: dot-mines
last-review: 2026-08-01
---

# Dot.Mines

Purpose: this is the Mines platform's own knowledge home — owned and maintained by the platform team, written in its own voice. It describes what this codebase actually is today, what it emits, and how it plans to connect to the wider Dot Ecosystem. Dot.Brain never edits this file; it only reads what we choose to publish.

> **Related:** [Dot.Brain's ingested view of this platform](https://github.com/sakhilebhayi/Dot.Brain/blob/main/platforms/dot-mines.md)

---

## 1. What Dot.Mines Is

Dot.Mines (repo name: `mines`) is a fleet management and mining-operations platform: real-time GPS machine tracking, geofencing, maintenance and fuel management, production tracking, OEM telemetry integrations, and a multi-agent AI optimization layer, built for open-pit mine operators, dispatchers, supervisors, and operators.

**Status:** this is a real, working Laravel 12 application — not a blueprint. The `README.md` describes it as "production ready" and it ships a substantial feature set: live map, operations feed (a structured replacement for WhatsApp shift comms), maintenance booking, fuel budgets, route planning, IoT sensor ingestion, 20+ OEM manufacturer integrations, Stripe billing, and team-based multi-tenancy. This wiki describes what is actually implemented; anything not yet built is called out explicitly under Roadmap (§7).

## 2. Architecture (as implemented)

| Layer | Technology |
|---|---|
| Backend | PHP 8.2+, Laravel 12.x |
| Frontend | Livewire 3.x, Alpine.js 3.x |
| Database | PostgreSQL 16+ |
| Styling | Tailwind CSS 3.x, DaisyUI 5.x |
| Charts | ApexCharts 5.x, Chart.js 4.x |
| Maps | Leaflet 1.9.x + Leaflet Draw, OpenStreetMap / Esri World Imagery |
| Real-time | Laravel Reverb (WebSockets), Laravel Echo, Pusher.js |
| Queue | Laravel Queue (database driver) |
| Auth | Laravel Jetstream + Sanctum, team-scoped sessions |
| Payments | Stripe via Laravel Cashier |
| File storage | AWS S3 via Flysystem |
| Search | Laravel Scout |
| Error monitoring | Sentry |
| Static analysis / tests | PHPStan, Psalm, PHPUnit 11 |

Multi-tenancy is team-based: each mine operates as an isolated `Team`, with role-and-policy-scoped data access (`HasTeamFilters` trait, `AuthServiceProvider` policies for Machines, Alerts, Geofences, Integrations, Notifications, Reports, Teams). Roles observed in the codebase: Operator, Supervisor, Safety Officer, Manager, Admin.

AI features run as a set of specialist services under `app/Services/AI/`: `FleetOptimizerAgent`, `MaintenancePredictorAgent`, `FuelPredictorAgent`, `RouteAdvisorAgent`, `CostAnalyzerAgent`, `AnomalyDetectorAgent`, orchestrated by `AIOptimizationService`. Recommendations are logged and can be accepted or dismissed (`AiRecommendationAction`), which is the closest existing analogue to an audit trail for AI-driven decisions.

OEM telemetry integrations live under `app/Services/Integration/` — one service class per manufacturer (CAT, Komatsu, Volvo, Sandvik, Epiroc, Liebherr, Hitachi, Hyundai, John Deere, Doosan, JCB, Bobcat, Kawasaki, Kobelco, Yanmar, Kubota, XCMG, CASE, New Holland, Atlas Copco, Bell, Sany, Takeuchi, Roundebult, CTrack), all implementing a common `ManufacturerServiceInterface` via `BaseManufacturerService`. Ingestion is webhook-based (`WebhookController`), with credentials stored per-team and never exposed in API responses.

## 3. Domain Entities It Owns

Derived from `app/Models/` and `database/migrations/`:

| Entity | Model | Notes |
|---|---|---|
| Team | `Team` | Tenant root — one mine per team |
| Machine | `Machine` | Trucks, loaders, drills; manufacturer, capacity, hours meter, status, GPS position, cycle/queue/loading time fields |
| Mine area | `MineArea` | Operational boundary, 4-point coordinate polygon |
| Geofence / Geofence entry | `Geofence`, `GeofenceEntry` | Virtual perimeters tied to mine areas; entry/exit tracked per machine |
| Route / Waypoint | `Route`, `Waypoint` | Sequenced waypoints, route geometry, speed limits, Traffic Management Plan docs |
| Shift | `Shift` | Shift templates (A/B/C), used by scheduling and the operations feed |
| Production record / target / forecast | `ProductionRecord`, `ProductionTarget`, `ProductionForecast` | Recorded vs reported loads, shift/section targets, AI-driven forecasts |
| Maintenance record / schedule / alert | `MaintenanceRecord`, `MaintenanceSchedule`, `MaintenanceAlert`, `ComponentReplacement` | Preventive/corrective booking, health-trend-triggered alerts |
| Machine health | `MachineHealthStatus`, `HealthMetric` | Health scoring feeding the Maintenance Predictor agent |
| Fuel tank / transaction / budget | `FuelTank`, `FuelTransaction`, `FuelBudget`, `FuelMonthlyAllocation`, `FuelConsumptionMetric`, `FuelAlert` | Per-mine-area tanks, allocation, monthly budgets, consumption tracking |
| IoT sensor / reading | `IoTSensor`, `SensorReading` | Per-machine sensors, polling, anomaly detection |
| Alert | `Alert` | Generic real-time alert record |
| Compliance violation / report | `ComplianceViolation`, `ComplianceReport` | Logged violations, exportable compliance reports |
| Operator fatigue | `OperatorFatigue` | Per-operator fatigue tracking |
| AI agent / session / insight / recommendation | `AIAgent`, `AIAnalysisSession`, `AIInsight`, `AIRecommendation`, `AiRecommendationAction`, `AILearningData` | The multi-agent optimization layer's own state |
| Integration | `Integration` | Per-team OEM manufacturer credentials/config |
| Report | `Report` | Generated PDF/CSV reports (maintenance, production, compliance, incident) |
| Subscription / plan / payment / invoice | `Subscription`, `SubscriptionPlan`, `Payment`, `Invoice` | Stripe-backed billing, fleet-slot enforcement |
| Notification | `Notification` | In-app/email notification records |
| Activity log | `ActivityLog` | Audit trail entries |

Not present in this domain model (yet): explicit `pit`/`bench` or `haul cycle` entities as named in Dot.Brain's registry view (§5) — the closest existing concepts are `Route`/`Waypoint` (haul routing) and `Machine.cycle_time`/`queue_time`/`loading_time` fields (cycle timing lives on the machine record, not as its own event/entity).

## 4. Events It Emits

All are `ShouldBroadcast` Laravel events, broadcast in-app over Reverb/WebSockets — these are internal real-time UI events today, not yet published as cross-platform Knowledge Packs:

| Event | Trigger |
|---|---|
| `MachineLocationUpdated` | GPS position update |
| `MachineOffline` | Machine stops reporting |
| `GeofenceEntryDetected` / `GeofenceExitDetected` | Machine crosses a geofence boundary |
| `AlertTriggered` | Any alert condition fires |
| `MaintenanceAlertTriggered` | Health-trend-based predictive maintenance alert |
| `ComplianceViolationDetected` | Compliance violation logged |
| `SensorReadingRecorded` / `SensorStatusChanged` | IoT sensor telemetry |

Supporting background jobs (`app/Jobs/`) that produce these events include `MachineLocationUpdateJob`, `MachineIdleMonitoringJob`, `MachineStatusMonitoringJob`, `GeofenceCrossingDetectionJob`, `RouteSpeedMonitoringJob`, `AlertGenerationJob`, `SyncIntegrationMachinesJob`, and `SyncMachineMetricsJob`.

## 5. Connecting to Dot.Brain

**Current state: not yet integrated.** Nothing in this repository publishes or consumes Dot.Brain Knowledge Packs today — no DKP manifest, no signing key, no pack emission code. The events in §4 are internal-only WebSocket broadcasts scoped to a team's live UI.

Dot.Brain's [`platforms/dot-mines.md`](https://github.com/sakhilebhayi/Dot.Brain/blob/main/platforms/dot-mines.md) describes an *ingested/target* view of this platform framed around haul-cycle management, pits, shifts, and a tight operational loop with Dot.Central — with a `dot-mines` manifest, four Knowledge Pack types (observation/insight/outcome/incident), four domain metrics (`mining.cycle_time_p50`, `mining.false_finding_rate`, `mining.unplanned_downtime_hours`, `mining.cost_per_false_finding`), and a worked example thread (the Kolomela wet-season cycle-time story). That document describes ecosystem intent for this platform; it is not yet reflected in this codebase. Planned work to close the gap:

- [ ] Publish `platform.dkp.json` manifest matching the shape in Dot.Brain's example (`platform_id: dot-mines`, signing key, publish/subscribe lists)
- [ ] Emit `observation` packs from `MachineLocationUpdated`, `SensorReadingRecorded`, and production/maintenance telemetry
- [ ] Emit `incident` packs from `ComplianceViolationDetected` and `AlertTriggered` (critical class)
- [ ] Model haul-cycle timing as its own event/entity rather than fields on `Machine`, to align with the `mining.cycle_time_p50` metric definition
- [ ] Stand up the Mines↔Central real-time dispatch loop described in Dot.Brain §6 — no `Dot.Central` integration exists in this codebase yet

## 6. Naming Discrepancy (Open Question)

Dot.Brain's ecosystem registry refers to this platform as `dot-mines` and its knowledge doc lives at `Dot.Brain/platforms/dot-mines.md`. The actual GitHub repository is named `mines` (no `Dot.` prefix) — `github.com/sakhilebhayi/mines`. The application itself brands as "Mines — Mining Intelligence Platform" in its README, not "Dot.Mines". This wiki keeps the `Dot.Mines` branding in its title for ecosystem consistency, but the repo-name vs registry-id vs product-name mismatch (`mines` / `dot-mines` / "Mines") is unresolved and should be reconciled — either by renaming the repo, or by Dot.Brain's registry explicitly documenting the mapping.

## 7. Roadmap / Open Questions

- [ ] Reconcile repo name (`mines`) vs Dot.Brain registry ID (`dot-mines`) vs in-app product name ("Mines") — see §6
- [ ] Design and implement Dot.Brain Knowledge Pack publishing (observation/insight/outcome/incident)
- [ ] Add explicit `pit`/`bench` and `haul_cycle` domain concepts if the ecosystem's mining ontology (Dot.Brain §2, §11) is to be adopted here
- [ ] Build the Dot.Central real-time dispatch integration
- [ ] Decide whether operator-level metrics (e.g. individual cycle-time speed) are ever surfaced, given Dot.Brain's Dopamine-surface guidance against speed leaderboards (`dot-mines.md` §8)

## Change Log

| Version | Date | Author | Change |
|---|---|---|---|
| 0.1.0 | 2026-08-01 | Mining Platform Lead | Initial wiki: derived from the actual `mines` Laravel codebase (models, events, README), cross-referenced against Dot.Brain's platforms/dot-mines.md for ecosystem framing and gap analysis |
| 0.2.0 | 2026-08-02 | Sakhile Bhayi | Redesigned `resources/views/welcome.blade.php`'s marketing surface: the placeholder gradient-and-SVG-icon brand mark in the nav and footer is now the real `public/images/logo.png` lockup; the hero and CTA sections' abstract gradient backgrounds are now real, licensed Unsplash photography (excavators at a mining site, and open-pit haul trucks — both hotlinked via Unsplash's CDN, photographers credited inline as HTML comments) layered under a dark gradient overlay tuned for WCAG-adequate text contrast against the photo. Verified visually via a standalone Tailwind-CDN render of the same markup before committing, not just reviewed as code. |
| 0.1.1 | 2026-08-01 | Platform loop pass | Real Dot.Mines logo/favicons wired into the auth-card mark, sidebar brand mark, and all `<head>` favicon links; in-app "Mines" text branding aligned to "Dot.Mines" (titles, APP_NAME, composer.json); removed an unreferenced dead Jetstream default marketing view (`resources/views/components/welcome.blade.php`); added a missing `tests/TestCase.php` (existing PHPUnit tests referenced it but it did not exist in the repo) plus new Feature tests for the dashboard, the fleet list, and `MineArea` cross-team access; flagged (not fixed) that `MineArea` is the one team-owned model without the `HasTeamFilters` global scope other models use — the live `/mine-areas/{id}` route is still protected by an explicit `abort(403)` check in `MineAreaDetail::mount()`, but `ReportController::view2()` has an unscoped `MineArea::all()` fallback branch that is unreachable under normal `ensure_team` middleware flow and should be tightened defensively |
