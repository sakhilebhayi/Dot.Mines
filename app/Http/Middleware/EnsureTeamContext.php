<?php

namespace App\Http\Middleware;

use App\Models\Team;
use Closure;
use Illuminate\Http\Request;
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
        $user = auth()->user();

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
            }
        }

        // Verify user has access to the team
        if ($teamId) {
            $team = Team::find($teamId);
            if (! $team || ! $user->belongsToTeam($team)) {
                abort(403, 'Unauthorized to access this team.');
            }
        } else {
            // A user who belongs to no team at all (e.g. removed from their last
            // team) reaches here with $teamId still null. Every team-scoped page
            // and API endpoint downstream assumes Auth::user()->currentTeam is
            // set (see e.g. Dashboard::mount() and ReportController::view2(),
            // which each guard against this individually) -- rather than let
            // every one of those crash with "Attempt to read property ... on
            // null", stop it here once, centrally, for every route this
            // middleware covers.
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'No team context available. Please create or join a team.',
                ], 409);
            }

            return redirect()->route('teams.create');
        }

        // Store team context in request
        $request->attributes->set('team_id', $teamId);

        return $next($request);
    }
}
