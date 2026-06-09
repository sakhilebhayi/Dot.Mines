---
name: enterprise-decision-intelligence
description: >
  Platform-wide executive decision validation meta-skill. Activate for ANY high-stakes agent
  decision before it is executed. Applies the 10-step decision validation protocol, calculates
  a Decision Confidence Score (0–100), enforces the autonomous/approval/reject thresholds,
  runs delusion detection (evidence sufficiency check), logs every decision to AgentPerformanceLog,
  and blocks execution when evidence is insufficient. Every agent inherits this skill.
argument-hint: 'Describe the decision or recommendation that needs validation'
esm-layer: intelligence
esm-role: meta — applied to all agents
---

# Enterprise Decision Intelligence

## Purpose

This is the **executive nervous system** of the Mines Platform. It is a meta-skill — inherited
by every agent — that must be applied before any significant recommendation is acted upon
autonomously. It prevents AI hallucination, delusion, and overconfident decisions from causing
real-world harm in a safety-critical mining environment.

---

## Decision Validation Protocol

Before any agent makes a recommendation or autonomous action, execute all 10 steps:

```
Step 1  → Validate data quality (trust score check via DataTrustService)
Step 2  → Check compliance impact (does this decision create a compliance obligation?)
Step 3  → Check safety impact (could this harm a person or increase injury risk?)
Step 4  → Check financial impact (revenue/cost implication > threshold?)
Step 5  → Check production impact (does this affect BCM/tons targets?)
Step 6  → Check ESG impact (does this increase carbon emissions?)
Step 7  → Check maintenance impact (does this affect machine health or availability?)
Step 8  → Check operational impact (does this affect dispatch, shifts, or areas?)
Step 9  → Calculate Decision Confidence Score (0–100)
Step 10 → Log decision to AgentPerformanceLog with full evidence trail
```

---

## Decision Confidence Score

```
Score = weighted average of:

  Data quality gate      (25%)  — based on DataTrustService trust score
  Evidence completeness  (25%)  — are all required data points present?
  Cross-validation       (20%)  — do multiple sources agree?
  Historical accuracy    (15%)  — what is this agent's prior accuracy for this decision type?
  Recency of data        (15%)  — is the data fresh enough to support this decision?

Final Score: 0–100
```

---

## Autonomous Execution Thresholds

```
Score 81–100  → AUTONOMOUS EXECUTION
               Agent may act without human approval.
               Log to AgentPerformanceLog. Notify stakeholders of outcome.

Score 61–80   → SAFE RECOMMENDATION
               Present recommendation to responsible user for review.
               Pre-fill all required fields. One-click approval.
               Agent does NOT execute autonomously.

Score 41–60   → HUMAN APPROVAL REQUIRED
               Escalate to team manager.
               Provide full evidence summary and alternatives.
               Highlight risks and contradictions.
               Block execution until approved.

Score 0–40    → DECISION REJECTED
               Do not present to users.
               Log rejection reason to AgentPerformanceLog.
               Trigger data quality investigation.
               Alert platform guardian.
```

---

## Delusion Detection Layer

Before finalising any confidence score, the agent must answer all 6 questions.
If any answer is "insufficient" or "unknown", the score is automatically capped at 60.

```
1. What evidence supports this decision?
   → List every data source used (model, field, timestamp)
   → If evidence list is empty → cap score at 40

2. What data sources were used?
   → Confirm each source has a trust score > 60 (DataTrustService)
   → If any source trust score < 60 → reduce confidence by 15 points per low-trust source

3. What assumptions were made?
   → List every assumption explicitly
   → If > 3 assumptions → add "HIGH ASSUMPTION RISK" caveat to recommendation

4. What risks exist?
   → Identify: safety risks, financial risks, compliance risks, operational risks
   → If safety risk is present → require human approval regardless of score

5. What contradicts this decision?
   → Actively search for contradicting data (not just confirming data)
   → If strong contradiction found → reduce score by 20 points

6. What is the confidence level and why?
   → State the confidence score and each factor contributing to it
   → If agent cannot explain its own confidence → score = 0
```

---

## Implementation Pattern — Using in an Agent

```php
use App\Services\DataTrustService;
use App\Models\AgentPerformanceLog;

class MaintenancePredictorAgent
{
    public function predictFailure(Machine $machine): array
    {
        // Step 1 — Data quality gate
        $trustService = app(DataTrustService::class);
        $trustScore   = $trustService->scoreMachine($machine)['trust_score'];

        if ($trustScore < 40) {
            return $this->rejectDecision(
                agent: 'maintenance-predictor',
                reason: "Data trust score too low: {$trustScore}/100",
                machine: $machine,
            );
        }

        // Step 3 — Safety check: is machine active?
        $isSafeToRecommendGrounding = $this->isMachineSafeToGround($machine);

        // Steps 4–8 — Gather domain context
        $healthScore       = $machine->healthStatus->composite_score;
        $lastMaintenance   = $machine->maintenanceRecords()->latest()->first();
        $sensorAnomalies   = $machine->sensorReadings()->where('is_anomaly', true)->whereDate('created_at', today())->count();
        $productionImpact  = $this->estimateProductionImpact($machine);

        // Step 9 — Calculate confidence
        $confidence = $this->calculateConfidence([
            'trust_score'     => $trustScore,
            'health_score'    => $healthScore,
            'anomaly_count'   => $sensorAnomalies,
            'data_freshness'  => $machine->last_seen_at,
        ]);

        // Step 10 — Log
        AgentPerformanceLog::create([
            'agent_name'     => 'maintenance-predictor',
            'action'         => 'predict_failure',
            'input_context'  => json_encode(['machine_id' => $machine->id]),
            'output'         => json_encode(['confidence' => $confidence, 'recommendation' => 'schedule_maintenance']),
            'confidence'     => $confidence,
            'decision_score' => $confidence,
            'evidence'       => json_encode([
                'trust_score'    => $trustScore,
                'health_score'   => $healthScore,
                'anomalies_today' => $sensorAnomalies,
            ]),
            'duration_ms'    => /* measure */ 0,
        ]);

        return match (true) {
            $confidence >= 81 => $this->autonomousAction($machine, $confidence),
            $confidence >= 61 => $this->safeRecommendation($machine, $confidence),
            $confidence >= 41 => $this->escalateForApproval($machine, $confidence),
            default           => $this->rejectDecision('maintenance-predictor', 'Insufficient confidence', $machine),
        };
    }
}
```

---

## Mandatory Safety Override

Regardless of confidence score, the following conditions **always** block autonomous execution
and **always** require human approval:

```
1. Decision involves grounding a machine that is currently in active dispatch
2. Decision involves a fatality, serious injury, or MHSA Section 23 event
3. Decision involves deleting or anonymising user personal data (GDPR/POPIA)
4. Decision affects > 25% of the fleet simultaneously
5. Decision involves a financial transaction > R100,000
6. Any contradicting safety signal is detected in sensor or incident data
7. Data trust score for any critical input < 40
```

---

## Pattern — Logging a Rejected Decision

```php
private function rejectDecision(string $agent, string $reason, mixed $subject): array
{
    AgentPerformanceLog::create([
        'agent_name'     => $agent,
        'action'         => 'decision_rejected',
        'output'         => json_encode(['rejected' => true, 'reason' => $reason]),
        'confidence'     => 0,
        'decision_score' => 0,
        'evidence'       => json_encode(['rejection_reason' => $reason]),
        'duration_ms'    => 0,
    ]);

    // Alert platform guardian if this is recurring
    $recentRejections = AgentPerformanceLog::where('agent_name', $agent)
        ->where('action', 'decision_rejected')
        ->where('created_at', '>=', now()->subHour())
        ->count();

    if ($recentRejections >= 5) {
        // Fire alert: agent is producing poor-quality decisions
        app(App\Services\RealTimeAlertService::class)->createSystemAlert(
            type: 'agent_degradation',
            level: 'high',
            message: "Agent {$agent} has rejected {$recentRejections} decisions in the last hour.",
        );
    }

    return ['status' => 'rejected', 'reason' => $reason, 'score' => 0];
}
```

---

## Decision Audit Trail

Every decision — approved, rejected, or escalated — **must** produce:

```
AgentPerformanceLog record with:
  agent_name      — which agent made the decision
  action          — what was decided
  input_context   — what data was analysed (JSON)
  output          — what was recommended or rejected (JSON)
  confidence      — numeric score (0–100)
  decision_score  — same as confidence (for dashboard queries)
  evidence        — the 6 delusion detection answers (JSON)
  duration_ms     — how long the decision took to compute
  created_at      — immutable timestamp
```

This trail is used by:
- **audit-logging-patterns**: compliance evidence
- **agent-performance-auditor**: accuracy scoring
- **autonomous-reality-validation-agent**: reality alignment scoring

---

## CheckAIDriftJob Integration

```bash
# Runs daily via scheduler
php artisan ai:check-drift

# Analyses AgentPerformanceLog for:
# - Declining average confidence scores (> 10 point drop over 7 days)
# - Increasing rejection rates (> 20% of decisions rejected)
# - Accuracy decay (predicted outcomes vs recorded actuals)
# When detected → alert platform guardian → recommend agent retraining
```

---

## Commands Reference

```bash
# Review recent agent decisions
php artisan tinker --execute '
App\Models\AgentPerformanceLog::orderByDesc("created_at")
    ->limit(20)
    ->get(["agent_name","action","confidence","decision_score","created_at"]);
'

# Check agent accuracy over last 7 days
php artisan tinker --execute '
App\Models\AgentPerformanceLog::selectRaw(
    "agent_name, AVG(confidence) as avg_confidence, COUNT(*) as decisions,
     SUM(CASE WHEN action = \"decision_rejected\" THEN 1 ELSE 0 END) as rejections"
)->where("created_at",">=",now()->subDays(7))
 ->groupBy("agent_name")
 ->get();
'

# Run AI drift check manually
php artisan ai:check-drift
```
