---
name: ai-agent-patterns
description: >
  Mines platform AI agent patterns. Use when: adding a new AI prediction agent, integrating AI
  insights into a feature, writing tests for AI model scopes, working with AIPredictiveAlert or
  AIRecommendation models, or debugging why AI predictions are stale or incorrect.
argument-hint: 'Describe the AI or predictive analytics task you need help with'
---

# AI Agent Patterns

## When to Use

- Creating a new AI prediction or optimization service
- Adding a new AIInsight or AIRecommendation type
- Writing tests for AI model scoping
- Connecting AI predictions to notifications or alerts
- Debugging stale or missing AI predictions

---

## AI Agent Service Structure

All AI agents in `app/Services/AI/` follow this interface pattern:

```php
class NewPredictorAgent
{
    public function __construct(
        private readonly MaintenanceHealthService $maintenanceService,
        // inject only what you need
    ) {}

    public function predict(Team $team): Collection
    {
        // 1. Gather historical data
        // 2. Apply prediction logic
        // 3. Persist AIInsight / AIPredictiveAlert records
        // 4. Return results
    }
}
```

---

## Pattern — Creating an AI Insight

```php
use App\Models\AIInsight;

AIInsight::create([
    'team_id'     => $team->id,
    'machine_id'  => $machine->id,
    'type'        => 'maintenance_prediction',    // or 'anomaly', 'fuel_forecast', etc.
    'title'       => 'Predicted hydraulic failure in 12 hours',
    'description' => "Based on hydraulic pressure trends...",
    'confidence'  => 0.87,    // 0–1
    'data'        => ['pressure_trend' => $trend, 'threshold' => 150],
    'valid_until' => now()->addHours(24),
]);
```

---

## Pattern — Creating a Predictive Alert

```php
use App\Models\AIPredictiveAlert;

// Deduplicate first
$existing = AIPredictiveAlert::where([
    'machine_id'       => $machine->id,
    'type'             => 'hydraulic_failure',
    'is_acknowledged'  => false,
])->first();

if (! $existing) {
    AIPredictiveAlert::create([
        'team_id'         => $team->id,
        'machine_id'      => $machine->id,
        'type'            => 'hydraulic_failure',
        'severity'        => 'high',
        'message'         => 'Hydraulic failure predicted within 12 hours',
        'predicted_at'    => now()->addHours(12),
        'confidence'      => 0.87,
    ]);
}
```

---

## Pattern — AI Recommendation Action Tracking

```php
// Record when a user acts on a recommendation
AiRecommendationAction::create([
    'recommendation_id' => $recommendation->id,
    'user_id'           => $user->id,
    'action'            => 'accepted',   // or 'dismissed', 'deferred'
    'notes'             => $notes,
]);

// Update recommendation status
$recommendation->update(['status' => 'accepted']);
```

---

## AI Learning Data Feedback Loop

When a prediction is validated (either confirmed or rejected by outcome):
```php
AILearningData::create([
    'type'    => 'maintenance_prediction',
    'input'   => json_encode($inputFeatures),
    'outcome' => 'confirmed',   // or 'rejected'
    'context' => json_encode(['machine_id' => $machine->id]),
]);
```

---

## Commands Reference

```bash
# Check AI agent last run
php artisan tinker --execute 'App\Models\AIAnalysisSession::latest()->first();'

# Run AI manually
php artisan tinker --execute '
$service = app(App\Services\AIOptimizationService::class);
$team = App\Models\Team::find(TEAM_ID);
$service->runAll($team);
'

# Run AI tests
php artisan test --compact tests/Feature/AIModelScopesTest.php
```
