<?php

namespace App\Policies;

use App\Models\Operator;
use App\Models\User;

/**
 * Who may see and change operator records.
 *
 * Two tiers on purpose. The operational tier (view/manage) covers names,
 * assignments, licence STATUS and compliance verdicts -- what a fleet user
 * needs to run a shift. The medical tier is separate because a medical row is
 * health information about an identified person: seeing that an operator "has
 * a current medical" is operational; seeing the restrictions text is not.
 */
class OperatorPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('view_operators');
    }

    public function view(User $user, Operator $operator): bool
    {
        return $user->current_team_id === $operator->team_id
            && $user->hasPermission('view_operators');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('manage_operators');
    }

    public function update(User $user, Operator $operator): bool
    {
        return $user->current_team_id === $operator->team_id
            && $user->hasPermission('manage_operators');
    }

    public function delete(User $user, Operator $operator): bool
    {
        return $this->update($user, $operator);
    }

    /**
     * Read medical details: fitness findings, restrictions, certificates.
     */
    public function viewMedical(User $user, Operator $operator): bool
    {
        return $user->current_team_id === $operator->team_id
            && $user->hasPermission('view_operator_medicals');
    }

    /**
     * Record or change medical information.
     */
    public function manageMedical(User $user, Operator $operator): bool
    {
        return $user->current_team_id === $operator->team_id
            && $user->hasPermission('manage_operator_medicals');
    }
}
