<?php

namespace App\Jobs;

use App\Models\AIAgent;
use App\Models\AILearningData;
use App\Models\Team;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Weekly AI drift detection job.
 *
 * For every active AIAgent, calculates a rolling 30-day accuracy score
 * from AILearningData records that have been validated (was_accurate is set).
 * Compares against the agent's stored accuracy_score baseline and triggers
 * notifications when accuracy degrades beyond defined thresholds.
 *
 * Thresholds:
 *   accuracy >= 0.70  → healthy
 *   accuracy  0.60    → SOFT alert (MEDIUM notification dispatched)
 *   accuracy <= 0.60  → HARD alert (CRITICAL notification, agent flagged)
 *   accuracy <= 0.50  → agent disabled (status = 'degraded')
 *
 * Queue: default
 * Schedule: weekly (Sundays at 04:00 via routes/console.php)
 */
class CheckAIDriftJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    /** Accuracy below this triggers a MEDIUM warning notification. */
    public const THRESHOLD_WARN = 0.70;

    /** Accuracy below this triggers a CRITICAL notification. */
    public const THRESHOLD_CRITICAL = 0.60;

    /** Accuracy below this disables the agent entirely. */
    public const THRESHOLD_DISABLE = 0.50;

    /** Minimum number of validated data points required for drift analysis. */
    public const MIN_DATA_POINTS = 10;

    /** Rolling window in days for accuracy calculation. */
    public const WINDOW_DAYS = 30;

    public function handle(): void
    {
        $agents = AIAgent::where('status', 'active')->get();
        $teamIds = Team::pluck('id');

        foreach ($agents as $agent) {
            $this->analyseAgent($agent, $teamIds);
        }
    }

    /** @param  Collection<int, int>  $teamIds */
    private function analyseAgent(AIAgent $agent, Collection $teamIds): void
    {
        // Fetch learning data records within the rolling window
        $records = AILearningData::where('ai_agent_id', $agent->id)
            ->where('created_at', '>=', now()->subDays(self::WINDOW_DAYS))
            ->get();

        $total = $records->count();

        if ($total < self::MIN_DATA_POINTS) {
            Log::info('CheckAIDriftJob: insufficient data points for agent', [
                'agent_type' => $agent->type,
                'data_points' => $total,
                'required' => self::MIN_DATA_POINTS,
            ]);

            return;
        }

        $correct = $records->where('was_accurate', true)->count();
        $rollingAccuracy = $correct / $total;

        // Update the agent's stored accuracy score
        $previousAccuracy = $agent->accuracy_score;
        $agent->accuracy_score = $rollingAccuracy;
        $agent->predictions_made = $total;
        $agent->successful_predictions = $correct;
        $agent->save();

        $drift = $previousAccuracy > 0
            ? round(($previousAccuracy - $rollingAccuracy) / $previousAccuracy * 100, 1)
            : 0;

        Log::info('CheckAIDriftJob: agent accuracy calculated', [
            'agent_type' => $agent->type,
            'rolling_accuracy' => round($rollingAccuracy * 100, 1).'%',
            'previous_accuracy' => round($previousAccuracy * 100, 1).'%',
            'drift_pct' => $drift.'%',
            'data_points' => $total,
        ]);

        if ($rollingAccuracy <= self::THRESHOLD_DISABLE) {
            $this->handleDisable($agent, $rollingAccuracy, $drift, $teamIds);
        } elseif ($rollingAccuracy <= self::THRESHOLD_CRITICAL) {
            $this->handleCritical($agent, $rollingAccuracy, $drift, $teamIds);
        } elseif ($rollingAccuracy < self::THRESHOLD_WARN) {
            $this->handleWarning($agent, $rollingAccuracy, $drift, $teamIds);
        }
    }

    /** @param  Collection<int, int>  $teamIds */
    private function handleDisable(AIAgent $agent, float $accuracy, float $drift, Collection $teamIds): void
    {
        $agent->status = 'degraded';
        $agent->save();

        Log::critical('CheckAIDriftJob: agent disabled due to accuracy below threshold', [
            'agent_type' => $agent->type,
            'accuracy' => round($accuracy * 100, 1).'%',
            'threshold' => (self::THRESHOLD_DISABLE * 100).'%',
        ]);

        foreach ($teamIds as $teamId) {
            NotificationService::dispatch([
                'team_id' => $teamId,
                'type' => 'ai_drift_critical',
                'title' => 'AI Agent Disabled: Accuracy Below '.(self::THRESHOLD_DISABLE * 100).'%',
                'message' => "The {$agent->type} AI agent has been automatically disabled. "
                    .'Rolling 30-day accuracy: '.round($accuracy * 100, 1).'% '
                    .'(drift: '.$drift.'% from baseline). '
                    .'Manual review and retraining required before re-enabling.',
                'alert_level' => 'critical',
                'data' => [
                    'agent_type' => $agent->type,
                    'accuracy' => $accuracy,
                    'drift_pct' => $drift,
                    'action_required' => 'manual_review',
                ],
                'notify_roles' => ['admin'],
            ]);
        }
    }

    /** @param  Collection<int, int>  $teamIds */
    private function handleCritical(AIAgent $agent, float $accuracy, float $drift, Collection $teamIds): void
    {
        Log::error('CheckAIDriftJob: agent accuracy critical', [
            'agent_type' => $agent->type,
            'accuracy' => round($accuracy * 100, 1).'%',
        ]);

        foreach ($teamIds as $teamId) {
            NotificationService::dispatch([
                'team_id' => $teamId,
                'type' => 'ai_drift_critical',
                'title' => 'AI Agent Accuracy Critical: '.$agent->type,
                'message' => "The {$agent->type} AI agent accuracy has dropped to "
                    .round($accuracy * 100, 1).'% over the last 30 days '
                    .'(drift: '.$drift.'% from baseline). '
                    .'This agent is at risk of being disabled. Review training data.',
                'alert_level' => 'critical',
                'data' => [
                    'agent_type' => $agent->type,
                    'accuracy' => $accuracy,
                    'drift_pct' => $drift,
                    'action_required' => 'review_training_data',
                ],
                'notify_roles' => ['admin'],
            ]);
        }
    }

    /** @param  Collection<int, int>  $teamIds */
    private function handleWarning(AIAgent $agent, float $accuracy, float $drift, Collection $teamIds): void
    {
        Log::warning('CheckAIDriftJob: agent accuracy degraded', [
            'agent_type' => $agent->type,
            'accuracy' => round($accuracy * 100, 1).'%',
        ]);

        foreach ($teamIds as $teamId) {
            NotificationService::dispatch([
                'team_id' => $teamId,
                'type' => 'ai_drift_warning',
                'title' => 'AI Agent Accuracy Warning: '.$agent->type,
                'message' => "The {$agent->type} AI agent accuracy has dropped to "
                    .round($accuracy * 100, 1).'% over the last 30 days '
                    .'(drift: '.$drift.'% from baseline). '
                    .'Monitor closely and consider retraining if trend continues.',
                'alert_level' => 'warning',
                'data' => [
                    'agent_type' => $agent->type,
                    'accuracy' => $accuracy,
                    'drift_pct' => $drift,
                ],
                'notify_roles' => ['admin', 'fleet_manager'],
            ]);
        }
    }
}
