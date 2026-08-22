<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

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
            $teamId = auth()->user()?->current_team_id;

            // Allow non-HTTP contexts (jobs/commands) to set the current team
            if (($teamId === null || $teamId === 0) && app()->has('current_team_id')) {
                $teamId = app('current_team_id');
            }

            if ($teamId) {
                $builder->where('team_id', $teamId);
            }
        });
    }

    /**
     * Get all models without team filtering
     * Use with caution - only for admin operations
     *
     * @return Builder<static>
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
     * @param  Builder<static>  $query
     * @param  int  $teamId
     * @return Builder<static>
     */
    public function scopeForTeam(Builder $query, $teamId)
    {
        return $query->withoutGlobalScope('team')->where('team_id', $teamId);
    }
}
