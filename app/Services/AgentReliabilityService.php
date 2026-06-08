<?php

namespace App\Services;

use App\Models\AgentPerformanceLog;
use Illuminate\Support\Facades\DB;

/**
 * MEGA V2 — Agent Reliability Auditor Service
 *
 * Log operations, measure reliability, and calculate the MEGA V2
 * "Agent Reliability" (+4%) and "Agent Collaboration" (+3%) domain scores.
 */
class AgentReliabilityService
{
    /**
     * Log a completed agent operation.
     *
     * @param  array<mixed>|null  $metadata
     */
    public function log(
        string $agentName,
        string $operation,
        string $status,
        float $confidenceScore = 1.0,
        int $evidenceCount = 0,
        int $findingCount = 0,
        ?int $executionTimeMs = null,
        bool $isFalsePositive = false,
        bool $isFalseNegative = false,
        ?string $summary = null,
        ?array $metadata = null,
    ): AgentPerformanceLog {
        return AgentPerformanceLog::create([
            'agent_name' => $agentName,
            'operation' => $operation,
            'status' => $status,
            'confidence_score' => $confidenceScore,
            'evidence_count' => $evidenceCount,
            'finding_count' => $findingCount,
            'execution_time_ms' => $executionTimeMs,
            'is_false_positive' => $isFalsePositive,
            'is_false_negative' => $isFalseNegative,
            'summary' => $summary,
            'metadata' => $metadata,
        ]);
    }

    /**
     * Calculate reliability score for a specific agent (0–100).
     *
     * Score = (success_count / total_count * 70) +
     *          (mean_confidence * 20) +
     *          ((1 - false_positive_rate) * 10)
     */
    public function agentScore(string $agentName, int $days = 30): float
    {
        $since = now()->subDays($days);

        $stats = AgentPerformanceLog::where('agent_name', $agentName)
            ->where('created_at', '>=', $since)
            ->select([
                DB::raw('COUNT(*) as total'),
                DB::raw("SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as success_count"),
                DB::raw('AVG(confidence_score) as avg_confidence'),
                DB::raw('SUM(CASE WHEN is_false_positive = 1 THEN 1 ELSE 0 END) as fp_count'),
            ])
            ->first();

        if ($stats === null || (int) $stats->total === 0) {
            return 0.0;
        }

        $total = (int) $stats->total;
        $successRate = (int) $stats->success_count / $total;
        $avgConf = (float) ($stats->avg_confidence ?? 0.5);
        $fpRate = (int) $stats->fp_count / $total;

        return round(($successRate * 70.0) + ($avgConf * 20.0) + ((1.0 - $fpRate) * 10.0), 2);
    }

    /**
     * Platform-wide reliability score (0–100): average across all distinct agents (last 30 days).
     */
    public function platformReliabilityScore(): float
    {
        $agents = AgentPerformanceLog::query()
            ->where('created_at', '>=', now()->subDays(30))
            ->distinct()
            ->pluck('agent_name');

        if ($agents->isEmpty()) {
            return 0.0;
        }

        $scores = $agents->map(fn (string $name) => $this->agentScore($name));

        return round((float) $scores->average(), 2);
    }

    /**
     * Summary report for MEGA V2 scorecard output.
     *
     * @return array<string, array<string, mixed>>
     */
    public function report(int $days = 30): array
    {
        $since = now()->subDays($days);

        $rows = AgentPerformanceLog::query()
            ->select([
                'agent_name',
                DB::raw('COUNT(*) as total'),
                DB::raw("SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as success"),
                DB::raw("SUM(CASE WHEN status = 'failure' THEN 1 ELSE 0 END) as failure"),
                DB::raw("SUM(CASE WHEN status = 'partial' THEN 1 ELSE 0 END) as partial"),
                DB::raw('AVG(confidence_score) as avg_confidence'),
                DB::raw('AVG(execution_time_ms) as avg_ms'),
            ])
            ->where('created_at', '>=', $since)
            ->groupBy('agent_name')
            ->get();

        $report = [];
        foreach ($rows as $row) {
            $report[$row->agent_name] = [
                'total' => (int) $row->total,
                'success' => (int) $row->success,
                'failure' => (int) $row->failure,
                'partial' => (int) $row->partial,
                'avg_confidence' => round((float) ($row->avg_confidence ?? 0), 3),
                'avg_execution_ms' => round((float) ($row->avg_ms ?? 0)),
                'reliability_score' => $this->agentScore($row->agent_name, $days),
            ];
        }

        return $report;
    }
}
