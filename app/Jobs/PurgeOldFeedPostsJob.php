<?php

namespace App\Jobs;

use App\Models\FeedComment;
use App\Models\FeedPost;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Permanently deletes soft-deleted feed posts and comments older than FEED_RETENTION_DAYS (default 90).
 */
class PurgeOldFeedPostsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 300;

    /** @var array<int> */
    public array $backoff = [60, 300];

    public function handle(): void
    {
        $retentionDays = (int) env('FEED_RETENTION_DAYS', 90);
        $cutoff = now()->subDays($retentionDays);

        $deletedComments = FeedComment::onlyTrashed()
            ->where('deleted_at', '<', $cutoff)
            ->forceDelete();

        $deletedPosts = FeedPost::onlyTrashed()
            ->where('deleted_at', '<', $cutoff)
            ->forceDelete();

        Log::info('PurgeOldFeedPostsJob completed', [
            'cutoff' => $cutoff->toDateString(),
            'deleted_posts' => $deletedPosts,
            'deleted_comments' => $deletedComments,
        ]);
    }
}
