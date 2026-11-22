<?php

namespace App\Exceptions;

use App\Models\User;
use RuntimeException;

/** An InfoDot sign-in matched a Mines account that is unverified or carries authority: the person proves the mailbox first. */
class ClaimRequired extends RuntimeException
{
    /** @param array{sub: string, email: string, name: string} $claims */
    public function __construct(public readonly User $user, public readonly array $claims)
    {
        parent::__construct('A Mines account holds this address; the claim flow is required.');
    }
}
