<?php

namespace App\Listeners;

use App\Events\FeedPostCreated;
use App\Jobs\SendFeedNotificationJob;
use App\Models\Role;
use App\Models\User;
use App\Models\UserFeedPreference;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendFeedPostNotification implements ShouldQueue
{
    public string $queue = 'notifications';

    public function handle(FeedPostCreated $event): void
    {
        $post = $event->post;
        $teamId = $post->team_id;

        // Determine which users to notify based on category / priority
        $recipientIds = $this->resolveRecipients($post, $teamId);

        if (empty($recipientIds)) {
            return;
        }

        // Map category / priority to alert level
        $alertLevel = match ($post->priority) {
            'critical' => 'critical',
            'high' => 'high',
            default => 'medium',
        };

        $categoryLabel = ucfirst(str_replace('_', ' ', $post->category));

        SendFeedNotificationJob::dispatch($recipientIds, [
            'team_id' => $teamId,
            'type' => 'feed_post',
            'title' => "[{$categoryLabel}] New post by {$post->author?->name}",
            'message' => mb_substr($post->body, 0, 200),
            'alert_level' => $alertLevel,
            'data' => [
                'post_id' => $post->id,
                'category' => $post->category,
                'priority' => $post->priority,
            ],
            'action_url' => '/feed',
        ]);
    }

    /**
     * @return array<mixed>
     */
    private function resolveRecipients($post, int $teamId): array
    {
        // All team users
        $allUserIds = User::whereHas('teams', fn ($q) => $q->where('teams.id', $teamId))
            ->pluck('id')
            ->toArray();

        // Filter by category / priority rules
        if ($post->priority === 'critical' || $post->category === 'safety_alert') {
            // Notify ALL team users
            $targetIds = $allUserIds;
        } elseif ($post->category === 'breakdown') {
            // Notify users with maintenance role
            $targetIds = User::whereHas('roles', fn ($q) => $q
                ->where('team_id', $teamId)
                ->where('name', 'maintenance')
            )->pluck('id')->toArray();
        } elseif ($post->category === 'safety_alert') {
            // Already handled above: safety_alert always notifies all
            $targetIds = $allUserIds;
        } else {
            // For other categories, only notify if user has opted in
            $targetIds = $allUserIds;
        }

        // Exclude the author themselves
        $targetIds = array_filter($targetIds, fn ($id) => $id !== $post->author_id);

        // Apply per-user notification preferences
        return $this->applyPreferences($targetIds, $teamId, $post->category);
    }

    /**
     * @param  array<mixed>  $userIds
     * @return array<mixed>
     */
    private function applyPreferences(array $userIds, int $teamId, string $category): array
    {
        if (empty($userIds)) {
            return [];
        }

        // Load preferences for these users
        $prefs = UserFeedPreference::where('team_id', $teamId)
            ->whereIn('user_id', $userIds)
            ->pluck('category_preferences', 'user_id');

        return array_values(array_filter($userIds, function (int $userId) use ($prefs, $category) {
            if (! $prefs->has($userId)) {
                return true; // default: opted in
            }
            $userPrefs = $prefs[$userId] ?? [];

            return (bool) ($userPrefs[$category] ?? true);
        }));
    }
}
