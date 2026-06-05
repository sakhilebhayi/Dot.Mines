<?php

namespace App\Listeners;

use App\Events\FeedCommentCreated;
use App\Jobs\SendFeedNotificationJob;
use App\Models\UserFeedPreference;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendFeedCommentNotification implements ShouldQueue
{
    public string $queue = 'notifications';

    public function handle(FeedCommentCreated $event): void
    {
        $comment = $event->comment;
        $post = $event->post;
        $teamId = $post->team_id;
        $authorId = $comment->author_id;

        $notifyIds = [];

        // Notify post author (if not the commenter)
        if ($post->author_id && $post->author_id !== $authorId) {
            $notifyIds[] = $post->author_id;
        }

        // If this is a reply, also notify the parent comment author
        if ($comment->parent_comment_id) {
            $parent = $comment->parent;
            if ($parent && $parent->author_id && $parent->author_id !== $authorId) {
                $notifyIds[] = $parent->author_id;
            }
        }

        $notifyIds = array_unique(array_filter($notifyIds));

        if (empty($notifyIds)) {
            return;
        }

        // Apply per-user preferences (notify_on_comment / notify_on_reply)
        $filteredIds = $this->applyCommentPreferences($notifyIds, $teamId, $comment->parent_comment_id !== null);

        if (empty($filteredIds)) {
            return;
        }

        $authorName = $comment->author?->name ?? 'Someone';
        $isReply = $comment->parent_comment_id !== null;

        SendFeedNotificationJob::dispatch($filteredIds, [
            'team_id' => $teamId,
            'type' => $isReply ? 'feed_reply' : 'feed_comment',
            'title' => $isReply
                ? "{$authorName} replied to a comment"
                : "{$authorName} commented on a post",
            'message' => mb_substr($comment->body, 0, 200),
            'alert_level' => 'low',
            'data' => [
                'post_id' => $post->id,
                'comment_id' => $comment->id,
            ],
            'action_url' => '/feed',
        ]);
    }

    /**
     * @param  array<mixed>  $userIds
     * @return array<mixed>
     */
    private function applyCommentPreferences(array $userIds, int $teamId, bool $isReply): array
    {
        $prefs = UserFeedPreference::where('team_id', $teamId)
            ->whereIn('user_id', $userIds)
            ->get(['user_id', 'notify_on_comment', 'notify_on_reply'])
            ->keyBy('user_id');

        $field = $isReply ? 'notify_on_reply' : 'notify_on_comment';

        return array_values(array_filter($userIds, function (int $userId) use ($prefs, $field) {
            if (! $prefs->has($userId)) {
                return true; // default: opted in
            }

            return (bool) $prefs[$userId]->{$field};
        }));
    }
}
