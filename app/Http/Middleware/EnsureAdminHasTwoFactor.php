<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Requires admin-role users to have two-factor authentication confirmed before
 * accessing admin-only routes. Non-admin users pass through unaffected.
 *
 * Redirects to the 2FA setup page (/user/two-factor-authentication) when 2FA
 * is not yet confirmed, so the admin can enable it before proceeding.
 */
class EnsureAdminHasTwoFactor
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        if (! $user->hasRole('admin')) {
            return $next($request);
        }

        if (is_null($user->two_factor_confirmed_at)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Two-factor authentication is required for admin accounts.',
                ], 403);
            }

            return redirect()->route('profile.show')
                ->with('flash.banner', 'Admin accounts must enable two-factor authentication before accessing this area.')
                ->with('flash.bannerStyle', 'danger');
        }

        return $next($request);
    }
}
