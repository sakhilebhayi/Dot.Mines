<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

/**
 * Query Cache Service
 *
 * Centralized service for caching database query results
 * with intelligent cache key generation and TTL management
 */
class QueryCacheService
{
    /**
     * Default cache TTL in seconds (5 minutes)
     */
    const DEFAULT_TTL = 300;

    /**
     * Cache dashboard statistics
     *
     * @return array<string, mixed>
     */
    public static function dashboardStats(int $teamId, \Closure $callback): array
    {
        return Cache::remember(
            "dashboard_stats_{$teamId}",
            self::DEFAULT_TTL,
            $callback
        );
    }

    /**
     * Invalidate dashboard cache for a team
     */
    public static function invalidateDashboard(int $teamId): void
    {
        Cache::forget("dashboard_stats_{$teamId}");
    }

    /**
     * Invalidate machine cache
     */
    public static function invalidateMachine(int $machineId, int $teamId): void
    {
        Cache::forget("machine_details_{$machineId}");
        // Bump the list version so every filter variation goes stale at
        // once -- NEVER Cache::flush() here: that wiped the whole store
        // (API token caches included) on every machine save.
        Cache::forever("machines_list_version_{$teamId}", now()->getTimestampMs());
    }

    /**
     * Invalidate alert cache for a team
     */
    public static function invalidateAlerts(int $teamId): void
    {
        Cache::forget("alert_stats_{$teamId}");
        Cache::forget("dashboard_stats_{$teamId}");
    }
}
