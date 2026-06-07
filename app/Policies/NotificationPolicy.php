<?php

namespace App\Policies;

use App\Models\Notification;
use App\Models\User;

class NotificationPolicy
{
    /**
     * Determine if the user can view any notifications.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine if the user can view the notification.
     */
    public function view(User $user, Notification $notification): bool
    {
        $teamId = $user->currentTeam ? $user->currentTeam->id : $user->current_team_id;

        return $teamId === $notification->team_id;
    }

    /**
     * Determine if the user can update the notification.
     */
    public function update(User $user, Notification $notification): bool
    {
        $teamId = $user->currentTeam ? $user->currentTeam->id : $user->current_team_id;

        return $teamId === $notification->team_id;
    }

    /**
     * Determine if the user can delete the notification.
     */
    public function delete(User $user, Notification $notification): bool
    {
        $teamId = $user->currentTeam ? $user->currentTeam->id : $user->current_team_id;

        return $teamId === $notification->team_id;
    }
}
