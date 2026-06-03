<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * HasTeamFilters Trait
 *
 * Automatically scopes all queries to the current team/tenant
 * Prevents cross-tenant data leakage by applying team_id filter globally
 */
trait HasTeamFilters
{
    /**
     * Boot the trait
     *
     * @return void
     */
    protected static function bootHasTeamFilters()
    {
        // Add global scope for team filtering
        static::addGlobalScope('team', function (Builder $builder) {
            /** @var \App\Models\User|null $user */
            $user = Auth::user();
            $teamId = $user?->current_team_id;

            // Allow non-HTTP contexts (jobs/commands) to set the current team
            if (empty($teamId) && app()->has('current_team_id')) {
                $teamId = app('current_team_id');
            }

            if ($teamId) {
                $builder->where('team_id', $teamId);

                return;
            }

            // In an HTTP context with an authenticated session but no resolved team,
            // the request must not silently return cross-tenant records.
            // Apply an impossible condition so zero rows are returned rather than all rows.
            if (! app()->runningInConsole() && Auth::check()) {
                $builder->whereRaw('1 = 0');
            }
        });
    }

    /**
     * Get all models without team filtering
     * Use with caution - only for admin operations
     *
     * @return Builder
     */
    public static function withoutTeamFilter()
    {
        return static::withoutGlobalScope('team');
    }

    /**
     * Get the team ID for this model
     *
     * @return int|null
     */
    public function getTeamId()
    {
        return $this->getAttribute('team_id');
    }

    /**
     * Scope to a specific team
     *
     * @param  int  $teamId
     * @return Builder
     */
    public function scopeForTeam(Builder $query, $teamId)
    {
        return $query->withoutGlobalScope('team')->where('team_id', $teamId);
    }
}
