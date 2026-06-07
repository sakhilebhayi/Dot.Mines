<?php

namespace App\Listeners;

use App\Events\FeedPostStatusChanged;
use App\Jobs\SendFeedNotificationJob;
use App\Models\UserFeedPreference;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendFeedApprovalNotification implements ShouldQueue
{
    public string $queue = 'notifications';

    public function handle(FeedPostStatusChanged $event): void
    {
        $post = $event->post;
        $approval = $event->approval;
        $teamId = $post->team_id;

        // Only notify on final decisions (approved / rejected)
        if (! in_array($approval->status, ['approved', 'rejected'])) {
            return;
        }

        // Only notify the post author
        if (! $post->author_id || $post->author_id === $approval->approver_id) {
            return;
        }

        // Check user preference for approval notifications
        $pref = UserFeedPreference::where('user_id', $post->author_id)
            ->where('team_id', $teamId)
            ->first();

        if ($pref && ! $pref->notify_on_approval) {
            return;
        }

        $status = $approval->status === 'approved' ? 'approved ✓' : 'rejected ✗';
        $alertLevel = $approval->status === 'approved' ? 'medium' : 'high';

        SendFeedNotificationJob::dispatch([$post->author_id], [
            'team_id' => $teamId,
            'type' => 'feed_approval',
            'title' => "Your post was {$status}",
            'message' => $approval->reason
                ? "Reason: {$approval->reason}"
                : mb_substr($post->body, 0, 150),
            'alert_level' => $alertLevel,
            'data' => [
                'post_id' => $post->id,
                'status' => $approval->status,
                'reason' => $approval->reason,
            ],
            'action_url' => '/feed',
        ]);
    }
}
