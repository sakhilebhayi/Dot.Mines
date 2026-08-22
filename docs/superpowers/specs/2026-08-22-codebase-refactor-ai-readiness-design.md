# Codebase Refactor, Security & AI-Readiness Program

**Date:** 2026-08-22
**Status:** Approved scope — full type-debt burndown to zero; app-wide Actions pattern.
**Prime directive (user's Critical Rule):** preserve behavior; no refactor without a concrete
gain in security, performance, maintainability, testability, reliability, AI readability, or
extensibility. Clean code stays untouched.

## Baseline (recorded 2026-08-22)

- 587 tests green (1,560 assertions); pint clean; CI: pint --test, phpstan/psalm, PHPUnit,
  semgrep, gitleaks, quality gate.
- **phpstan-baseline.neon: 1,898 suppressed issues. psalm-baseline.xml: 259 files.** This is
  the debt ledger the program eliminates.
- App: 262 PHP files / 42k lines; views 19k lines; JS 5.1k lines; 78 migrations; 134 test files.

## Findings inventory (DISCOVER phase)

**Healthy — explicitly out of scope (Critical Rule):** zero TODO markers; `{!!` confined to
vendor mail + static terms/policy; SQL-raw surface verified parameterized; 4 justified
`withoutGlobalScopes`; CSP/nonce/security headers/CSRF from prior programs; tenancy global
scopes with isolation tests (billing, sync, broadcast); overlay/design contracts enforced by
tests.

**Debt:**
1. Type debt: the two analyzer baselines (above).
2. God classes: IntegrationService 1,177 / BellService 951 / FuelManagement 764 / Fleet 707 /
   PaystackService 698 / MineAreaDetail 660 / MaintenanceDashboard 532; blades to 1,675 lines
   (fleet-movement-replay), maintenance-dashboard 1,021, mine-area-detail 1,015.
3. Realtime JS spans three eras. Verified during R0: livewire-realtime.js IS live (imported
   by app.js; drives ReverbService.init via the RealtimeUpdates trait's realtime:init event —
   alerts→toasts, per-machine locations, connection monitor). Genuinely dead: LivewireEcho
   (zero consumers) and the dormant 'realtime:team-locations' listener (nothing ever
   dispatched it; superseded by the direct live-map wire from PR #123).
4. Zero Form Requests; validation inline across 27 controllers (Livewire rules() idiomatic and
   stays; API controllers audited).
5. 15/60 Livewire components carry no explicit authz keyword — verify coverage-by-middleware
   per component; add explicit checks wherever a write action exists.
6. Hygiene: 12 `env()` calls in app code; 18 route closures; GenerateRoadsPathCoordinates
   (768-line command) to review for dead weight.

## Conventions this program introduces

- **Actions pattern (app/Actions/{Domain}/):** one class, one write operation, `execute()`
  with typed input/output. Controllers and Livewire components orchestrate; Actions own
  business rules; services own external integrations and cross-cutting infrastructure.
  Recorded in `.ai/rules` so future work follows it.
- **Analyzer ratchet:** a CI-enforced test asserting the baseline files only ever shrink
  (line-count / entry-count monotonic decrease) until they are deleted at zero.
- **No new abstractions beyond these** — no repositories over Eloquent, no interfaces without
  a second implementation.

## Slices (each: branch → PR → full gates → merge on green → deploy → tracker update)

- **R0 — Ratchet + hygiene.** Baseline-ratchet test; env()→config (Sentry bootstrap,
  TrustProxies, ArchiveOldMetricsJob); delete dead realtime JS (LivewireEcho and the
  dormant team-locations listener + its orphaned ReverbService method; livewire-realtime.js
  itself stays — it is live); route-closure audit;
  `.ai/rules` entries for Actions + ratchet.
- **R1 — Security & tenancy verification.** Per-component authz verdict for the 15 unmarked
  Livewire components (explicit checks added to any with writes); API controller validation
  audit (Form Requests introduced where validation is inline and non-trivial); upload pipeline
  re-verification. Deliverable is mostly tests.
- **R2 — Integration layer restructure.** IntegrationService + BellService +
  BaseManufacturerService split along §9 boundaries: credential/auth concern, HTTP transport
  (timeout/retry/rate-limit), response normalization, persistence, event dispatch. Existing
  Bell test suite freezes behavior; add cases where seams expose gaps.
- **R3 — Livewire + Actions restructure.** FuelManagement, Fleet, MineAreaDetail,
  MaintenanceDashboard, ProductionDashboard: write operations move to Actions; queries to
  computed properties/scoped services; N+1 audit with query-count assertions. PaystackService
  webhook/checkout/plan concerns split; billing writes become Actions.
- **R4 — Type-debt burndown I:** app/Models, app/Traits, app/Events, app/Jobs to zero
  baseline entries.
- **R5 — Type-debt burndown II:** app/Services to zero.
- **R6 — Type-debt burndown III:** app/Livewire, app/Http, remainder to zero; **delete both
  baseline files**; CI runs analyzers bare.
- **R7 — Frontend quality.** fleet-movement-replay blade (1,675 lines) decomposed into
  partials/components; blade duplication sweep; dead CSS/JS; chart/map utility consolidation.
- **R8 — Performance verification.** Query-count assertions for dashboard, fleet, live-map,
  production, reports pages; before/after query counts + response times recorded in the PR;
  fixes for any N+1/unbounded query found. No unverified performance claims (§21).
- **R9 — Second independent audit (§25).** Fresh pass over the diff of the whole program:
  missed files, new N+1s, tenant gaps, dead code, architecture consistency; fix and re-verify.

## Testing & safety rails

- Suite must stay green after every slice; new tests accompany every extraction (behavior
  freeze first, then move).
- The ratchet test makes type-debt regression a CI failure from R0 onward.
- Performance work follows measurement (§21); security work follows the existing
  tenant-isolation test pattern.
- Deploys after each slice with prod smoke checks (/ready, key pages), matching house practice.

## Out of scope

Rewrites of working features; new architectural patterns beyond Actions; caching changes to
realtime telemetry paths (freshness > caching, §13); moving Livewire validation out of
rules(); vendor mail templates; the static marketing/docs pages.
