<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Requires admin-role users to have two-factor authentication confirmed before
 * accessing the authenticated app. Non-admin users pass through unaffected.
 *
 * Redirects to Jetstream's profile page (where 2FA is enabled) when 2FA is
 * not yet confirmed. That route lives outside the group this middleware is
 * applied to, so no redirect loop is possible.
 *
 * @psalm-suppress UnusedClass -- registered via the 'admin.2fa' middleware
 * alias in bootstrap/app.php, which psalm cannot trace.
 */
final class EnsureAdminHasTwoFactor
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
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
