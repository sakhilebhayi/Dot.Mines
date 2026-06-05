<?php

namespace App\Livewire;

use App\Models\AIAgent;
use App\Models\AIInsight;
use App\Models\AIPredictiveAlert;
use App\Models\AIRecommendation;
use App\Models\FuelTransaction;
use App\Models\Machine;
use App\Models\MachineMetric;
use App\Models\MaintenanceRecord;
use App\Models\ProductionRecord;
use App\Models\User;
use App\Services\AI\AIOptimizationService;
use App\Traits\BrowserEventBridge;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
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

    protected ?AIOptimizationService $aiService = null;

    public function boot(AIOptimizationService $aiService): void
    {
        $this->aiService = $aiService;
    }

    public function mount(): void
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

    public function runAnalysis(): void
    {
        $this->analysisRunning = true;

        try {
            /** @var User|null $user */
            $user = Auth::user();
            $team = $user?->currentTeam;
            if ($team) {
                $this->aiService?->runComprehensiveAnalysis($team, $user);
            }

            $this->dispatch('analysis-completed');
            $this->dispatchBrowserEvent('notify', ['type' => 'success', 'message' => 'AI analysis completed successfully!']);
        } catch (\Exception $e) {
            $this->dispatchBrowserEvent('notify', ['type' => 'error', 'message' => 'Analysis failed: '.$e->getMessage()]);
        }

        $this->analysisRunning = false;
    }

    public function setCategory(string $category): void
    {
        $this->selectedCategory = $category;
        $this->resetPage();
    }

    public function setPriority(string $priority): void
    {
        $this->selectedPriority = $priority;
        $this->resetPage();
    }

    public function implementRecommendation(int $recommendationId): void
    {
        $team = Auth::user()->currentTeam;
        $recommendation = AIRecommendation::where('team_id', $team->id)->findOrFail($recommendationId);
        try {
            $this->authorize('update', $recommendation);

            $recommendation->markAsImplemented(Auth::user());

            $this->dispatchBrowserEvent('notify', ['type' => 'success', 'message' => 'Recommendation marked as implemented!']);
            $this->dispatch('recommendation-updated', ['id' => $recommendation->id, 'status' => 'implemented']);
        } catch (AuthorizationException $e) {
            $this->dispatchBrowserEvent('notify', ['type' => 'error', 'message' => 'You are not authorized to implement this recommendation.']);

            return;
        }
    }

    public function rejectRecommendation(int $recommendationId): void
    {
        $team = Auth::user()->currentTeam;
        $recommendation = AIRecommendation::where('team_id', $team->id)->findOrFail($recommendationId);
        try {
            $this->authorize('update', $recommendation);

            $recommendation->update(['status' => 'rejected']);

            $this->dispatchBrowserEvent('notify', ['type' => 'success', 'message' => 'Recommendation rejected.']);
            $this->dispatch('recommendation-updated', ['id' => $recommendation->id, 'status' => 'rejected']);
        } catch (AuthorizationException $e) {
            $this->dispatchBrowserEvent('notify', ['type' => 'error', 'message' => 'You are not authorized to reject this recommendation.']);

            return;
        }
    }

    public function promptRecommendationAction(int $recommendationId, string $action): void
    {
        $this->pendingRecommendationId = $recommendationId;
        $this->pendingRecommendationAction = $action;
        $this->showRecommendationConfirm = true;
    }

    public function confirmRecommendationAction(): void
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

    public function cancelRecommendationAction(): void
    {
        $this->showRecommendationConfirm = false;
        $this->pendingRecommendationId = null;
        $this->pendingRecommendationAction = null;
    }

    public function acknowledgeAlert(int $alertId): void
    {
        $team = Auth::user()->currentTeam;
        $alert = AIPredictiveAlert::where('team_id', $team->id)->findOrFail($alertId);

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

        $allRecs = AIRecommendation::where('team_id', $team->id)->get();
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

        $agents = AIAgent::all();

        // Data transparency: record counts from real system tables
        $teamId = $team->id;
        $dataPoints = [
            'production_records' => ProductionRecord::where('team_id', $teamId)->count(),
            'machines' => Machine::where('team_id', $teamId)->count(),
            'maintenance_records' => MaintenanceRecord::where('team_id', $teamId)->count(),
            'fuel_transactions' => FuelTransaction::where('team_id', $teamId)->count(),
            'machine_metrics' => MachineMetric::where('team_id', $teamId)->count(),
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

    public function markInsightAsRead(int $insightId): void
    {
        $team = Auth::user()->currentTeam;
        $insight = AIInsight::where('team_id', $team->id)->findOrFail($insightId);
        $this->authorize('update', $insight);
        $insight->markAsRead();
        $this->dispatchBrowserEvent('notify', ['type' => 'success', 'message' => 'Insight marked as read.']);
    }

    public function render(): View
    {
        $team = Auth::user()->currentTeam;

        // Get dashboard data
        $dashboardData = $this->aiService?->getDashboardInsights($team) ?? [];

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
