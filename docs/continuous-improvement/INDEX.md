# Continuous Improvement Framework — Index

> **Purpose**: Living engineering governance documentation for the Mines Fleet Management Platform.
> Every document in this directory is updated automatically as the platform evolves.

---

## Core Documents

| Document | Purpose | Last Reviewed |
|---|---|---|
| [ROADMAP.md](ROADMAP.md) | Long-term product and engineering roadmap | 2026-07-02 |
| [PLATFORM_SCORECARD.md](PLATFORM_SCORECARD.md) | Enterprise maturity scores per subsystem | 2026-07-02 |
| [CHANGELOG.md](CHANGELOG.md) | Record of every significant engineering change | 2026-07-02 |
| [KNOWN_ISSUES.md](KNOWN_ISSUES.md) | Active bugs, workarounds, status | 2026-07-02 |
| [RELEASE_CHECKLIST.md](RELEASE_CHECKLIST.md) | Gate checklist for every production release | 2026-07-02 |
| [TESTING_STRATEGY.md](TESTING_STRATEGY.md) | Test coverage strategy and standards | 2026-07-02 |
| [CODE_STANDARDS.md](CODE_STANDARDS.md) | Naming, architecture, and documentation conventions | 2026-07-02 |

## Improvement Tracks

| Document | Domain |
|---|---|
| [TECHNICAL_DEBT.md](TECHNICAL_DEBT.md) | Refactoring, legacy code, cleanup |
| [SECURITY_IMPROVEMENTS.md](SECURITY_IMPROVEMENTS.md) | Vulnerabilities, hardening, OWASP |
| [PERFORMANCE_IMPROVEMENTS.md](PERFORMANCE_IMPROVEMENTS.md) | Queries, caching, queue, frontend |
| [DATABASE_IMPROVEMENTS.md](DATABASE_IMPROVEMENTS.md) | Schema, indexes, archiving, partitioning |
| [API_IMPROVEMENTS.md](API_IMPROVEMENTS.md) | Endpoints, consistency, documentation |
| [INTEGRATION_IMPROVEMENTS.md](INTEGRATION_IMPROVEMENTS.md) | OEM adapters, telemetry pipeline |
| [UI_UX_IMPROVEMENTS.md](UI_UX_IMPROVEMENTS.md) | Accessibility, design consistency, UX |
| [AI_IMPROVEMENTS.md](AI_IMPROVEMENTS.md) | Agents, prompts, orchestration, cost |
| [OBSERVABILITY.md](OBSERVABILITY.md) | Logging, metrics, tracing, alerting |

---

## How This Framework Works

1. **Every development session** appends an entry to [CHANGELOG.md](CHANGELOG.md).
2. **Every discovered issue** is logged in [KNOWN_ISSUES.md](KNOWN_ISSUES.md) or the relevant improvement track.
3. **Before any release**, the [RELEASE_CHECKLIST.md](RELEASE_CHECKLIST.md) must be completed.
4. **After every sprint**, [PLATFORM_SCORECARD.md](PLATFORM_SCORECARD.md) scores are reviewed.
5. **Technical debt** found during any session is logged in [TECHNICAL_DEBT.md](TECHNICAL_DEBT.md) even if not immediately resolved.

## Automation Triggers

| Trigger | Documents Updated |
|---|---|
| Feature complete | CHANGELOG.md, PLATFORM_SCORECARD.md |
| Bug fixed | KNOWN_ISSUES.md, CHANGELOG.md |
| Security patch | SECURITY_IMPROVEMENTS.md, CHANGELOG.md |
| New OEM integration | INTEGRATION_IMPROVEMENTS.md, CHANGELOG.md |
| Performance optimisation | PERFORMANCE_IMPROVEMENTS.md, CHANGELOG.md |
| Production release | RELEASE_CHECKLIST.md, CHANGELOG.md |
| Architecture change | TECHNICAL_DEBT.md, CODE_STANDARDS.md, CHANGELOG.md |
