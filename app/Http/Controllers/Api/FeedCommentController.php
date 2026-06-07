<?php

namespace App\Http\Controllers\Api;

use App\Events\FeedCommentCreated;
use App\Events\FeedCommentDeleted;
use App\Events\FeedCommentUpdated;
use App\Models\FeedComment;
use App\Models\FeedPost;
use App\Services\MentionParser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Feed Comment API Controller
 *
 * Handles comment creation, editing, deletion, and listing with nested replies.
 */
class FeedCommentController extends Controller
{
    /**
     * GET /api/feed/{post}/comments
     * Returns top-level comments with their replies eager-loaded.
     */
    public function index(FeedPost $post): JsonResponse
    {
        $this->authorize('view', $post);

        $comments = $post->comments()
            ->with(['author:id,name', 'replies.author:id,name'])
            ->orderBy('created_at')
            ->get();

        return response()->json(['data' => $comments]);
    }

    /**
     * POST /api/feed/{post}/comments
     */
    public function store(Request $request, FeedPost $post): JsonResponse
    {
        $this->authorize('view', $post);

        $validated = $request->validate([
            'body' => 'required|string|max:2000',
            'parent_comment_id' => [
                'nullable',
                'integer',
                'exists:feed_comments,id',
            ],
        ]);

        // Ensure the parent comment belongs to the same post and is top-level
        if (! empty($validated['parent_comment_id'])) {
            $parent = FeedComment::find($validated['parent_comment_id']);
            abort_if($parent->post_id !== $post->id, 422, 'Parent comment does not belong to this post.');
            abort_if($parent->parent_comment_id !== null, 422, 'Replies cannot be nested beyond one level.');
        }

        $comment = DB::transaction(function () use ($validated, $post) {
            $comment = FeedComment::create([
                'post_id' => $post->id,
                'parent_comment_id' => $validated['parent_comment_id'] ?? null,
                'author_id' => auth()->id(),
                'body' => $validated['body'],
            ]);

            // Only top-level comments increment the counter
            if ($comment->parent_comment_id === null) {
                $post->increment('comment_count');
            }

            return $comment;
        });

        $comment->load('author:id,name');
        $post->refresh();

        // Parse @mentions in comment
        app(MentionParser::class)->parseSave($comment, $comment->body, auth()->id(), $post->team_id);

        FeedCommentCreated::dispatch($comment, $post);

        return response()->json(['data' => $comment], 201);
    }

    /**
     * PUT /api/feed/comments/{comment}
     * Author-only, within 5-minute edit window.
     */
    public function update(Request $request, FeedComment $comment): JsonResponse
    {
        $this->authorize('update', $comment);

        $validated = $request->validate([
            'body' => 'required|string|max:2000',
        ]);

        $comment->update([
            'body' => $validated['body'],
            'is_edited' => true,
        ]);

        FeedCommentUpdated::dispatch($comment, $comment->post);

        return response()->json(['data' => $comment]);
    }

    /**
     * DELETE /api/feed/comments/{comment}
     */
    public function destroy(FeedComment $comment): JsonResponse
    {
        $this->authorize('delete', $comment);

        $post = $comment->post;

        DB::transaction(function () use ($comment, $post) {
            $commentId = $comment->id;

            $comment->delete();

            // Only top-level comment deletion adjusts the counter
            if ($comment->parent_comment_id === null) {
                $post->decrement('comment_count');
            }

            FeedCommentDeleted::dispatch($commentId, $post);
        });

        return response()->json(['message' => 'Comment deleted.']);
    }
}
