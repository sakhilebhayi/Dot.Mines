<?php

namespace App\Policies;

use App\Models\FeedItem;
use App\Models\User;

/**
 * Who may read, write, pin and remove feed items.
 *
 * System items have no author and are never edited by anyone -- they are a
 * record of something that happened. People manage only posts: their own,
 * or any post if they hold the pin permission (the same trust level that
 * curates what the whole team sees).
 */
class FeedItemPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('view_feed');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('post_feed');
    }

    public function pin(User $user, FeedItem $item): bool
    {
        return $user->current_team_id === $item->team_id
            && $user->hasPermission('pin_feed');
    }

    public function delete(User $user, FeedItem $item): bool
    {
        if ($user->current_team_id !== $item->team_id || $item->isSystem()) {
            return false;
        }

        return $item->user_id === $user->id || $user->hasPermission('pin_feed');
    }
}
