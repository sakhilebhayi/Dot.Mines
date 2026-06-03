<?php

namespace App\Http\Controllers\Api;

use App\Events\FeedAcknowledgementUpdated;
use App\Events\FeedPostCreated;
use App\Events\FeedPostLiked;
use App\Events\FeedPostStatusChanged;
use App\Models\AuditLog;
use App\Models\FeedAcknowledgement;
use App\Models\FeedApproval;
use App\Models\FeedLike;
use App\Models\FeedPost;
use App\Services\AuditService;
use App\Services\MentionParser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Feed API Controller
 *
 * Handles all feed post operations including CRUD, acknowledgements,
 * likes, attachments, and approval workflow.
 */
class FeedController extends Controller
{
    // ── List ──────────────────────────────────────────────────────────────────

    /**
     * GET /api/feed
     * Paginated post list, filterable by mine_area_id, category, shift, date, approval_status.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', FeedPost::class);

        $validated = $request->validate([
            'per_page' => 'nullable|integer|min:1|max:100',
            'mine_area_id' => 'nullable|integer|exists:mine_areas,id',
            'category' => ['nullable', Rule::in(FeedPost::CATEGORIES)],
            'shift' => ['nullable', Rule::in(FeedPost::SHIFTS)],
            'priority' => ['nullable', Rule::in(FeedPost::PRIORITIES)],
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'approval_status' => ['nullable', Rule::in(FeedApproval::STATUSES)],
            // Reconnection catch-up: return only posts created strictly after this ISO timestamp
            'since' => 'nullable|date',
        ]);

        $query = FeedPost::with(['author', 'mineArea', 'attachments', 'approval'])
            ->withCount([
                'acknowledgements as user_has_acknowledged' => function ($q) {
                    $q->where('user_id', Auth::id());
                },
                'likes as user_has_liked' => function ($q) {
                    $q->where('user_id', Auth::id());
                },
            ]);

        if ($request->filled('mine_area_id')) {
            $query->where('mine_area_id', $validated['mine_area_id']);
        }

        if ($request->filled('category')) {
            $query->where('category', $validated['category']);
        }

        if ($request->filled('shift')) {
            $query->where('shift', $validated['shift']);
        }

        if ($request->filled('priority')) {
            $query->where('priority', $validated['priority']);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $validated['date_from']);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $validated['date_to']);
        }

        if ($request->filled('since')) {
            $query->where('created_at', '>', $validated['since']);
        }

        if ($request->filled('approval_status')) {
            $query->whereHas('approval', function ($q) use ($validated) {
                $q->where('status', $validated['approval_status']);
            });
        }

        // Pinned critical posts float to the top
        $query->orderByDesc('is_pinned')
            ->orderByRaw("CASE WHEN priority = 'critical' THEN 0 WHEN priority = 'high' THEN 1 ELSE 2 END")
            ->orderByDesc('created_at');

        $perPage = $validated['per_page'] ?? 25;
        $posts = $query->paginate($perPage);

        return response()->json([
            'data' => $posts->items(),
            'pagination' => [
                'total' => $posts->total(),
                'per_page' => $posts->perPage(),
                'current_page' => $posts->currentPage(),
                'last_page' => $posts->lastPage(),
            ],
        ]);
    }

    // ── Create ────────────────────────────────────────────────────────────────

    /**
     * POST /api/feed
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', FeedPost::class);

        $base = $request->validate([
            'mine_area_id' => 'nullable|integer|exists:mine_areas,id',
            'shift' => ['nullable', Rule::in(FeedPost::SHIFTS)],
            'category' => ['required', Rule::in(FeedPost::CATEGORIES)],
            'priority' => ['nullable', Rule::in(FeedPost::PRIORITIES)],
            'body' => 'required|string|max:5000',
            'meta' => 'nullable|array',
        ]);

        // Category-specific meta validation
        $this->validateCategoryMeta($request, $base['category']);

        // Safety alerts are always critical
        if ($base['category'] === 'safety_alert') {
            $base['priority'] = 'critical';
        }

        $base['team_id'] = Auth::user()->current_team_id;
        $base['author_id'] = Auth::id();
        $base['priority'] = $base['priority'] ?? 'normal';

        $post = FeedPost::create($base);

        // Create a pending approval record for posts that require moderation
        FeedApproval::create([
            'post_id' => $post->id,
            'approver_id' => Auth::id(), // placeholder, updated when reviewed
            'status' => 'pending',
        ]);

        $post->load('author', 'mineArea', 'attachments', 'approval');

        // Parse @mentions after post is created
        app(MentionParser::class)->parseSave($post, $post->body, (int) Auth::id(), $post->team_id);

        FeedPostCreated::dispatch($post);

        AuditService::log(
            AuditLog::FEED_POST_CREATED,
            "Created feed post #{$post->id} (category: {$post->category}, priority: {$post->priority})",
            $post,
            ['category' => $post->category, 'priority' => $post->priority, 'mine_area_id' => $post->mine_area_id]
        );

        return response()->json(['data' => $post], 201);
    }

    // ── Delete ────────────────────────────────────────────────────────────────

    /**
     * DELETE /api/feed/{post}
     */
    public function destroy(FeedPost $post): JsonResponse
    {
        $this->authorize('delete', $post);

        AuditService::log(
            AuditLog::FEED_POST_DELETED,
            "Deleted feed post #{$post->id} (category: {$post->category})",
            $post,
            ['category' => $post->category, 'author_id' => $post->author_id]
        );

        $post->delete();

        return response()->json(['message' => 'Post deleted.']);
    }

    // ── Acknowledgements ──────────────────────────────────────────────────────

    /**
     * POST /api/feed/{post}/acknowledge
     */
    public function acknowledge(FeedPost $post): JsonResponse
    {
        $this->authorize('view', $post);

        $userId = Auth::id();

        $existing = FeedAcknowledgement::where('post_id', $post->id)
            ->where('user_id', $userId)
            ->first();

        if ($existing) {
            return response()->json(['message' => 'Already acknowledged.'], 422);
        }

        DB::transaction(function () use ($post, $userId) {
            FeedAcknowledgement::create([
                'post_id' => $post->id,
                'user_id' => $userId,
                'acknowledged_at' => now(),
            ]);
            $post->increment('acknowledgement_count');
        });

        $post->refresh();
        FeedAcknowledgementUpdated::dispatch($post);

        return response()->json([
            'message' => 'Acknowledged.',
            'acknowledgement_count' => $post->acknowledgement_count,
        ]);
    }

    /**
     * GET /api/feed/{post}/acknowledgements
     */
    public function acknowledgements(FeedPost $post): JsonResponse
    {
        $this->authorize('view', $post);

        $acks = $post->acknowledgements()->with('user:id,name')->get();

        return response()->json(['data' => $acks]);
    }

    // ── Attachments ───────────────────────────────────────────────────────────

    /**
     * POST /api/feed/{post}/attachments
     *
     * Stores the uploaded file directly in the database (no AWS dependency).
     * MIME type is verified server-side from file content.
     */
    public function storeAttachment(Request $request, FeedPost $post): JsonResponse
    {
        $this->authorize('update', $post);

        $request->validate([
            'file' => 'required|file|max:51200|mimes:jpeg,jpg,png,gif,webp,mp3,m4a,ogg,wav,pdf',
        ]);

        $file = $request->file('file');

        if (! $file instanceof \Illuminate\Http\UploadedFile) {
            return response()->json(['message' => 'Invalid file upload.'], 422);
        }

        try {
            $attachment = app(\App\Services\FeedAttachmentService::class)
                ->store($file, $post, $request->user());
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\RuntimeException $e) {
            report($e);

            return response()->json(['message' => 'Failed to store attachment. Please try again.'], 500);
        }

        // Return the attachment without file_data (hidden on model)
        // Include the routable url via the accessor
        return response()->json([
            'data' => array_merge($attachment->toArray(), ['url' => $attachment->url]),
        ], 201);
    }

    // ── Likes ─────────────────────────────────────────────────────────────────

    /**
     * POST /api/feed/{post}/like  — toggles like/unlike
     */
    public function like(FeedPost $post): JsonResponse
    {
        $this->authorize('view', $post);

        $userId = Auth::id();

        $like = FeedLike::where('post_id', $post->id)->where('user_id', $userId)->first();

        DB::transaction(function () use ($post, $userId, $like) {
            if ($like) {
                $like->delete();
                $post->decrement('like_count');
            } else {
                FeedLike::create([
                    'post_id' => $post->id,
                    'user_id' => $userId,
                    'liked_at' => now(),
                ]);
                $post->increment('like_count');
            }
        });

        $post->refresh();
        FeedPostLiked::dispatch($post);

        return response()->json([
            'liked' => ! $like,
            'like_count' => $post->like_count,
        ]);
    }

    /**
     * GET /api/feed/{post}/likes
     */
    public function likes(FeedPost $post): JsonResponse
    {
        $this->authorize('view', $post);

        return response()->json([
            'data' => $post->likes()->with('user:id,name')->get(),
        ]);
    }

    // ── Approvals ─────────────────────────────────────────────────────────────

    /**
     * POST /api/feed/{post}/approve
     */
    public function approve(FeedPost $post): JsonResponse
    {
        $this->authorize('approve', $post);

        $approval = $post->approval ?? FeedApproval::create([
            'post_id' => $post->id,
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

        AuditService::log(
            AuditLog::FEED_POST_APPROVED,
            "Approved feed post #{$post->id}",
            $post,
            ['approver_id' => Auth::id()]
        );

        return response()->json(['data' => $approval]);
    }

    /**
     * POST /api/feed/{post}/reject
     */
    public function reject(Request $request, FeedPost $post): JsonResponse
    {
        $this->authorize('approve', $post);

        $validated = $request->validate([
            'reason' => 'required|string|max:1000',
        ]);

        $approval = $post->approval ?? FeedApproval::create([
            'post_id' => $post->id,
            'approver_id' => Auth::id(),
            'status' => 'pending',
        ]);

        $approval->update([
            'approver_id' => Auth::id(),
            'status' => 'rejected',
            'reason' => $validated['reason'],
            'reviewed_at' => now(),
        ]);

        $approval->refresh();
        FeedPostStatusChanged::dispatch($post, $approval);

        AuditService::log(
            AuditLog::FEED_POST_REJECTED,
            "Rejected feed post #{$post->id}",
            $post,
            ['approver_id' => Auth::id(), 'reason' => $validated['reason']]
        );

        return response()->json(['data' => $approval]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function validateCategoryMeta(Request $request, string $category): void
    {
        match ($category) {
            'breakdown' => $request->validate([
                'meta.machine_id' => 'required|string|max:100',
                'meta.failure_type' => 'required|string|max:255',
                'meta.estimated_downtime' => 'required|string|max:100',
            ]),
            'shift_update' => $request->validate([
                'meta.section' => 'required|string|max:100',
                'meta.shift' => ['required', Rule::in(FeedPost::SHIFTS)],
                'meta.loads_per_hour' => 'required|numeric|min:0',
            ]),
            default => null,
        };
    }
}
