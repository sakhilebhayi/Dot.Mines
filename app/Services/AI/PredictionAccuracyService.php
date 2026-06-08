<?php

namespace App\Services\AI;

use App\Models\AIPredictionOutcome;
use Illuminate\Support\Facades\DB;

/**
 * MEGA V2 — AI Prediction Accuracy Service
 *
 * Provides prediction logging and accuracy metrics for the
 * "AI Reliability" (4%) and "AI Drift Control" (3%) MEGA V2 scoring domains.
 */
class PredictionAccuracyService
{
    /**
     * Log a new prediction made by an AI agent.
     *
     * @param  array<mixed>  $predictedValue
     * @param  array<mixed>|null  $metadata
     */
    public function logPrediction(
        string $agentType,
        string $predictionType,
        int $teamId,
        array $predictedValue,
        float $confidenceScore = 1.0,
        ?int $machineId = null,
        ?array $metadata = null,
    ): AIPredictionOutcome {
        return AIPredictionOutcome::create([
            'agent_type' => $agentType,
            'prediction_type' => $predictionType,
            'team_id' => $teamId,
            'machine_id' => $machineId,
            'predicted_value' => $predictedValue,
            'predicted_at' => now(),
            'confidence_score' => $confidenceScore,
            'metadata' => $metadata,
        ]);
    }

    /**
     * Calculate the mean accuracy score per agent type over the last N days.
     *
     * Returns array keyed by agent_type with keys:
     *   accuracy_mean, total_predictions, false_positive_rate, false_negative_rate, drift_score
     *
     * @return array<string, array<string, float|int|null>>
     */
    public function accuracyReport(int $days = 30): array
    {
        $since = now()->subDays($days);

        $rows = AIPredictionOutcome::query()
            ->select([
                'agent_type',
                DB::raw('COUNT(*) as total'),
                DB::raw('COUNT(accuracy_score) as evaluated'),
                DB::raw('AVG(accuracy_score) as avg_accuracy'),
                DB::raw('SUM(CASE WHEN false_positive = 1 THEN 1 ELSE 0 END) as fp_count'),
                DB::raw('SUM(CASE WHEN false_negative = 1 THEN 1 ELSE 0 END) as fn_count'),
            ])
            ->where('predicted_at', '>=', $since)
            ->groupBy('agent_type')
            ->get();

        $report = [];
        foreach ($rows as $row) {
            $evaluated = (int) $row->evaluated;
            $report[$row->agent_type] = [
                'total_predictions' => (int) $row->total,
                'evaluated_predictions' => $evaluated,
                'accuracy_mean' => $evaluated > 0 ? round((float) $row->avg_accuracy, 4) : null,
                'false_positive_rate' => $evaluated > 0 ? round((int) $row->fp_count / $evaluated, 4) : null,
                'false_negative_rate' => $evaluated > 0 ? round((int) $row->fn_count / $evaluated, 4) : null,
            ];
        }

        return $report;
    }

    /**
     * Compute drift score: difference in mean accuracy between last 7 days vs prior 23 days.
     * Positive drift = improving. Negative drift = degrading (concern at < -0.05).
     *
     * @return array<string, float|null>
     */
    public function driftReport(): array
    {
        $recent = $this->accuracyReport(7);
        $prior = $this->accuracyReport(30);

        $drift = [];
        foreach (array_unique(array_merge(array_keys($recent), array_keys($prior))) as $agent) {
            $recentAcc = $recent[$agent]['accuracy_mean'] ?? null;
            $priorAcc = $prior[$agent]['accuracy_mean'] ?? null;

            $drift[$agent] = ($recentAcc !== null && $priorAcc !== null)
                ? round($recentAcc - $priorAcc, 4)
                : null;
        }

        return $drift;
    }

    /**
     * Overall AI Reliability Score (0–10) for MEGA V2 scoring.
     * Based on mean accuracy across all evaluated agents over 30 days.
     */
    public function reliabilityScore(): float
    {
        $avgAccuracy = AIPredictionOutcome::query()
            ->whereNotNull('accuracy_score')
            ->where('predicted_at', '>=', now()->subDays(30))
            ->avg('accuracy_score');

        if ($avgAccuracy === null) {
            return 0.0; // No data — score is 0 (not enough to trust)
        }

        return round((float) $avgAccuracy * 10, 2);
    }
}
