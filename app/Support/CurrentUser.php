<?php

namespace App\Support;

use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * The authenticated user, typed as the concrete model both analyzers can
 * resolve. larastan types Auth::user() as the bare Authenticatable
 * contract (the facade @method pins it), and a nullsafe `?->` on that
 * contract defeats even its magic property extension -- while psalm's
 * plugin wants the null handled. This helper is the one place that
 * narrows, so call sites can use plain `?->` and stay clean under BOTH
 * analyzers (see .ai/rules/app.md).
 */
final class CurrentUser
{
    public static function get(): ?User
    {
        $user = Auth::user();

        return $user instanceof User ? $user : null;
    }

    /**
     * The authenticated user's current team, or a 403 for the teamless
     * edge -- the one place this guard lives, so call sites stay clean
     * under both analyzers (phpstan needs the null handled; psalm's
     * plugin considers currentTeam non-null and calls per-site guards
     * redundant).
     *
     * @psalm-suppress RedundantConditionGivenDocblockType, DocblockTypeContradiction
     */
    public static function team(): Team
    {
        $team = self::get()?->currentTeam;

        if ($team === null) {
            abort(403);
        }

        return $team;
    }
}
