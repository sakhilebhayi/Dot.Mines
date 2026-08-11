<?php

namespace App\Policies;

use App\Models\MineArea;
use App\Models\User;

class MineAreaPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('view_mine_areas');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, MineArea $mineArea): bool
    {
        return $user->current_team_id === $mineArea->team_id &&
               $user->hasPermission('view_mine_areas');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermission('create_mine_areas');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, MineArea $mineArea): bool
    {
        return $user->current_team_id === $mineArea->team_id &&
               $user->hasPermission('update_mine_areas');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, MineArea $mineArea): bool
    {
        return $user->current_team_id === $mineArea->team_id &&
               $user->hasPermission('delete_mine_areas');
    }
}
