<?php

namespace App\Livewire;

use App\Models\AIAgent;
use App\Models\AIAnalysisSession;
use App\Models\AIInsight;
use App\Models\AIPredictiveAlert;
use App\Models\AIRecommendation;
use Illuminate\View\View;
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
        $team = auth()->user()->currentTeam;
        $startDate = now()->subDays((int) $this->timeRange);

        // Get agents
        $agents = AIAgent::all();

        // Recommendations over time
        $recommendationsTimeline = AIRecommendation::where('team_id', $team->id)
            ->where('created_at', '>=', $startDate)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count, status')
            ->groupBy('date', 'status')
            ->orderBy('date')
            ->get()
            ->groupBy('date');

        // Category breakdown
        $categoryBreakdown = AIRecommendation::where('team_id', $team->id)
            ->where('created_at', '>=', $startDate)
            ->selectRaw('category, COUNT(*) as count, SUM(estimated_savings) as savings')
            ->groupBy('category')
            ->get();

        // Priority distribution
        $priorityDistribution = AIRecommendation::where('team_id', $team->id)
            ->where('created_at', '>=', $startDate)
            ->selectRaw('priority, COUNT(*) as count')
            ->groupBy('priority')
            ->get();

        // Agent performance
        $agentPerformance = $agents->map(function ($agent) use ($team, $startDate) {
            $recommendations = AIRecommendation::where('team_id', $team->id)
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
                'predictions_made' => $agent->predictions_made,
                'total_savings' => $recommendations->where('status', 'implemented')->sum('estimated_savings'),
            ];
        });

        // Implementation rate over time
        $implementationRate = AIRecommendation::where('team_id', $team->id)
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
        $savingsTimeline = AIRecommendation::where('team_id', $team->id)
            ->where('status', 'implemented')
            ->where('implemented_at', '>=', $startDate)
            ->selectRaw('DATE(implemented_at) as date, SUM(estimated_savings) as savings')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Top recommendations by savings
        $topRecommendations = AIRecommendation::where('team_id', $team->id)
            ->where('status', 'implemented')
            ->where('implemented_at', '>=', $startDate)
            ->orderByDesc('estimated_savings')
            ->with('aiAgent')
            ->limit(10)
            ->get();

        // Analysis sessions
        $recentSessions = AIAnalysisSession::where('team_id', $team->id)
            ->where('created_at', '>=', $startDate)
            ->with('aiAgent')
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        // Alert statistics
        $alertStats = AIPredictiveAlert::where('team_id', $team->id)
            ->where('created_at', '>=', $startDate)
            ->selectRaw('alert_type, severity, COUNT(*) as count, 
                        SUM(CASE WHEN is_acknowledged THEN 1 ELSE 0 END) as acknowledged,
                        AVG(probability) as avg_probability')
            ->groupBy('alert_type', 'severity')
            ->get();

        // Insights by category
        $insightsByCategory = AIInsight::where('team_id', $team->id)
            ->where('created_at', '>=', $startDate)
            ->selectRaw('category, insight_type, COUNT(*) as count')
            ->groupBy('category', 'insight_type')
            ->get();

        return view('livewire.ai-analytics', [
            'agents' => $agents,
            'recommendationsTimeline' => $recommendationsTimeline,
            'categoryBreakdown' => $categoryBreakdown,
            'priorityDistribution' => $priorityDistribution,
            'agentPerformance' => $agentPerformance,
            'implementationRate' => $implementationRate,
            'savingsTimeline' => $savingsTimeline,
            'topRecommendations' => $topRecommendations,
            'recentSessions' => $recentSessions,
            'alertStats' => $alertStats,
            'insightsByCategory' => $insightsByCategory,
        ]);
    }
}
