# Dot.Mines: AI Recommendation Approval Gate (First Real Level 2 Process)

## Context

Dot.Brain's ecosystem-wide Owner Independence program classifies every real process on every Dot platform into Level 1 (Autonomous) / Level 2 (Escalate) / Level 3 (Human Control), per `brain.autonomy.md` §2. Dot.Mines' audit (`Dot.Brain/platforms/dot-mines.md`, 2026-08-08) found real Level 1 automation (AI recommendation generation, monitoring/alert jobs) but no real Level 2 process anywhere — "AI recommendation implementation" was classified Level 3 (fully manual, ad hoc) because nothing in the codebase presents a structured Context → Evidence → Risk → Recommendation → Proposed Action proposal for approval.

Direct inspection of the real codebase found this gap is narrower than a from-scratch build. `AIRecommendation` (`app/Models/AIRecommendation.php`) already carries most of the required shape — `data` (Context), `confidence_score`/`impact_analysis` (Evidence), `estimated_savings`/`estimated_efficiency_gain`/`priority` (Risk), `description` (Recommendation) — created centrally by `AIOptimizationService::runComprehensiveAnalysis()` and `::getRecommendationsForCategory()` (the only two creation sites, fed by 6 `app/Services/AI/*Agent.php` classes). Approval already flows through a policy-gated (`AIRecommendationPolicy`), confirm-dialog-backed Livewire component (`AIOptimizationDashboard`).

Three real gaps remain, found by direct inspection:

1. **No "Proposed Action" field.** `description` blends diagnosis and instruction; there is no field distinctly answering "what should the operator actually do."
2. **No decision log, and rejections capture no reason.** `rejectRecommendation()` in `AIOptimizationDashboard` sets `status = 'rejected'` and nothing else — no reason, no record of who rejected what evidence and why. A second model, `AiRecommendationAction` (`app/Models/AiRecommendationAction.php`, table `ai_recommendation_actions`), already has the right columns (`status`, `actioned_by`, `actioned_at`, `reject_reason`, `performance_impact`) but is used by zero code in the entire application — confirmed via `grep -rl "AiRecommendationAction" app` returning only the model file itself.
3. **Approval is not actually gated to approval authority.** `AIRecommendationPolicy::update()` lets any user on the same team act, falling through to a permission check only as a secondary path — there is no real requirement that the person clicking "Implement" holds real approval authority.

## Goal

Close all three gaps so Dot.Mines has a real, auditable Level 2 process: a recommendation presents Context → Evidence → Risk → Recommendation → Proposed Action, only an owner/admin can approve or reject it, and every decision — approve or reject, with a reason — is written to a real decision log.

## Changes

### 1. `proposed_action` column

Add a nullable `proposed_action` TEXT column to `ai_recommendations` via migration. `AIOptimizationService`'s two creation call sites populate it as `$rec['proposed_action'] ?? $rec['description']` — backward-compatible, so the 5 agents not updated in this pass keep working with `description` doing double duty, while any agent that does supply `proposed_action` gets it stored distinctly. `RouteAdvisorAgent::analyze()` is updated as the one worked example: `description` stays the diagnosis ("Route can be optimized to save X minutes and Y liters of fuel"), `proposed_action` becomes the concrete instruction ("Reroute {route name} via the optimized path identified by the route advisor to capture the savings above"). The other 5 agents (`AnomalyDetectorAgent`, `FuelPredictorAgent`, `FleetOptimizerAgent`, `CostAnalyzerAgent`, `MaintenancePredictorAgent`) adopting the same pattern is explicitly out of scope here — flagged as follow-up work, not silently expanded into this task.

### 2. Decision log via `AiRecommendationAction`

Add an `ai_recommendation_id` foreign key (nullable, since the table's existing `recommendation_hash`/`recommendation` JSON columns stay as-is for any other caller that might exist outside this codebase's own app/ tree — this migration is additive, not destructive) to `ai_recommendation_actions` via migration. `AIOptimizationDashboard::implementRecommendation()` and `::rejectRecommendation()` each create one `AiRecommendationAction` row: `team_id`, `ai_recommendation_id`, `status` (`implemented`/`rejected`), `actioned_by` (the approving user's ID), `actioned_at` (now), and — for rejections — `reject_reason`, which becomes **required**: the Livewire confirm dialog's reject path gets a required text input, and `rejectRecommendation()` refuses to proceed with an empty reason (mirrors the existing `showRecommendationConfirm` confirm-then-act pattern already in the component, adding one required field to the reject branch only). `performance_impact` stays nullable — auto-populating it needs a real outcome-measurement job that doesn't exist yet; this spec does not build one.

### 3. Tighten `AIRecommendationPolicy::update()`

Remove the "any user on the same team may act" fallback. The method keeps exactly two paths to authorization: `hasRole('owner')`/`hasRole('admin')`/`hasRole('administrator')`, or the existing `hasPermission('update_recommendations')` check — both already present in the policy today, only the same-team fallback is removed.

## Testing

Existing Laravel test conventions in this repo (`php artisan test`) — feature tests for `AIOptimizationDashboard::implementRecommendation()`/`rejectRecommendation()` covering: a non-owner/admin without `update_recommendations` is denied (403/authorization exception); an owner/admin can implement, and it writes an `AiRecommendationAction` row with `status = 'implemented'`; an owner/admin rejecting without a reason is blocked; an owner/admin rejecting with a reason writes an `AiRecommendationAction` row with `status = 'rejected'` and the reason stored; `proposed_action` round-trips through `AIOptimizationService`'s creation path (falls back to `description` when an agent doesn't supply it, stores the distinct value when `RouteAdvisorAgent` does).

## Explicitly out of scope

- Updating the 5 non-`RouteAdvisorAgent` agent classes to supply their own `proposed_action` (flagged as follow-up).
- Auto-populating `performance_impact` (needs a future outcome-measurement job).
- Any change to `AIRecommendation` generation itself, or the monitoring/alert Level 1 processes — both already real and untouched by this spec.
- Registering this change in Dot.Brain's `platforms/dot-mines.md` or `platforms/autonomy-signals.json` — that's a separate, future re-audit pass once this ships, not part of building the feature itself.
