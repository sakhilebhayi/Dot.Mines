<?php

namespace App\Policies;

use App\Models\ShiftTemplate;
use App\Models\User;

class ShiftTemplatePolicy
{
    public function before(User $user): ?bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasRole(['supervisor', 'manager', 'safety_officer']);
    }

    public function update(User $user, ShiftTemplate $template): bool
    {
        // Enforce team membership before checking role or ownership.
        if ($user->current_team_id !== $template->team_id) {
            return false;
        }

        return $user->hasRole(['supervisor', 'manager'])
            || $template->created_by === $user->id;
    }

    public function delete(User $user, ShiftTemplate $template): bool
    {
        // Enforce team membership before checking role or ownership.
        if ($user->current_team_id !== $template->team_id) {
            return false;
        }

        return $user->hasRole(['supervisor', 'manager'])
            || $template->created_by === $user->id;
    }
}
