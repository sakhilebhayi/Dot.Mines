<?php

namespace App\Livewire;

use App\Models\AIInsight;
use App\Models\AIPredictiveAlert;
use App\Models\AIRecommendation;
use App\Models\AiRecommendationAction;
use App\Services\AI\AIOptimizationService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Lazy;
use Livewire\Component;
use Livewire\WithPagination;

#[Lazy]
class AIOptimizationDashboard extends Component
{
    /**
     * Skeleton shown while this page lazy-loads -- the page shell paints
     * immediately instead of blocking on mount()'s data queries.
     *
     * @psalm-suppress PossiblyUnusedMethod -- invoked by Livewire's lazy-loading lifecycle
     */
    public function placeholder(): View
    {
        return view('livewire.placeholders.dashboard');
    }

    use WithPagination;

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

    public string $rejectReason = '';

    protected ?AIOptimizationService $aiService = null;

    public function boot(AIOptimizationService $aiService): void
    {
        $this->aiService = $aiService;
    }

    public function mount(): void
    {
        // Auto-run analysis if no recent data
        $lastRecommendation = AIRecommendation::where('team_id', auth()->user()->currentTeam->id)
            ->latest()
            ->first();

        if (! $lastRecommendation || $lastRecommendation->created_at->diffInHours(now()) > 24) {
            $this->runAnalysis();
        }
    }

    public function runAnalysis(): void
    {
        $this->analysisRunning = true;

        try {
            $aiService = $this->aiService;
            assert($aiService !== null);
            $aiService->runComprehensiveAnalysis(
                auth()->user()->currentTeam,
                auth()->user()
            );

            $this->dispatch('analysis-completed');
            $this->dispatch('notify', ['type' => 'success', 'message' => 'AI analysis completed successfully!']);
        } catch (\Throwable $e) {
            // The raw exception message (which can include third-party API
            // responses, stack details, or internal identifiers) used to go
            // straight to the user. Log it for us; tell them something
            // useful instead.
            Log::error('AI analysis failed', [
                'user_id' => auth()->id(),
                'team_id' => auth()->user()?->current_team_id,
                'error' => $e->getMessage(),
            ]);
            $this->dispatch('notify', ['type' => 'error', 'message' => 'AI analysis could not be completed right now. Please try again in a few minutes.']);
        }

        $this->analysisRunning = false;
    }

    /**
     * @param  string  $category
     */
    public function setCategory($category): void
    {
        $this->selectedCategory = $category;
        $this->resetPage();
    }

    /**
     * @param  string  $priority
     */
    public function setPriority($priority): void
    {
        $this->selectedPriority = $priority;
        $this->resetPage();
    }

    /**
     * @param  int|string  $recommendationId
     */
    public function implementRecommendation($recommendationId): void
    {
        $team = auth()->user()->currentTeam;
        $recommendation = AIRecommendation::where('team_id', $team->id)->findOrFail($recommendationId);
        try {
            $this->authorize('update', $recommendation);

            $recommendation->markAsImplemented(auth()->user());

            AiRecommendationAction::create([
                'team_id' => $team->id,
                'ai_recommendation_id' => $recommendation->id,
                'recommendation_hash' => sha1($recommendation->id.$recommendation->title),
                'recommendation' => ['title' => $recommendation->title, 'description' => $recommendation->description],
                'status' => 'implemented',
                'actioned_by' => auth()->id(),
                'actioned_at' => now(),
            ]);

            $this->dispatch('notify', ['type' => 'success', 'message' => 'Recommendation marked as implemented!']);
            $this->dispatch('recommendation-updated', ['id' => $recommendation->id, 'status' => 'implemented']);
        } catch (AuthorizationException $e) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'You are not authorized to implement this recommendation.']);

            return;
        }
    }

    /**
     * @param  int|string  $recommendationId
     */
    public function rejectRecommendation($recommendationId, string $reason = ''): void
    {
        $team = auth()->user()->currentTeam;
        $recommendation = AIRecommendation::where('team_id', $team->id)->findOrFail($recommendationId);

        if (trim($reason) === '') {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'A rejection reason is required.']);

            return;
        }

        try {
            $this->authorize('update', $recommendation);

            $recommendation->update(['status' => 'rejected']);

            AiRecommendationAction::create([
                'team_id' => $team->id,
                'ai_recommendation_id' => $recommendation->id,
                'recommendation_hash' => sha1($recommendation->id.$recommendation->title),
                'recommendation' => ['title' => $recommendation->title, 'description' => $recommendation->description],
                'status' => 'rejected',
                'actioned_by' => auth()->id(),
                'actioned_at' => now(),
                'reject_reason' => $reason,
            ]);

            $this->dispatch('notify', ['type' => 'success', 'message' => 'Recommendation rejected.']);
            $this->dispatch('recommendation-updated', ['id' => $recommendation->id, 'status' => 'rejected']);
        } catch (AuthorizationException $e) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'You are not authorized to reject this recommendation.']);

            return;
        }
    }

    /**
     * @param  string  $action
     * @param  int|string  $recommendationId
     */
    public function promptRecommendationAction($recommendationId, $action): void
    {
        $this->pendingRecommendationId = (int) $recommendationId;
        $this->pendingRecommendationAction = $action;
        $this->showRecommendationConfirm = true;
    }

    public function confirmRecommendationAction(): void
    {
        if (! $this->pendingRecommendationId || ! in_array($this->pendingRecommendationAction, ['implement', 'reject'])) {
            $this->showRecommendationConfirm = false;
            $this->pendingRecommendationId = null;
            $this->pendingRecommendationAction = null;
            $this->rejectReason = '';

            return;
        }

        $id = $this->pendingRecommendationId;
        $action = $this->pendingRecommendationAction;

        if ($action === 'implement') {
            $this->implementRecommendation($id);
        } else {
            $this->rejectRecommendation($id, $this->rejectReason);
            if (trim($this->rejectReason) === '') {
                // rejectRecommendation() already surfaced the error notification; keep the dialog open.
                return;
            }
        }

        $this->showRecommendationConfirm = false;
        $this->pendingRecommendationId = null;
        $this->pendingRecommendationAction = null;
        $this->rejectReason = '';
        // Refresh pagination/list
        $this->resetPage();
    }

    public function cancelRecommendationAction(): void
    {
        $this->showRecommendationConfirm = false;
        $this->pendingRecommendationId = null;
        $this->pendingRecommendationAction = null;
        $this->rejectReason = '';
    }

    // (Possibly missing function for alert acknowledgement should be implemented here if needed)

    /**
     * @param  int|string  $insightId
     */
    public function markInsightAsRead($insightId): void
    {
        $team = auth()->user()->currentTeam;
        $insight = AIInsight::where('team_id', $team->id)->findOrFail($insightId);
        $this->authorize('update', $insight);
        $insight->markAsRead();
        $this->dispatch('notify', ['type' => 'success', 'message' => 'Insight marked as read.']);
    }

    public function render(): View
    {
        $team = auth()->user()->currentTeam;

        // Get dashboard data
        $aiService = $this->aiService;
        assert($aiService !== null);
        $dashboardData = $aiService->getDashboardInsights($team);

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
        ]);
    }
}
