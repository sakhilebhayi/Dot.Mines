<?php

namespace App\Actions\Ecosystem;

use App\Models\User;

/** The result of trying to match an InfoDot identity to a Dot.Mines user: the user, or the (loggable) reason there is none. */
final class LinkOutcome
{
    public const BAD_IDENTITY = 'identity_incomplete';

    public const NO_ACCOUNT = 'no_mines_account';

    public const MINES_EMAIL_UNVERIFIED = 'mines_email_unverified';

    public const LINKED_ELSEWHERE = 'mines_account_linked_to_another_identity';

    private function __construct(public readonly ?User $user, public readonly ?string $reason) {}

    public static function found(User $user): self
    {
        return new self($user, null);
    }

    public static function refused(string $reason): self
    {
        return new self(null, $reason);
    }
}
