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
     */
    public static function dashboardStats(int $teamId, callable $callback): array
    {
        return Cache::remember(
            "dashboard_stats_{$teamId}",
            self::DEFAULT_TTL,
            $callback
        );
    }

    /**
     * Cache machine list for team
     */
    public static function machineList(int $teamId, array $filters, callable $callback)
    {
        $filterKey = md5(json_encode($filters));

        return Cache::remember(
            "machines_list_{$teamId}_{$filterKey}",
            60, // 1 minute for list views
            $callback
        );
    }

    /**
     * Cache machine details
     */
    public static function machineDetails(int $machineId, callable $callback)
    {
        return Cache::remember(
            "machine_details_{$machineId}",
            self::DEFAULT_TTL,
            $callback
        );
    }

    /**
     * Cache alert statistics
     */
    public static function alertStats(int $teamId, callable $callback): array
    {
        return Cache::remember(
            "alert_stats_{$teamId}",
            120, // 2 minutes
            $callback
        );
    }

    /**
     * Cache geofence statistics
     */
    public static function geofenceStats(int $geofenceId, callable $callback): array
    {
        return Cache::remember(
            "geofence_stats_{$geofenceId}",
            self::DEFAULT_TTL,
            $callback
        );
    }

    /**
     * Cache integration sync status
     */
    public static function integrationStatus(int $teamId, callable $callback): array
    {
        return Cache::remember(
            "integration_status_{$teamId}",
            600, // 10 minutes - integrations don't change often
            $callback
        );
    }

    /**
     * Cache report templates
     */
    public static function reportTemplates(callable $callback): array
    {
        return Cache::remember(
            'report_templates',
            86400, // 24 hours - templates rarely change
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
        // Also clear team's machine list cache (all variations)
        Cache::flush(); // Or use tags if using Redis
    }

    /**
     * Invalidate alert cache for a team
     */
    public static function invalidateAlerts(int $teamId): void
    {
        Cache::forget("alert_stats_{$teamId}");
        Cache::forget("dashboard_stats_{$teamId}");
    }

    /**
     * Invalidate geofence cache
     */
    public static function invalidateGeofence(int $geofenceId): void
    {
        Cache::forget("geofence_stats_{$geofenceId}");
    }

    /**
     * Invalidate integration cache
     */
    public static function invalidateIntegrations(int $teamId): void
    {
        Cache::forget("integration_status_{$teamId}");
    }

    /**
     * Clear all caches for a team
     */
    public static function clearTeamCache(int $teamId): void
    {
        $keys = [
            "dashboard_stats_{$teamId}",
            "alert_stats_{$teamId}",
            "integration_status_{$teamId}",
        ];

        foreach ($keys as $key) {
            Cache::forget($key);
        }
    }
}
