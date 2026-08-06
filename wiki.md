---
title: "Dot.Mines — Platform Wiki"
version: 0.3.4
status: draft
owners: [Mining Platform Lead]
platform-id: dot-mines
last-review: 2026-08-06
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
| 0.3.4 | 2026-08-06 | Platform-loop pass | Redesigned the guest-facing auth page family (login, register, forgot-password, reset-password, confirm-password, two-factor-challenge, verify-email, plus the terms/policy views) to match the welcome-page brand identity from 0.3.3, since they were still Jetstream's stock scaffold — an unstyled slate/amber dark theme with several hardcoded stock-gray (`text-gray-600`, `text-gray-900`) and indigo focus rings left over from the original Jetstream generator, disconnected from the real `--ink #211a14` / `--gold #d99e2b` / `--stone #f4efe4` / `--sand #c9b896` palette and `Outfit`/`Plus Jakarta Sans`/`JetBrains Mono` type pairing established in the welcome page. Moved the shared `layouts/guest.blade.php` onto the same Google Fonts (Outfit/Plus Jakarta Sans/JetBrains Mono) and `:root` CSS custom properties as `welcome.blade.php`, so every auth page inherits the identical token set. Replaced `authentication-card-logo.blade.php`'s placeholder amber-box-plus-text lockup with the real `public/images/logo.png` at `h-16 sm:h-20` — the same nav logo sizing already proven legible on the welcome page, instead of Jetstream's undersized default. Restyled `authentication-card.blade.php` (solid `--ink` page background, `--ink-soft` card panel with a hairline `--line` border, replacing the generic `bg-gradient-to-br from-slate-900 via-slate-800 to-amber-900` on `bg-gray-100`-adjacent default) and the shared `label`/`input`/`button`/`checkbox`/`validation-errors` field components (gold focus rings, gold CTA buttons in `font-display`, sand-colored helper text, a bordered/tinted red card for validation errors instead of bare unstyled text). Swept every `auth/*.blade.php` page for hardcoded inline colors that don't exist in this system (`text-slate-300`, `text-amber-400/300`, `text-gray-600/900`, `focus:ring-indigo-500`) and replaced them with the token equivalents (`--sand`, `--gold`/`--gold-soft`, `--stone`) — `verify-email.blade.php` and `two-factor-challenge.blade.php` in particular still had un-migrated stock Jetstream gray/indigo classes that would have been low-contrast against a dark card. Restyled `terms.blade.php`/`policy.blade.php` (white-card-on-`bg-gray-100` Jetstream default → the same ink/card frame, `prose-invert` typography) for consistency, though `Features::termsAndPrivacyPolicy()` is currently commented out in `config/jetstream.php` so those routes are not live today. Did not touch any `route()`/`action`/`@csrf`/input `name` attributes or validation-error display logic — visual restyle only. Verified: `npm run build` clean; `php artisan serve` against the existing `dot_mines_verify` local Postgres database (already migrated from an earlier verification pass); confirmed `/login`, `/register`, `/forgot-password`, and `/reset-password/{token}` render correctly with legible text and no console errors at both 1600px and 375px, no horizontal overflow. `confirm-password` and `two-factor-challenge` are gated behind an authenticated/partial-login session that can't be reached without creating a real account, so those two were verified by code review only, reusing the same already-verified shared components. `verify-email` has no live route today (email verification is not enabled on the `User` model), also verified by code review only. |
| 0.3.3 | 2026-08-04 | Platform-loop pass | Redesigned `resources/views/welcome.blade.php` as the ecosystem's guest-facing-design pilot (four combined skills: `frontend-design`, `design-taste-frontend-v1`, `emil-design-eng`, `ui-ux-pro-max`). The prior page was a generic dark-SaaS template (near-black background, bright amber gradients, floating blurred orbs, a fake dashboard mockup, glassmorphism cards) — exactly the AI-slop pattern the design skills warn against. Sampled the real logo (`public/images/logo.png`) directly to ground the new palette in the brand's actual mustard-gold/cocoa-brown identity instead of a generic dark-mode default (`--ink #211a14`, `--gold #d99e2b`, `--umber #6b4226`, `--stone #f4efe4`, single accent, no pure black). Typography: `Outfit` (display) + `Plus Jakarta Sans` (body) + `JetBrains Mono` (data/labels) — no Inter, matching the anti-slop constraint. Replaced the 3-equal-card feature grid and icon-in-colored-box cliché with a divided asymmetric list (hairline borders, mono field-tags instead of numbered markers, since the six features aren't a sequence). Added a large, quiet line-art headframe silhouette (echoing the real logo's headframe icon) as the hero's signature element, replacing the generic floating badges/glow orbs. Rewrote all copy to remove fabricated stats (`40%`, `99.9%`, `<40ms`) and hype language ("AI-Powered Mining Intelligence") in favor of concrete, functional claims grounded in real platform capabilities (§1/§3 of this wiki) — the hero's "live data" strip is a capability list, not invented numbers. Removed the three dead `href="#"` social-icon links in the footer (unfinished-artifact clutter). Motion pared back to a single restrained scroll-reveal (IntersectionObserver, `prefers-reduced-motion` respected) and `scale(0.97)` press-feedback on buttons — no perpetual/ambient animation. Verified end-to-end: `npm run build` clean, rendered via a local `php artisan serve` preview at both desktop and mobile (375×812) viewports, confirmed via DOM inspection (contrast, reveal-visibility, no console errors) since the automated screenshot tool had an intermittent capture-timing issue unrelated to the page itself. This is a pilot — reviewed before being used as the pattern for the rest of the ecosystem's guest-facing pages. |
| 0.3.2 | 2026-08-03 | Sakhile Bhayi | **The app was completely unable to serve any HTTP request** — found by actually running it in a browser for the first time. `public/index.php` had `__DIR__.'/mines/vendor/autoload.php'` etc. instead of `__DIR__.'/../vendor/autoload.php'` (a stray `mines/` segment introduced by commit `98a3c92`, which reverted a correct fix from `81f4e43`), so every single request 500'd on the autoloader require. Invisible to `php artisan test`, which bootstraps the app in-process and never goes through `public/index.php`. Fixed. Also fixed a real Tailwind v4 migration gap found in the same pass: `vite.config.js` never registered the `@tailwindcss/vite` plugin despite the package being installed, and `resources/css/app.css` still used Tailwind v3's `@tailwind base/components/utilities` directives and needed a `@config` directive to keep reading the existing `tailwind.config.js` custom theme (animations, daisyUI) under v4. Removed the now-conflicting `postcss.config.js`. Verified by actually building assets (`npm run build`) and running the app end-to-end in a real browser — the welcome page renders correctly with the real logo and both Unsplash photos. |
| 0.3.1 | 2026-08-03 | Sakhile Bhayi | Fixed a lingering branding gap: `application-logo.blade.php` (and, where present, `application-mark.blade.php`) still rendered Jetstream's stock placeholder SVG wordmark in the app sidebar/nav and other authenticated-app surfaces, even though the login page's own `authentication-card-logo.blade.php` and the marketing welcome page already used the real logo. These two components render on every authenticated page via Jetstream's own layout, so the placeholder was visible constantly, not just on one screen. Swapped to the real logo file, matching the asset path already used elsewhere in this repo. |
| 0.3.0 | 2026-08-03 | Sakhile Bhayi | Real-execution verification pass — this platform was actually installed and run against real PHP/PostgreSQL for the first time (composer install required PHP 8.3, since `vimeo/psalm` 6.5.0 requires `php ~8.1.17 \|\| ~8.2.4 \|\| ~8.3.0 \|\| ~8.4.0`, incompatible with this Mac's default PHP 8.5). `php artisan migrate` surfaced several real, previously-uncaught ordering and schema bugs: (1) `create_roles_table`/`create_permissions_table` (foreign-keying to `teams`) ran before the shared `create_teams_table` migration, which was timestamped hours later — retimestamped the six shared Jetstream migrations to run immediately after the core `users`/`jobs` tables. (2) `create_geofences_table` and several later migrations (fuel management, IoT/forecasting, routes, AI agents) all foreign-key to `mine_areas`, but `create_mine_areas_table` was dated nearly a month later (2026-02-12) than every migration that depends on it — moved it to run right after `teams`, resolving every one of those dependencies in a single retimestamp. (3) `create_integrations_table` ran after `create_machines_table`, which foreign-keys to it — reordered. (4) `2026_02_19_..._backfill_allocation_mine_area_id` (numbered 000001) ran before `..._add_mine_area_id_to_monthly_allocations` (numbered 000004), the migration that actually adds the column it backfills — retimestamped to run after. (5) `add_performance_indexes` hardcoded a `sqlite_master` query to check for existing indexes, which only works on SQLite and threw `relation "sqlite_master" does not exist` on Postgres — replaced with Laravel's driver-agnostic `Schema::hasIndex()`. (6) That same migration referenced three columns that don't exist under the names it expected — `alerts.alert_level` (actual: `priority`), `geofences.fence_type` (actual: `type`), `reports.report_type` (actual: `type`) — fixed to the real column names, and removed an index on `mine_areas.area_type`, a column that was never created anywhere. (7) `machines.mine_area_id` was assumed to exist by both `MineArea::machines()` (a real `hasMany` relationship) and `2026_02_19_..._make_machine_mine_area_id_not_nullable`, but no migration ever created it — added `2026_02_19_000006_add_mine_area_id_to_machines_table.php` to add it. After all of these fixes, `php artisan migrate` ran clean end-to-end (60+ migrations). `php artisan test` then surfaced two more real bugs: a zip-bomb-rejection test (`FileUploadServiceZipTest`) legitimately needs more than PHP's default 128M `memory_limit` to build its 60MB test fixture — added an `<ini name="memory_limit" value="512M"/>` override to `phpunit.xml`; and `MineAreaTenantScopingTest::test_user_cannot_view_another_teams_mine_area` failed with 404 instead of the expected 403 — traced to `App\Models\MineArea` having picked up the `HasTeamFilters` trait (which auto-scopes every query to the current user's team), directly contradicting that same test's own docblock ("MineArea does not use the HasTeamFilters global scope... enforced explicitly in mount()") and this wiki's own 0.1.1 entry below, which explicitly documented that MineArea was the one team-owned model *without* that trait. Every real call site already filters `MineArea` queries explicitly by `team_id` or `->forTeam()`, confirming the trait's presence was an unintended regression, not a design change — removed it, restoring the explicit `abort(403)` in `MineAreaDetail::mount()` as the actual enforcement path. Final test suite: 18/18 passed, 0 failed. Applied the Dot.Brain adr/ADR-0013 idempotent guard to this platform's six shared Jetstream-core migrations (users/password_reset_tokens/sessions, two-factor columns, personal_access_tokens, teams, team_user, team_invitations), each now wrapped in `Schema::hasTable`/`hasColumn` checks so this platform's copies are safe to co-execute with any other Dot platform against the same shared `infodot` database. Re-verified against a fresh, empty database after guarding: migrate clean, same 18/18 passed test result, confirming the guard changes no observable behavior. |
| 0.1.0 | 2026-08-01 | Mining Platform Lead | Initial wiki: derived from the actual `mines` Laravel codebase (models, events, README), cross-referenced against Dot.Brain's platforms/dot-mines.md for ecosystem framing and gap analysis |
| 0.2.0 | 2026-08-02 | Sakhile Bhayi | Redesigned `resources/views/welcome.blade.php`'s marketing surface: the placeholder gradient-and-SVG-icon brand mark in the nav and footer is now the real `public/images/logo.png` lockup; the hero and CTA sections' abstract gradient backgrounds are now real, licensed Unsplash photography (excavators at a mining site, and open-pit haul trucks — both hotlinked via Unsplash's CDN, photographers credited inline as HTML comments) layered under a dark gradient overlay tuned for WCAG-adequate text contrast against the photo. Verified visually via a standalone Tailwind-CDN render of the same markup before committing, not just reviewed as code. |
| 0.1.1 | 2026-08-01 | Platform loop pass | Real Dot.Mines logo/favicons wired into the auth-card mark, sidebar brand mark, and all `<head>` favicon links; in-app "Mines" text branding aligned to "Dot.Mines" (titles, APP_NAME, composer.json); removed an unreferenced dead Jetstream default marketing view (`resources/views/components/welcome.blade.php`); added a missing `tests/TestCase.php` (existing PHPUnit tests referenced it but it did not exist in the repo) plus new Feature tests for the dashboard, the fleet list, and `MineArea` cross-team access; flagged (not fixed) that `MineArea` is the one team-owned model without the `HasTeamFilters` global scope other models use — the live `/mine-areas/{id}` route is still protected by an explicit `abort(403)` check in `MineAreaDetail::mount()`, but `ReportController::view2()` has an unscoped `MineArea::all()` fallback branch that is unreachable under normal `ensure_team` middleware flow and should be tightened defensively |
