<?php

namespace App\Policies;

use App\Models\AIRecommendation;
use App\Models\User;

class AIRecommendationPolicy
{
    /**
     * Determine whether the user can view any recommendations.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('view_recommendations');
    }

    /**
     * Determine whether the user can view the recommendation.
     */
    public function view(User $user, AIRecommendation $recommendation): bool
    {
        return $user->current_team_id === $recommendation->team_id &&
               ($user->hasPermission('view_recommendations') || $user->ownsTeam($recommendation->team));
    }

    /**
     * Determine whether the user can create recommendations.
     */
    public function create(User $user): bool
    {
        return $user->hasPermission('create_recommendations');
    }

    /**
     * Determine whether the user can update (implement/reject) the recommendation.
     */
    public function update(User $user, AIRecommendation $recommendation): bool
    {
        if ($user->current_team_id !== $recommendation->team_id) {
            return false;
        }

        // hasPermission() already grants every permission to the 'admin' role,
        // so the only case to add here is the team owner acting without an
        // 'admin' role assignment.
        return $user->hasPermission('update_recommendations') || $user->ownsTeam($recommendation->team);
    }

    /**
     * Determine whether the user can delete the recommendation.
     */
    public function delete(User $user, AIRecommendation $recommendation): bool
    {
        return $user->current_team_id === $recommendation->team_id &&
               $user->hasPermission('delete_recommendations');
    }
}
