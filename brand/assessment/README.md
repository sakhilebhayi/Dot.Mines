# Brand experience baseline: Dot.Mines (mining-fleet demonstration, seeded demo mine only)

> **Advisory. Written by an AI reviewer. No profile is ratified.** This is a first baseline from the Brand Feeling Engine, run on Dot.Mines, using the seeded demo mine only. Nothing here blocks a merge or a deploy, nobody at the company has reviewed it yet, and the brand profile it is measured against is a draft that the owner has not ratified. Scores are honest and low on purpose: unmeasured checks count as zero and are listed, and a product cannot be `release_ready` without observed or customer evidence that does not exist yet.

| | |
|---|---|
| **Evidenced score** | **21.91 / 100** |
| **Ceiling** (most this evidence could earn) | 62.20 |
| **Attainment** (evidenced / ceiling) | 35.22 % |
| **State** | `not_ready` |
| **Confidence** | Low (Evidence disagrees by more than 1 level(s) on 3 dimension(s).) |
| Blockers | 1 |

Read the three numbers together. The ceiling is 62.20 because only automated checks and expert assessment exist; the other 37.8 points can only be earned from observed or customer evidence. An AI-only expert assessment counts at most half. Every check that was not run counts as zero and is listed below.

**Blockers recorded by the engine:**
- WCAG A failure of criterion 3.1.1. (`d8.labels_alt_lang.86191558d9e3`, resources/views/reports/pdf/generated.blade.php)

(That blocker is the report PDF template, which has no `lang` attribute. It is real but narrow.)

| Dimension | Status | Points earned / weight | Ceiling (this evidence) | AI expert level (0-4) |
|---|---|---|---|---|
| Emotional clarity and positioning | partially evidenced | 1.53 / 8 | 4.00 | 3 |
| Brand distinctiveness | partially evidenced | 1.01 / 7 | 4.20 | 2 |
| Usability and cognitive load | partially evidenced | 2.71 / 12 | 7.20 | 2 |
| Visual and interaction craftsmanship | partially evidenced | 1.91 / 9 | 7.20 | 3 |
| Functional completeness | partially evidenced | 6.81 / 10 | 9.00 | - |
| Trust and transparency | partially evidenced | 2.22 / 10 | 7.00 | 2 |
| Perceived and demonstrated value | not measured | 0.00 / 8 | 4.00 | - |
| Accessibility and inclusion | partially evidenced | 3.32 / 12 | 8.40 | 1 |
| Cross-platform consistency | partially evidenced | 0.81 / 6 | 4.80 | - |
| Reliability and recovery from errors | partially evidenced | 1.60 / 8 | 6.40 | - |
| Retention through continuing value | not measured | 0.00 / 5 | 0.00 | - |
| Measurable customer outcomes | not measured | 0.00 / 5 | 0.00 | - |

Dimensions 11 and 12 (retention through continuing value, measurable customer outcomes) are **not measured**: they accept only real observed or customer results, and none exist. Perceived and demonstrated value (dimension 7) has no automated or expert evidence in this run.

## Safety first

The profile's domain overrides are copied into the score report: safety, security and accessibility findings outrank any brand preference, and a calm tone must never soften a safety alert. In the seeded scenario the alert path behaves as that rule wants (the active-alert count, a red recent-alert row with an Acknowledge action and a bell badge all appear on the dashboard). **This baseline did not test real alarm delivery or a real caution code**; the alert is a synthetic fixture.

## Main gaps found

1. **Form labels.** 202 form controls sit beside a visible `<label>` that has no `for`/`id` link (a sample was checked by hand); 20 click handlers sit on anchors that cannot take focus; the report PDF has no `lang` (WCAG 1.3.1, 2.1.1, 3.1.1).
2. **Contrast in the rendered app.** White on red badges are 3.8:1, light text on green 1.9:1, placeholders 3.2 to 3.8:1. (The static contrast check passed 9 of 9 pairs because it only reads one stylesheet; the rendered detector caught the real ones.)
3. **Claims versus state.** The welcome page says Dot.Mines "reads your OEM telemetry directly" and the Integrations page labels 16 manufacturers "Available", while the capabilities page and the repository's own wiki say manufacturer integrations are in development and none has ever completed a real connection.
4. **Mixed signals in the seeded scenario.** The dashboard says "Active now 2, 100% operational" beside "No telemetry 2" and "Stale"; on Fleet, "Needs attention" lists the same two machines as "Top performers". Whether this is a seeding artefact or a real status-versus-telemetry gap was not resolved.
5. **Distinctiveness.** The shell is a dark sidebar with card grids; no DESIGN.md records an identity.

What is already good: no horizontal overflow on any of 27 pages at 375, 768 or 1280 px (measured), a phone navigation, full reduced-motion coverage, data freshness shown on the dashboard and production page ("Stale", "Sync delayed"), a wiki that records unverified integrations plainly, and 1,069 passing tests.

## Findings that are probably false positives (do not chase these)

- `d5.dead_links` (1): `route('contact')` inside `@if (Route::has('contact'))`; the engine does not read the guard.
- `d6.privacy_terms_present`: privacy, terms and cookie pages exist and render; they are registered by Jetstream from `resources/markdown`.
- `d8.token_pair_contrast` reads 1.00 from one stylesheet; it is not evidence that contrast is fine.
- Detector checks read 0.00 for `d2` because the engine counts hits against a fixed ceiling of 10.

## How this baseline was produced

- **Engine.** Dot.Brain brand engine 0.2.0 (Dot.Brain commit `2019312`), commands `analyze`, `evidence assemble`, `score`, `plan`. The engine reads files and reports, runs no tool itself and calls no network. Rubric BXQS 1.0.0 (sha256 `f5a4f8cb49b8`, **proposed, not ratified**). Profile `dot-ecosystem@0.1.0` (**draft, not ratified**): no product profile exists for Dot.Mines yet, so the ecosystem profile is used. Domain override in force: a calm tone must never soften a safety alert, and safety codes and alarms outrank every brand rule.
- **Checkout.** Commit `b9f73a2` of `main`, analysed untouched.
- **Run locally, not against any live host.** The **seeded demo mine only** ("Bell E2E Mine": two machines, one pit, 90 days of synthetic production, one synthetic caution-code alert labelled as a fixture, a connected-integration row labelled as a mock). No real mine, machine or Bell data was used and the mock fleet API was not needed to render pages. Laravel's test kernel, SQLite `:memory:` only, a `.env` with only the app key and sqlite lines, `PAO_DISABLE=1`, no MySQL or Postgres, no outbound requests. The repository's own PHPUnit suite was also run on SQLite `:memory:` (with placeholder local values for the Reverb settings the tests expect) and its JUnit file given to the engine: 1,069 tests, all passed.
- **Rendered-page evidence.** 27 pages (public pages, and the signed-in dashboard, fleet, machine detail, map, production, alerts, maintenance, fuel, mine areas, operators, feed, reports, integrations, settings, team, billing, geofences) were rendered, served from a local static server and opened in headless Chrome. 78 external references (Google Fonts, avatar service) were removed so nothing leaves the machine, and Livewire's JavaScript was served from the vendor folder. The map shows no tiles because tile servers were not contacted. The design detector ran on the source (15 hits) and on the snapshots (131 hits; 4 contrast hits with an unresolved background dropped, 127 kept). Responsive overflow was measured with Playwright at 375, 768 and 1280 px on all 27 pages: **no page overflows horizontally at any width** (not scored; the engine has no adapter for it).
- **Not installed, so not measured:** axe-core and Lighthouse (no global tool was installed for this review). No assistive-technology test, no user session and no customer feedback exist, so dimensions 11 and 12 and every observed or customer metric are **not measured**.
- **Expert assessment.** Written by an **AI reviewer** (assessor `ai-reviewer-fresh-2026-10-23`, kind `ai`) that did not build this product in this run. It is AI-only expert evidence: the rubric counts it at no more than half. `built_by` is declared as `prior-ai-sessions-and-owner`; independence is declared, not proven. No user was observed and nobody was asked how they feel.

## What is not measured and why

| Automated check | Why it is not measured |
|---|---|
| `d1.product_md_consistency` | no built-in check yet (needs a browser run, scripted flows, or a check nobody has written) |
| `d1.purpose_statement_present` | no built-in check yet (needs a browser run, scripted flows, or a check nobody has written) |
| `d10.error_copy_lint` | no built-in check yet (needs a browser run, scripted flows, or a check nobody has written) |
| `d10.error_path_tests` | the repository does not tag tests as error-path tests, so the test adapter found none. |
| `d2.token_uniqueness` | no built-in check yet (needs a browser run, scripted flows, or a check nobody has written) |
| `d3.primary_action_per_screen` | no built-in check yet (needs a browser run, scripted flows, or a check nobody has written) |
| `d3.task_flow_step_counts` | no built-in check yet (needs a browser run, scripted flows, or a check nobody has written) |
| `d4.responsive_overflow` | the engine has no built-in check or adapter for it. The reviewer measured horizontal overflow separately (see the rendered-page paragraph above) but it is not scored. |
| `d4.spacing_type_scale_conformance` | the check ran and said it could not judge |
| `d6.consent_handling` | no built-in check yet (needs a browser run, scripted flows, or a check nobody has written) |
| `d6.honest_states` | the check ran and said it could not judge |
| `d7.empty_state_guidance` | no built-in check yet (needs a browser run, scripted flows, or a check nobody has written) |
| `d7.time_to_first_result` | no built-in check yet (needs a browser run, scripted flows, or a check nobody has written) |
| `d7.value_above_fold` | no built-in check yet (needs a browser run, scripted flows, or a check nobody has written) |
| `d8.axe_violations` | axe-core is not installed on the review machine and no global tool was installed, so no axe report exists. Not measured. |
| `d9.principle_diff` | no built-in check yet (needs a browser run, scripted flows, or a check nobody has written) |
| `d9.shared_components` | no built-in check yet (needs a browser run, scripted flows, or a check nobody has written) |

## Unknowns

- No product profile exists, so audience, purpose and intended feelings for Dot.Mines are not recorded; the ecosystem profile is a draft with open questions that block ratification.
- Real operators, real machines, real Bell responses and real alarm handling were out of scope; nothing is known about how operators read the screens on site, in daylight, or with gloves.

## Files in this folder

| File | What it is |
|---|---|
| `2026-10-23-b9f73a2.json` | the BXQS score report (`score` output) |
| `2026-10-23-b9f73a2.evidence.json` | the evidence file the score was computed from (`evidence assemble` output; includes the AI assessments) |
| `2026-10-23-b9f73a2.analysis.json` | the analysis (`analyze` output); it is the baseline that `brand-engine verify --before` needs |
| `2026-10-23-b9f73a2.plan.md` | the ordered task list (`plan` output). Tasks that point at `rendered-snapshots/...` refer to pages rendered for this review, not files in this repository |

Re-score: `node services/brand-engine/src/cli.js score --evidence 2026-10-23-b9f73a2.evidence.json --profile dot-ecosystem --built-by prior-ai-sessions-and-owner` from a Dot.Brain checkout at or after the commit above.
