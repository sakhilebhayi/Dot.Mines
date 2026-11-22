<?php

namespace App\Support;

/**
 * Where the "Sign in" entry points of the signed-out pages go. With DOTSSO_ENABLED they go straight to the central
 * sign-in (no hop through /login); otherwise exactly as before. Dot.Mines has no sign-up of its own, so there is no
 * "Create account": a person is added by invitation or by an administrator.
 */
class CentralSignIn
{
    public static function active(): bool
    {
        return config('dotsso.enabled') === true && config('dotsso.allow_local_login') !== true;
    }

    public static function login(): string
    {
        return self::active() ? route('dotsso.login') : route('login');
    }

    /**
     * The invitation email's button: central sign-in that returns to the signed accept link.
     *
     * @psalm-suppress PossiblyUnusedMethod -- called from emails/team-invitation.blade.php
     */
    public static function loginThen(string $next): string
    {
        return route('dotsso.login', ['next' => $next]);
    }
}
