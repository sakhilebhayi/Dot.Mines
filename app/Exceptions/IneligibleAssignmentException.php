<?php

namespace App\Exceptions;

use Exception;

/**
 * An operator failed the eligibility gate for a machine assignment.
 *
 * Carries every blocker, not just the first, so the person assigning can fix
 * the whole list in one pass instead of discovering failures one at a time.
 */
class IneligibleAssignmentException extends Exception
{
    /**
     * @param  list<string>  $blockers
     */
    public function __construct(public readonly array $blockers)
    {
        parent::__construct(
            'Operator cannot be assigned: '.implode(' ', $blockers)
        );
    }
}
