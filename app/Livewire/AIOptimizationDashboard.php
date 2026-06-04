<?php

namespace App\Livewire;

use App\Models\AIInsight;
use App\Models\AIPredictiveAlert;
use App\Models\AIRecommendation;
use App\Services\AI\AIOptimizationService;
use App\Traits\BrowserEventBridge;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class AIOptimizationDashboard extends Component
{
    use BrowserEventBridge, WithPagination;

    public string $activeTab = 'overview';

    public string $selectedCategory = 'all';

    public string $selectedPriority = 'all';

    /** @var array<string, mixed> */
    public array $filters = [
        'category' => '',
        'priority' => '',
        'status' => '',
    ];

    public bool $analysisRunning = false;

    public ?int $pendingRecommendationId = null;

    public ?string $pendingRecommendationAction = null; // 'implement'|'reject'

    public bool $showRecommendationConfirm = false;

    protected $aiService;

    public function boot(AIOptimizationService $aiService)
    {
        $this->aiService = $aiService;
    }

    public function mount()
    {
        // Auto-run analysis if no recent data
        $teamId = Auth::user()->currentTeam?->id;
        $lastRecommendation = $teamId ? AIRecommendation::where('team_id', $teamId)
            ->latest()
            ->first() : null;

        if (! $lastRecommendation || $lastRecommendation->created_at->diffInHours(now()) > 24) {
            $this->runAnalysis();
        }
    }

    public function runAnalysis()
    {
        $this->analysisRunning = true;

        try {
            $this->aiService->runComprehensiveAnalysis(
                Auth::user()->currentTeam,
                Auth::user()
            );

            $this->dispatch('analysis-completed');
            $this->dispatchBrowserEvent('notify', ['type' => 'success', 'message' => 'AI analysis completed successfully!']);
        } catch (\Exception $e) {
            $this->dispatchBrowserEvent('notify', ['type' => 'error', 'message' => 'Analysis failed: '.$e->getMessage()]);
        }

        $this->analysisRunning = false;
    }

    public function setCategory($category)
    {
        $this->selectedCategory = $category;
        $this->resetPage();
    }

    public function setPriority($priority)
    {
        $this->selectedPriority = $priority;
        $this->resetPage();
    }

    public function implementRecommendation($recommendationId)
    {
        $team = Auth::user()->currentTeam;
        $recommendation = AIRecommendation::where('team_id', $team->id)->findOrFail($recommendationId);
        try {
            $this->authorize('update', $recommendation);

            $recommendation->markAsImplemented(Auth::user());

            $this->dispatchBrowserEvent('notify', ['type' => 'success', 'message' => 'Recommendation marked as implemented!']);
            $this->dispatch('recommendation-updated', ['id' => $recommendation->id, 'status' => 'implemented']);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            $this->dispatchBrowserEvent('notify', ['type' => 'error', 'message' => 'You are not authorized to implement this recommendation.']);

            return;
        }
    }

    public function rejectRecommendation($recommendationId)
    {
        $team = Auth::user()->currentTeam;
        $recommendation = AIRecommendation::where('team_id', $team->id)->findOrFail($recommendationId);
        try {
            $this->authorize('update', $recommendation);

            $recommendation->update(['status' => 'rejected']);

            $this->dispatchBrowserEvent('notify', ['type' => 'success', 'message' => 'Recommendation rejected.']);
            $this->dispatch('recommendation-updated', ['id' => $recommendation->id, 'status' => 'rejected']);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            $this->dispatchBrowserEvent('notify', ['type' => 'error', 'message' => 'You are not authorized to reject this recommendation.']);

            return;
        }
    }

    public function promptRecommendationAction($recommendationId, $action)
    {
        $this->pendingRecommendationId = $recommendationId;
        $this->pendingRecommendationAction = $action;
        $this->showRecommendationConfirm = true;
    }

    public function confirmRecommendationAction()
    {
        if (! $this->pendingRecommendationId || ! in_array($this->pendingRecommendationAction, ['implement', 'reject'])) {
            $this->showRecommendationConfirm = false;
            $this->pendingRecommendationId = null;
            $this->pendingRecommendationAction = null;

            return;
        }

        $id = $this->pendingRecommendationId;
        $action = $this->pendingRecommendationAction;

        if ($action === 'implement') {
            $this->implementRecommendation($id);
        } else {
            $this->rejectRecommendation($id);
        }

        $this->showRecommendationConfirm = false;
        $this->pendingRecommendationId = null;
        $this->pendingRecommendationAction = null;
        // Refresh pagination/list
        $this->resetPage();
    }

    public function cancelRecommendationAction()
    {
        $this->showRecommendationConfirm = false;
        $this->pendingRecommendationId = null;
        $this->pendingRecommendationAction = null;
    }

    public function acknowledgeAlert($alertId)
    {
        $team = Auth::user()->currentTeam;
        $alert = \App\Models\AIPredictiveAlert::where('team_id', $team->id)->findOrFail($alertId);

        $alert->update([
            'is_acknowledged' => true,
            'acknowledged_by' => Auth::id(),
            'acknowledged_at' => now(),
        ]);

        $this->dispatchBrowserEvent('notify', ['type' => 'success', 'message' => 'Alert acknowledged.']);
    }

    /** @return array<string, mixed> */
    public function getOverviewDataProperty(): array
    {

        $allRecs = \App\Models\AIRecommendation::where('team_id', $team->id)->get();
        $pending = $allRecs->where('status', 'pending');
        $implemented = $allRecs->where('status', 'implemented');
        $total = $allRecs->count();

        $implementationRate = $total > 0
            ? round(($implemented->count() / $total) * 100, 1)
            : 0;

        $categories = ['fleet', 'fuel', 'maintenance', 'production', 'route', 'cost'];
        $byCategory = [];
        foreach ($categories as $cat) {
            $catRecs = $allRecs->where('category', $cat);
            $catTotal = $catRecs->count();
            $catImpl = $catRecs->where('status', 'implemented')->count();
            $byCategory[$cat] = [
                'label' => ucfirst($cat),
                'total' => $catTotal,
                'pending' => $catRecs->where('status', 'pending')->count(),
                'implemented' => $catImpl,
                'impl_rate_pct' => $catTotal > 0 ? round(($catImpl / $catTotal) * 100) : 0,
                'avg_confidence' => $catRecs->isNotEmpty()
                    ? round($catRecs->avg('confidence_score') * 100, 0)
                    : 0,
                'potential_savings' => round($catRecs->where('status', 'pending')->sum('estimated_savings'), 0),
            ];
        }

        $agents = \App\Models\AIAgent::all();

        // Data transparency: record counts from real system tables
        $teamId = $team->id;
        $dataPoints = [
            'production_records' => \App\Models\ProductionRecord::where('team_id', $teamId)->count(),
            'machines' => \App\Models\Machine::where('team_id', $teamId)->count(),
            'maintenance_records' => \App\Models\MaintenanceRecord::where('team_id', $teamId)->count(),
            'fuel_transactions' => \App\Models\FuelTransaction::where('team_id', $teamId)->count(),
            'machine_metrics' => \App\Models\MachineMetric::where('team_id', $teamId)->count(),
        ];

        return [
            'implementation_rate' => $implementationRate,
            'realized_savings' => round($implemented->sum('estimated_savings'), 0),
            'potential_savings' => round($pending->sum('estimated_savings'), 0),
            'critical_pending' => $pending->where('priority', 'critical')->count(),
            'avg_confidence' => $total > 0 ? round($allRecs->avg('confidence_score') * 100, 0) : 0,
            'by_category' => $byCategory,
            'agents' => $agents,
            'top_opportunity' => $pending->sortByDesc('estimated_savings')->first(),
            'data_points' => $dataPoints,
        ];
    }

    // (Possibly missing function for alert acknowledgement should be implemented here if needed)

    public function markInsightAsRead($insightId)
    {
        $team = Auth::user()->currentTeam;
        $insight = AIInsight::where('team_id', $team->id)->findOrFail($insightId);
        $this->authorize('update', $insight);
        $insight->markAsRead();
        $this->dispatchBrowserEvent('notify', ['type' => 'success', 'message' => 'Insight marked as read.']);
    }

    public function render(): \Illuminate\View\View
    {
        $team = Auth::user()->currentTeam;

        // Get dashboard data
        $dashboardData = $this->aiService->getDashboardInsights($team);

        // Get recommendations with filters
        $recommendationsQuery = AIRecommendation::where('team_id', $team->id)
            ->with(['aiAgent', 'machine', 'mineArea', 'route']);
        // Status filter: default to 'pending' when no status filter provided
        if (! empty($this->filters['status'])) {
            $recommendationsQuery->where('status', $this->filters['status']);
        } else {
            $recommendationsQuery->where('status', 'pending');
        }

        // Category filter (supports backward-compatible selectedCategory)
        if (! empty($this->filters['category'])) {
            $recommendationsQuery->where('category', $this->filters['category']);
        } elseif ($this->selectedCategory !== 'all') {
            $recommendationsQuery->where('category', $this->selectedCategory);
        }

        // Priority filter (supports backward-compatible selectedPriority)
        if (! empty($this->filters['priority'])) {
            $recommendationsQuery->where('priority', $this->filters['priority']);
        } elseif ($this->selectedPriority !== 'all') {
            $recommendationsQuery->where('priority', $this->selectedPriority);
        }

        $recommendations = $recommendationsQuery
            ->orderBy('priority')
            ->orderByDesc('confidence_score')
            ->paginate(10);

        // Get predictive alerts
        $predictiveAlerts = AIPredictiveAlert::where('team_id', $team->id)
            ->unacknowledged()
            ->with(['aiAgent', 'machine', 'mineArea'])
            ->orderBy('severity')
            ->orderBy('predicted_occurrence')
            ->limit(5)
            ->get();

        return view('livewire.ai-optimization-dashboard', [
            'stats' => $dashboardData['stats'],
            'insights' => $dashboardData['insights'],
            'recommendations' => $recommendations,
            'predictiveAlerts' => $predictiveAlerts,
            'overviewData' => $this->overviewData,
        ]);
    }
}
