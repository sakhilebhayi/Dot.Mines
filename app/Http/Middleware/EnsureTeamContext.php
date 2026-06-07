<?php

namespace App\Http\Middleware;

use App\Models\Team;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * EnsureTeamContext Middleware
 *
 * Ensures every request has a valid team context
 * Sets the current team for the authenticated user
 * Used to enforce multi-tenancy throughout the application
 */
class EnsureTeamContext
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Get authenticated user
        /** @var User|null $user */
        $user = Auth::user();

        if (! $user) {
            return $next($request);
        }

        // Get team_id from route or use user's current team
        $teamId = $request->route('team_id') ?? $user->current_team_id;

        // If no team_id, set to user's default team
        if (! $teamId) {
            $teamId = $user->teams()->first()?->id;
            if ($teamId) {
                $user->update(['current_team_id' => $teamId]);
                // Refresh the in-memory model so Auth::user()->currentTeam works downstream
                Auth::setUser($user->refresh());
            }
        }

        // Verify user has access to the team
        if ($teamId) {
            $team = Team::find($teamId);
            if (! $team || ! $user->belongsToTeam($team)) {
                abort(403, 'Unauthorized to access this team.');
            }
        }

        // Store team context in request
        $request->attributes->set('team_id', $teamId);

        return $next($request);
    }
}
