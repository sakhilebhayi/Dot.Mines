# AI Platform Improvements

> Track prompt engineering, agent quality, orchestration, and cost optimisation.

---

## Current AI Score: 72/100

---

## Agent Coverage

The platform has 50+ agents covering all operational domains. Agent health is tracked by `agent-performance-auditor` and `ai-governance-drift-agent`.

---

## Open Improvements

### AI-001 — AI Recommendations Use Stale Telemetry Context
- **Finding**: `AIOptimizationDashboard` generates recommendations from `AIRecommendation` model records, not from live telemetry. Recommendations may be hours old.
- **Fix**: Pass current `MachineTelemetryService` snapshot as context when generating new recommendations.
- **Effort**: 3 days
- **Status**: 🟡 Planned

### AI-002 — No Prompt Versioning
- **Finding**: Agent SKILL.md files are edited directly without version control for the prompts themselves.
- **Risk**: Prompt regressions are invisible. A broken prompt silently degrades AI quality.
- **Fix**: Add a `version` field to each SKILL.md; track changes in git with meaningful commit messages.
- **Effort**: 1 day (process change)
- **Status**: 🔴 Open

### AI-003 — No Token Usage Tracking
- **Finding**: No metrics on tokens consumed per agent invocation, session, or feature.
- **Risk**: Runaway costs if agents are invoked unexpectedly often.
- **Fix**: Add token count to `AgentPerformanceLog`; build a Pulse metric for daily token usage.
- **Effort**: 2 days
- **Status**: 🔵 Backlog

### AI-004 — `enterprise-decision-intelligence` Skill Not Consistently Applied
- **Finding**: High-stakes decisions (machine status changes, alert creation, dispatch recommendations) do not always pass through the 10-step decision validation protocol.
- **Fix**: Add a gate in `BellIso15143Service` alert creation and `MachineStatusChanged` events that records a Decision Confidence Score.
- **Effort**: 3 days
- **Status**: 🔵 Backlog

### AI-005 — No A/B Testing for AI Recommendations
- **Finding**: No way to compare recommendation quality across prompt versions.
- **Fix**: Add `variant` column to `AIRecommendation`; record which prompt generated which recommendation.
- **Effort**: 2 days
- **Status**: 🔵 Backlog

---

## Agent Quality Standards

Every agent skill file (`SKILL.md`) should include:
- Clear trigger conditions
- Explicit output format
- Example inputs and outputs
- Version number
- Last-reviewed date

---

## AI Data Flow

```
MachineTelemetryService (live snapshot)
    ↓
AIContext builder (aggregates telemetry + history + targets)
    ↓
Agent invocation (via SKILL.md prompt)
    ↓
AgentPerformanceLog (decision recorded)
    ↓
AIRecommendation / AIInsight (stored result)
    ↓
UI (AIOptimizationDashboard, MaintenanceDashboard)
```

The key gap today: step 1 (live snapshot) is not always fed into step 2.
