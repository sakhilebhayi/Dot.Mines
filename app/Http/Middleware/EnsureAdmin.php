<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restricts access to admin-only routes.
 * Must be used inside an authenticated route group.
 *
 * @psalm-suppress UnusedClass -- registered via the 'admin' middleware
 * alias in bootstrap/app.php, which psalm cannot trace.
 */
final class EnsureAdmin
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        abort_unless($user instanceof User && $user->hasRole('admin'), 403, 'Admin access required.');

        return $next($request);
    }
}
