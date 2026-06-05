<?php

namespace App\Livewire;

use App\Events\FeedAcknowledgementUpdated;
use App\Events\FeedCommentCreated;
use App\Events\FeedCommentDeleted;
use App\Events\FeedCommentUpdated;
use App\Events\FeedPostCreated;
use App\Events\FeedPostLiked;
use App\Events\FeedPostStatusChanged;
use App\Models\FeedAcknowledgement;
use App\Models\FeedApproval;
use App\Models\FeedAuditLog;
use App\Models\FeedComment;
use App\Models\FeedLike;
use App\Models\FeedPost;
use App\Models\MachineMetric;
use App\Models\MineArea;
use App\Models\ProductionRecord;
use App\Models\ShiftTemplate;
use App\Models\User;
use App\Services\FeedAttachmentService;
use App\Services\MentionParser;
use App\Traits\RealtimeUpdates;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class Feed extends Component
{
    use RealtimeUpdates, WithFileUploads, WithPagination;

    // ── Filter state ──────────────────────────────────────────────────────────
    public string $filterCategory = 'all';

    public string $filterSection = 'all';

    public string $filterShift = 'all';

    public string $filterPriority = 'all';

    public string $filterApproval = 'all';

    public string $filterDateFrom = '';

    public string $filterDateTo = '';

    // ── Compose state─────────────────────────────────────────────────────────
    public bool $showCompose = false;

    public string $composeCategory = '';

    public string $composeBody = '';

    public string $composeShift = '';

    public ?int $composeMineAreaId = null;

    public string $composePriority = 'normal';

    /** @var array<string, mixed> */
    public array $composeMeta = [];

    /** @var array<mixed> */
    public array $composeAttachments = [];   // uploaded files (Livewire temp)

    /** @var array<string, mixed> */
    public array $categoryTemplates = [];   // templates for current category

    // ── Comment state ─────────────────────────────────────────────────────────
    /** @var array<array-key, mixed> */
    public array $expandedComments = [];   // post IDs with comments open

    /** @var array<array-key, mixed> */
    public array $commentBody = [];   // [post_id => text]

    /** @var array<array-key, mixed> */
    public array $replyTo = [];   // [post_id => comment_id]

    /** @var array<array-key, mixed> */
    public array $editingComment = [];   // [comment_id => text]

    // ── Rejection modal ───────────────────────────────────────────────────────
    public bool $showRejectModal = false;

    public ?int $rejectPostId = null;

    public string $rejectReason = '';

    /**
     * @return array<mixed>
     */
    protected function rules(): array
    {
        return [
            'composeCategory' => 'required|in:breakdown,shift_update,safety_alert,production,general',
            'composeBody' => 'required|string|max:5000',
            'composeShift' => 'nullable|in:A,B,C',
            'composeMineAreaId' => 'nullable|integer|exists:mine_areas,id',
            'composePriority' => 'required|in:normal,high,critical',
            'composeMeta' => 'nullable|array',
            'composeAttachments.*' => 'nullable|file|max:51200|mimes:jpeg,jpg,png,gif,webp,mp3,m4a,ogg,wav,pdf',
        ];
    }

    // ── Mount ─────────────────────────────────────────────────────────────────

    public function mount(): void
    {
        $this->initializeRealtimeUpdates();
        $this->subscribeToFeed();
        /** @var User $user */
        $user = Auth::user();
        $this->composeShift = $this->detectCurrentShift();
        $this->composeMineAreaId = $user->currentTeam?->mineAreas()->first()?->id;

        // ── Auto welcome post: create once when a new team has no posts ───────
        if ($user->hasRole('admin') && FeedPost::count() === 0) {
            $post = FeedPost::create([
                'team_id' => $user->current_team_id,
                'author_id' => $user->id,
                'category' => 'general',
                'priority' => 'normal',
                'body' => "👋 Welcome to the Mine Operations Feed!\n\nThis is your team's real-time activity stream. Use it to:\n• Report equipment breakdowns with structured details\n• Post shift updates (loads/hour, tonnage, headcount)\n• Share safety alerts that auto-notify the whole team\n• Log production updates and general operational notes\n\nPosts require approval before they're visible to the full team. Supervisors and managers can approve or reject from this feed.\n\nGet started by clicking **New Post** above. 🚀",
            ]);
            FeedApproval::create(['post_id' => $post->id, 'approver_id' => $user->id, 'status' => 'approved', 'reviewed_at' => now()]);
        }
    }

    // ── Computed data ─────────────────────────────────────────────────────────

    public function getPosts(): mixed
    {
        $user = Auth::user();

        $query = FeedPost::with(['author', 'mineArea', 'attachments', 'approval'])
            ->withCount([
                'acknowledgements as user_has_acknowledged' => fn ($q) => $q->where('user_id', $user->id),
                'likes as user_has_liked' => fn ($q) => $q->where('user_id', $user->id),
            ]);

        if ($this->filterCategory !== 'all') {
            $query->where('category', $this->filterCategory);
        }

        if ($this->filterSection !== 'all') {
            $query->where('mine_area_id', $this->filterSection);
        }

        if ($this->filterShift !== 'all') {
            $query->where('shift', $this->filterShift);
        }

        if ($this->filterPriority !== 'all') {
            $query->where('priority', $this->filterPriority);
        }

        if ($this->filterApproval !== 'all') {
            $query->whereHas('approval', fn ($q) => $q->where('status', $this->filterApproval));
        }

        if ($this->filterDateFrom) {
            $query->whereDate('created_at', '>=', $this->filterDateFrom);
        }

        if ($this->filterDateTo) {
            $query->whereDate('created_at', '<=', $this->filterDateTo);
        }

        return $query
            ->orderByDesc('is_pinned')
            ->orderByRaw("CASE WHEN priority = 'critical' THEN 0 WHEN priority = 'high' THEN 1 ELSE 2 END")
            ->orderByDesc('created_at')
            ->paginate(20);
    }

    public function getMineAreas(): mixed
    {
        return MineArea::orderBy('name')->get(['id', 'name']);
    }

    // ── Compose ───────────────────────────────────────────────────────────────

    public function openCompose(): void
    {
        $this->showCompose = true;
        $this->resetCompose();
    }

    public function closeCompose(): void
    {
        $this->showCompose = false;
        $this->resetCompose();
    }

    public function submitPost(): void
    {
        $this->validateOnly('composeCategory,composeBody,composeShift,composeMineAreaId,composePriority');

        // Safety alerts are always critical
        if ($this->composeCategory === 'safety_alert') {
            $this->composePriority = 'critical';
        }

        /** @var User $user */
        $user = Auth::user();

        $post = DB::transaction(function () use ($user) {
            $post = FeedPost::create([
                'team_id' => $user->current_team_id,
                'author_id' => $user->id,
                'mine_area_id' => $this->composeMineAreaId,
                'shift' => $this->composeShift ?: null,
                'category' => $this->composeCategory,
                'priority' => $this->composePriority,
                'body' => $this->composeBody,
                'meta' => $this->composeMeta ?: null,
            ]);

            // Create pending approval record
            FeedApproval::create([
                'post_id' => $post->id,
                'approver_id' => $user->id,
                'status' => 'pending',
            ]);

            // Handle file attachments — stored in DB, not AWS
            $attachmentService = app(FeedAttachmentService::class);
            foreach ($this->composeAttachments as $file) {
                try {
                    $attachmentService->store($file, $post, $user);
                } catch (\InvalidArgumentException $e) {
                    // Surface validation errors back to the UI without rolling back the post
                    $this->addError('composeAttachments', $e->getMessage());
                } catch (\RuntimeException $e) {
                    $this->addError('composeAttachments', 'One or more files could not be saved. Please try again.');
                }
            }

            return $post;
        });

        $post->load('author', 'mineArea', 'attachments', 'approval');

        // Parse @mentions
        app(MentionParser::class)->parseSave($post, $post->body, $user->id, $post->team_id);

        FeedPostCreated::dispatch($post);

        $this->closeCompose();
        $this->resetPage();
        $this->dispatch('notify', type: 'success', message: 'Post published.');
    }

    // ── Acknowledgement ───────────────────────────────────────────────────────

    public function acknowledge(int $postId): void
    {
        $userId = Auth::id();

        $already = FeedAcknowledgement::where('post_id', $postId)
            ->where('user_id', $userId)
            ->exists();

        if ($already) {
            return;
        }

        DB::transaction(function () use ($postId, $userId) {
            FeedAcknowledgement::create([
                'post_id' => $postId,
                'user_id' => $userId,
                'acknowledged_at' => now(),
            ]);
            FeedPost::find($postId)->increment('acknowledgement_count');
        });

        $post = FeedPost::find($postId);
        FeedAcknowledgementUpdated::dispatch($post);
    }

    // ── Like (toggle) ─────────────────────────────────────────────────────────

    public function toggleLike(int $postId): void
    {
        $userId = Auth::id();

        $like = FeedLike::where('post_id', $postId)->where('user_id', $userId)->first();

        DB::transaction(function () use ($postId, $userId, $like) {
            if ($like) {
                $like->delete();
                FeedPost::find($postId)->decrement('like_count');
            } else {
                FeedLike::create(['post_id' => $postId, 'user_id' => $userId, 'liked_at' => now()]);
                FeedPost::find($postId)->increment('like_count');
            }
        });

        $post = FeedPost::find($postId);
        FeedPostLiked::dispatch($post);
    }

    // ── Comments ──────────────────────────────────────────────────────────────

    public function toggleComments(int $postId): void
    {
        if (in_array($postId, $this->expandedComments)) {
            $this->expandedComments = array_values(array_filter(
                $this->expandedComments,
                fn ($id) => $id !== $postId,
            ));
        } else {
            $this->expandedComments[] = $postId;
        }
    }

    public function getComments(int $postId): mixed
    {
        return FeedComment::with(['author:id,name', 'replies.author:id,name'])
            ->where('post_id', $postId)
            ->whereNull('parent_comment_id')
            ->orderBy('created_at')
            ->get();
    }

    public function submitComment(int $postId): void
    {
        $body = trim($this->commentBody[$postId] ?? '');

        if (empty($body)) {
            return;
        }

        $parentId = $this->replyTo[$postId] ?? null;

        if ($parentId) {
            $parent = FeedComment::find($parentId);
            if (! $parent || $parent->post_id !== $postId || $parent->parent_comment_id !== null) {
                $parentId = null;
            }
        }

        $comment = DB::transaction(function () use ($postId, $body, $parentId) {
            $comment = FeedComment::create([
                'post_id' => $postId,
                'parent_comment_id' => $parentId,
                'author_id' => Auth::id(),
                'body' => $body,
            ]);

            if ($parentId === null) {
                FeedPost::find($postId)->increment('comment_count');
            }

            return $comment;
        });

        $comment->load('author:id,name');
        $post = FeedPost::find($postId);
        $post->refresh();

        // Parse @mentions in comment
        app(MentionParser::class)->parseSave($comment, $comment->body, (int) Auth::id(), (int) $post->team_id);

        FeedCommentCreated::dispatch($comment, $post);

        $this->commentBody[$postId] = '';
        $this->replyTo[$postId] = null;
    }

    public function startEditComment(int $commentId): void
    {
        $comment = FeedComment::find($commentId);
        if ($comment && $comment->isEditableBy(Auth::user())) {
            $this->editingComment[$commentId] = $comment->body;
        }
    }

    public function saveEditComment(int $commentId): void
    {
        $body = trim($this->editingComment[$commentId] ?? '');

        if (empty($body)) {
            return;
        }

        $comment = FeedComment::find($commentId);

        if (! $comment || ! $comment->isEditableBy(Auth::user())) {
            $this->dispatch('notify', type: 'error', message: 'Comment can no longer be edited.');

            return;
        }

        $comment->update(['body' => $body, 'is_edited' => true]);

        FeedCommentUpdated::dispatch($comment, $comment->post);

        unset($this->editingComment[$commentId]);
    }

    public function deleteComment(int $commentId): void
    {
        $comment = FeedComment::find($commentId);

        if (! $comment) {
            return;
        }

        $this->authorize('delete', $comment);

        $post = $comment->post;
        $isTopLevel = $comment->parent_comment_id === null;

        DB::transaction(function () use ($comment, $post, $isTopLevel) {
            $commentId = $comment->id;
            $comment->delete();

            if ($isTopLevel) {
                $post->decrement('comment_count');
            }

            FeedCommentDeleted::dispatch($commentId, $post);
        });
    }

    // ── Approvals ─────────────────────────────────────────────────────────────

    public function approvePost(int $postId): void
    {
        $post = FeedPost::find($postId);
        $this->authorize('approve', $post);

        $approval = $post->approval ?? FeedApproval::create([
            'post_id' => $postId,
            'approver_id' => Auth::id(),
            'status' => 'pending',
        ]);

        $approval->update([
            'approver_id' => Auth::id(),
            'status' => 'approved',
            'reason' => null,
            'reviewed_at' => now(),
        ]);

        $approval->refresh();
        FeedPostStatusChanged::dispatch($post, $approval);

        $this->dispatch('notify', type: 'success', message: 'Post approved.');
    }

    public function openRejectModal(int $postId): void
    {
        $this->rejectPostId = $postId;
        $this->rejectReason = '';
        $this->showRejectModal = true;
    }

    public function submitRejection(): void
    {
        $this->validate(['rejectReason' => 'required|string|max:1000']);

        $post = FeedPost::find($this->rejectPostId);
        $this->authorize('approve', $post);

        $approval = $post->approval ?? FeedApproval::create([
            'post_id' => $this->rejectPostId,
            'approver_id' => Auth::id(),
            'status' => 'pending',
        ]);

        $approval->update([
            'approver_id' => Auth::id(),
            'status' => 'rejected',
            'reason' => $this->rejectReason,
            'reviewed_at' => now(),
        ]);

        $approval->refresh();
        FeedPostStatusChanged::dispatch($post, $approval);

        $this->showRejectModal = false;
        $this->rejectPostId = null;
        $this->rejectReason = '';

        $this->dispatch('notify', type: 'success', message: 'Post rejected.');
    }

    // ── Templates ─────────────────────────────────────────────────────────────

    public function updatedComposeCategory(string $value): void
    {
        if ($value) {
            $this->categoryTemplates = ShiftTemplate::where('category', $value)
                ->orderBy('title')
                ->get(['id', 'title', 'template_body', 'required_fields'])
                ->toArray();
        } else {
            $this->categoryTemplates = [];
        }
    }

    public function applyTemplate(int $templateId): void
    {
        $template = ShiftTemplate::find($templateId);

        if (! $template) {
            return;
        }

        $this->composeBody = $template->template_body;
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function resetCompose(): void
    {
        $this->composeCategory = '';
        $this->composeBody = '';
        $this->composeShift = $this->detectCurrentShift();
        $this->composeMineAreaId = Auth::user()->currentTeam?->mineAreas()->first()?->id;
        $this->composePriority = 'normal';
        $this->composeMeta = [];
        $this->composeAttachments = [];
        $this->categoryTemplates = [];
    }

    private function detectCurrentShift(): string
    {
        $hour = (int) now()->format('H');

        return match (true) {
            $hour >= 6 && $hour < 14 => 'A',
            $hour >= 14 && $hour < 22 => 'B',
            default => 'C',
        };
    }

    public function canApprove(): bool
    {
        /** @var User $user */
        $user = Auth::user();

        return $user->hasRole(['admin', 'supervisor', 'manager', 'safety_officer']);
    }

    public function isAdmin(): bool
    {
        /** @var User $user */
        $user = Auth::user();

        return $user->hasRole('admin');
    }

    // ── Admin: pin / unpin ────────────────────────────────────────────────────

    public function pinPost(int $postId): void
    {
        abort_if(! $this->isAdmin(), 403);
        $post = FeedPost::findOrFail($postId);
        $post->update(['is_pinned' => true]);
        FeedAuditLog::record('pin', $post, ['body_preview' => Str::limit($post->body, 80)]);
        $this->dispatch('notify', type: 'success', message: 'Post pinned.');
    }

    public function unpinPost(int $postId): void
    {
        abort_if(! $this->isAdmin(), 403);
        $post = FeedPost::findOrFail($postId);
        $post->update(['is_pinned' => false]);
        FeedAuditLog::record('unpin', $post);
        $this->dispatch('notify', type: 'success', message: 'Post unpinned.');
    }

    // ── Admin: force-delete with audit trail ──────────────────────────────────

    public function adminDeletePost(int $postId): void
    {
        abort_if(! $this->isAdmin(), 403);
        $post = FeedPost::findOrFail($postId);
        FeedAuditLog::record('admin_delete', $post, [
            'category' => $post->category,
            'author_id' => $post->author_id,
            'body_preview' => Str::limit($post->body, 120),
        ]);
        $post->delete();
        $this->dispatch('notify', type: 'success', message: 'Post deleted.');
    }

    // ── Admin: override approval ──────────────────────────────────────────────

    public function overrideApproval(int $postId, string $status): void
    {
        abort_if(! $this->isAdmin(), 403);
        abort_if(! in_array($status, ['approved', 'rejected'], true), 422);

        $post = FeedPost::findOrFail($postId);
        $approval = $post->approval ?? FeedApproval::create([
            'post_id' => $postId,
            'approver_id' => Auth::id(),
            'status' => 'pending',
        ]);

        $approval->update([
            'approver_id' => Auth::id(),
            'status' => $status,
            'reason' => $status === 'rejected' ? '[Admin override]' : null,
            'reviewed_at' => now(),
        ]);

        FeedAuditLog::record('override_approval', $post, ['new_status' => $status]);
        $approval->refresh();
        FeedPostStatusChanged::dispatch($post, $approval);
        $this->dispatch('notify', type: 'success', message: 'Approval overridden to '.$status.'.');
    }

    public function updatingFilterCategory(): void
    {
        $this->resetPage();
    }

    public function updatingFilterSection(): void
    {
        $this->resetPage();
    }

    public function updatingFilterShift(): void
    {
        $this->resetPage();
    }

    public function updatingFilterPriority(): void
    {
        $this->resetPage();
    }

    public function updatingFilterApproval(): void
    {
        $this->resetPage();
    }

    public function updatingFilterDateFrom(): void
    {
        $this->resetPage();
    }

    public function updatingFilterDateTo(): void
    {
        $this->resetPage();
    }

    /**
     * Called by JS (via `feed:reconnected` dispatch) when the WebSocket
     * reconnects and missed posts have been detected. Reset pagination so
     * the freshest posts are visible at the top of the feed.
     */
    #[On('feed:reconnected')]
    public function onFeedReconnected(): void
    {
        $this->resetPage();
    }

    // ── Daily Production Statistics ───────────────────────────────────────────

    /**
     * @return array<mixed>
     */
    public function getDailyProductionStats(): array
    {
        /** @var User $user */
        $user = Auth::user();
        $teamId = (int) $user->current_team_id;
        $today = Carbon::today();

        // Today's production records for this team
        $records = ProductionRecord::where('team_id', $teamId)
            ->where('record_date', $today)
            ->with('machine:id,name,machine_type')
            ->get();

        $totalLoads = $records->count();
        $totalTonnage = round((float) $records->sum('quantity_produced'), 2);
        $totalTarget = round((float) $records->sum('target_quantity'), 2);
        $achievement = $totalTarget > 0 ? round(($totalTonnage / $totalTarget) * 100, 1) : null;

        // Sensor-based cycle count for today
        $totalCycles = MachineMetric::where('team_id', $teamId)
            ->whereBetween('recorded_at', [$today->copy()->startOfDay(), $today->copy()->endOfDay()])
            ->where('load_weight', '>', 0)
            ->count();

        // Best performing trucks: top 5 machines by tonnage today
        $bestTrucks = $records
            ->whereNotNull('machine_id')
            ->groupBy('machine_id')
            ->map(function ($machineRecords) {
                /** @var ProductionRecord $first */
                $first = $machineRecords->first();
                $machine = $first->machine;

                return [
                    'name' => $machine?->name ?? 'Unknown',
                    'type' => $machine?->machine_type ?? '',
                    'tonnage' => round((float) $machineRecords->sum('quantity_produced'), 2),
                    'loads' => $machineRecords->count(),
                ];
            })
            ->sortByDesc('tonnage')
            ->take(5)
            ->values()
            ->toArray();

        // Current shift detection
        $hour = now()->hour;
        $currentShift = match (true) {
            $hour >= 6 && $hour < 18 => 'Day',
            default => 'Night',
        };

        $shiftRecords = $records->where('shift', strtolower($currentShift));
        $shiftTonnage = round((float) $shiftRecords->sum('quantity_produced'), 2);
        $shiftLoads = $shiftRecords->count();

        return [
            'total_loads' => $totalLoads,
            'total_cycles' => $totalCycles,
            'total_tonnage' => $totalTonnage,
            'total_target' => $totalTarget,
            'achievement' => $achievement,
            'best_trucks' => $bestTrucks,
            'current_shift' => $currentShift,
            'shift_tonnage' => $shiftTonnage,
            'shift_loads' => $shiftLoads,
            'as_of' => now()->format('H:i'),
        ];
    }

    // ── Render ────────────────────────────────────────────────────────────────

    public function render(): View
    {
        return view('livewire.feed', [
            'posts' => $this->getPosts(),
            'mineAreas' => $this->getMineAreas(),
            'canApprove' => $this->canApprove(),
            'dailyStats' => $this->getDailyProductionStats(),
        ]);
    }
}
