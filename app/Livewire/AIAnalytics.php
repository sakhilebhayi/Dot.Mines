<?php

namespace App\Livewire;

use App\Models\AIAgent;
use App\Models\AIAnalysisSession;
use App\Models\AIInsight;
use App\Models\AIPredictiveAlert;
use App\Models\AIRecommendation;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Livewire\Component;

class AIAnalytics extends Component
{
    public string $timeRange = '30'; // days

    public string $selectedAgent = 'all';

    public bool $showDetails = true;

    public function mount(): void
    {
        //
    }

    public function setTimeRange(string $days): void
    {
        $this->timeRange = $days;
    }

    public function setAgent(string $agentType): void
    {
        $this->selectedAgent = $agentType;
    }

    public function render(): View
    {
        $team = Auth::user()->currentTeam;
        $teamId = $team?->id ?? 0;
        $startDate = now()->subDays((int) $this->timeRange);

        // Cache all analytics queries for 10 minutes.
        // Key includes team, time range, and agent filter so different
        // filter combinations are cached independently.
        $cacheKey = "ai_analytics.{$teamId}.{$this->timeRange}.{$this->selectedAgent}";
        $ttl = now()->addMinutes(10);

        $analytics = Cache::remember($cacheKey, $ttl, function () use ($teamId, $startDate) {
            // Get agents
            $agents = AIAgent::all();

            // Recommendations over time
            $recommendationsTimeline = AIRecommendation::where('team_id', $teamId)
                ->where('created_at', '>=', $startDate)
                ->selectRaw('DATE(created_at) as date, COUNT(*) as count, status')
                ->groupBy('date', 'status')
                ->orderBy('date')
                ->get()
                ->groupBy('date');

            // Category breakdown
            $categoryBreakdown = AIRecommendation::where('team_id', $teamId)
                ->where('created_at', '>=', $startDate)
                ->selectRaw('category, COUNT(*) as count, SUM(estimated_savings) as savings')
                ->groupBy('category')
                ->get();

            // Priority distribution
            $priorityDistribution = AIRecommendation::where('team_id', $teamId)
                ->where('created_at', '>=', $startDate)
                ->selectRaw('priority, COUNT(*) as count')
                ->groupBy('priority')
                ->get();

            // Agent performance (one query per agent — batched in a map)
            $agentPerformance = $agents->map(function ($agent) use ($startDate, $teamId) {
                $recommendations = AIRecommendation::where('team_id', $teamId)
                    ->where('ai_agent_id', $agent->id)
                    ->where('created_at', '>=', $startDate)
                    ->get();

                return [
                    'name' => $agent->name,
                    'type' => $agent->type,
                    'total_recommendations' => $recommendations->count(),
                    'implemented' => $recommendations->where('status', 'implemented')->count(),
                    'pending' => $recommendations->where('status', 'pending')->count(),
                    'accuracy' => $agent->accuracy_score,
                    'total_savings' => $recommendations->where('status', 'implemented')->sum('estimated_savings'),
                ];
            });

            // Implementation rate over time
            $implementationRate = AIRecommendation::where('team_id', $teamId)
                ->where('created_at', '>=', $startDate)
                ->selectRaw('DATE(created_at) as date, 
                            COUNT(*) as total,
                            SUM(CASE WHEN status = "implemented" THEN 1 ELSE 0 END) as implemented')
                ->groupBy('date')
                ->orderBy('date')
                ->get()
                ->map(function ($item) {
                    $item->rate = $item->total > 0 ? ($item->implemented / $item->total) * 100 : 0;

                    return $item;
                });

            // Savings over time
            $savingsTimeline = AIRecommendation::where('team_id', $teamId)
                ->where('status', 'implemented')
                ->where('implemented_at', '>=', $startDate)
                ->selectRaw('DATE(implemented_at) as date, SUM(estimated_savings) as savings')
                ->groupBy('date')
                ->orderBy('date')
                ->get();

            // Top recommendations by savings
            $topRecommendations = AIRecommendation::where('team_id', $teamId)
                ->where('status', 'implemented')
                ->where('implemented_at', '>=', $startDate)
                ->orderByDesc('estimated_savings')
                ->with('aiAgent')
                ->limit(10)
                ->get();

            // Analysis sessions
            $recentSessions = AIAnalysisSession::where('team_id', $teamId)
                ->where('created_at', '>=', $startDate)
                ->with('aiAgent')
                ->orderByDesc('created_at')
                ->limit(20)
                ->get();

            // Alert statistics
            $alertStats = AIPredictiveAlert::where('team_id', $teamId)
                ->where('created_at', '>=', $startDate)
                ->selectRaw('alert_type, severity, COUNT(*) as count, 
                            SUM(CASE WHEN is_acknowledged THEN 1 ELSE 0 END) as acknowledged,
                            AVG(probability) as avg_probability')
                ->groupBy('alert_type', 'severity')
                ->get();

            // Insights by category
            $insightsByCategory = AIInsight::where('team_id', $teamId)
                ->where('created_at', '>=', $startDate)
                ->selectRaw('category, insight_type, COUNT(*) as count')
                ->groupBy('category', 'insight_type')
                ->get();

            return compact(
                'agents',
                'recommendationsTimeline',
                'categoryBreakdown',
                'priorityDistribution',
                'agentPerformance',
                'implementationRate',
                'savingsTimeline',
                'topRecommendations',
                'recentSessions',
                'alertStats',
                'insightsByCategory',
            );
        });

        return view('livewire.ai-analytics', $analytics);
    }
}
