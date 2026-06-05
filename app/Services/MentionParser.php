<?php

namespace App\Services;

use App\Models\FeedMention;
use App\Models\Notification;
use App\Models\User;
use App\Models\UserFeedPreference;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class MentionParser
{
    /**
     * Extract @username handles from a body string.
     * Returns an array of raw handles (without the @ sign).
     *
     * @return array<mixed>
     */
    public function extractHandles(string $body): array
    {
        preg_match_all('/@([\w.-]+)/', $body, $matches);

        return array_unique($matches[1]);
    }

    /**
     * Resolve @username handles to User models on the given team.
     * Matches on the `name` field (case-insensitive).
     *
     * @param  array<string>  $handles
     * @return Collection<int, User>
     */
    public function resolveUsers(array $handles, int $teamId): Collection
    {
        if (empty($handles)) {
            return collect();
        }

        // Build LOWER(name) patterns
        return User::whereHas('teams', fn ($q) => $q->where('teams.id', $teamId))
            ->where(function ($q) use ($handles) {
                foreach ($handles as $handle) {
                    // Match @first.last or @FirstName (replace dots/dashes with spaces for name lookup)
                    $q->orWhereRaw("LOWER(REPLACE(name, ' ', '.')) = ?", [strtolower($handle)])
                        ->orWhereRaw("LOWER(REPLACE(name, ' ', '-')) = ?", [strtolower($handle)])
                        ->orWhereRaw('LOWER(name) = ?', [strtolower(str_replace(['.', '-'], ' ', $handle))]);
                }
            })
            ->get(['id', 'name', 'email']);
    }

    /**
     * Parse mentions in a body, persist FeedMention records, and create
     * in-app notifications for each mentioned user.
     *
     * @param  Model  $mentionable  FeedPost or FeedComment instance
     * @param  string  $body  The post/comment body
     * @param  int  $authorId  The user who authored the content
     * @param  int  $teamId  The current team
     */
    public function parseSave(Model $mentionable, string $body, int $authorId, int $teamId): void
    {
        try {
            $handles = $this->extractHandles($body);

            if (empty($handles)) {
                return;
            }

            $users = $this->resolveUsers($handles, $teamId);

            foreach ($users as $user) {
                // Don't notify the author about their own mention
                if ($user->id === $authorId) {
                    continue;
                }

                // Persist mention record (skip duplicate)
                FeedMention::firstOrCreate([
                    'mentionable_type' => get_class($mentionable),
                    'mentionable_id' => $mentionable->id,
                    'mentioned_user_id' => $user->id,
                    'mentioned_by_user_id' => $authorId,
                    'team_id' => $teamId,
                ]);

                // Check user preference for mention notifications
                $pref = UserFeedPreference::where('user_id', $user->id)
                    ->where('team_id', $teamId)
                    ->first();

                if ($pref && ! $pref->notify_on_mention) {
                    continue;
                }

                // Create in-app notification
                Notification::create([
                    'team_id' => $teamId,
                    'type' => 'feed_mention',
                    'title' => 'You were mentioned',
                    'message' => 'Someone mentioned you in a post.',
                    'alert_level' => 'medium',
                    'data' => [
                        'mentionable_type' => get_class($mentionable),
                        'mentionable_id' => $mentionable->id,
                    ],
                    'action_url' => '/feed',
                    'is_read' => false,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('MentionParser failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Render @username mentions in a body string as highlighted HTML spans.
     * Safe: the body should already be HTML-escaped before passing here.
     */
    public function highlight(string $escapedBody): string
    {
        return preg_replace(
            '/@([\w.-]+)/',
            '<span class="text-blue-400 font-medium">@$1</span>',
            $escapedBody
        ) ?? $escapedBody;
    }
}
