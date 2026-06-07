<?php

namespace App\Services;

use Closure;
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
    /**
     * @return array<string, mixed>
     */
    public static function dashboardStats(int $teamId, Closure $callback): array
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
    /**
     * @param  array<string, mixed>  $filters
     */
    public static function machineList(int $teamId, array $filters, Closure $callback): mixed
    {
        $filterKey = md5((string) json_encode($filters));

        return Cache::remember(
            "machines_list_{$teamId}_{$filterKey}",
            60, // 1 minute for list views
            $callback
        );
    }

    /**
     * Cache machine details
     */
    public static function machineDetails(int $machineId, Closure $callback): mixed
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
    /**
     * @return array<string, mixed>
     */
    public static function alertStats(int $teamId, Closure $callback): array
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
    /**
     * @return array<string, mixed>
     */
    public static function geofenceStats(int $geofenceId, Closure $callback): array
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
    /**
     * @return array<string, mixed>
     */
    public static function integrationStatus(int $teamId, Closure $callback): array
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
    /**
     * @return array<string, mixed>
     */
    public static function reportTemplates(Closure $callback): array
    {
        return Cache::remember(
            'report_templates',
            86400, // 24 hours - templates rarely change
            $callback
        );
    }

    // ──────────────────────────────────────────────────────────────────────
    // Charts & analytics
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Cache production chart data for a team.
     *
     * @return array<string, mixed>
     */
    public static function productionChart(int $teamId, string $period, Closure $callback): array
    {
        return Cache::remember(
            "production_chart_{$teamId}_{$period}",
            600, // 10 minutes
            $callback
        );
    }

    /**
     * Cache fuel consumption chart data.
     *
     * @return array<string, mixed>
     */
    public static function fuelChart(int $teamId, string $period, Closure $callback): array
    {
        return Cache::remember(
            "fuel_chart_{$teamId}_{$period}",
            600,
            $callback
        );
    }

    /**
     * Cache machine utilisation chart data.
     *
     * @return array<string, mixed>
     */
    public static function utilisationChart(int $teamId, string $period, Closure $callback): array
    {
        return Cache::remember(
            "utilisation_chart_{$teamId}_{$period}",
            300,
            $callback
        );
    }

    /**
     * Cache maintenance cost chart data.
     *
     * @return array<string, mixed>
     */
    public static function maintenanceCostChart(int $teamId, string $period, Closure $callback): array
    {
        return Cache::remember(
            "maintenance_cost_chart_{$teamId}_{$period}",
            1800, // 30 minutes – slow-moving data
            $callback
        );
    }

    // ──────────────────────────────────────────────────────────────────────
    // Fleet data
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Cache fleet summary statistics.
     *
     * @return array<string, mixed>
     */
    public static function fleetSummary(int $teamId, Closure $callback): array
    {
        return Cache::remember(
            "fleet_summary_{$teamId}",
            120, // 2 minutes – fleet status changes frequently
            $callback
        );
    }

    /**
     * Cache fleet machine locations (for map view).
     *
     * @return array<string, mixed>
     */
    public static function fleetLocations(int $teamId, Closure $callback): array
    {
        return Cache::remember(
            "fleet_locations_{$teamId}",
            30, // 30 seconds – near-real-time
            $callback
        );
    }

    /**
     * Cache machine health summary for the fleet.
     *
     * @return array<string, mixed>
     */
    public static function fleetHealthSummary(int $teamId, Closure $callback): array
    {
        return Cache::remember(
            "fleet_health_summary_{$teamId}",
            300,
            $callback
        );
    }

    // ──────────────────────────────────────────────────────────────────────
    // Production data
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Cache production totals for a team and date range.
     *
     * @return array<string, mixed>
     */
    public static function productionTotals(int $teamId, string $startDate, string $endDate, Closure $callback): array
    {
        return Cache::remember(
            "production_totals_{$teamId}_{$startDate}_{$endDate}",
            300,
            $callback
        );
    }

    /**
     * Cache per-machine production stats.
     *
     * @return array<string, mixed>
     */
    public static function machineProductionStats(int $machineId, string $period, Closure $callback): array
    {
        return Cache::remember(
            "machine_production_{$machineId}_{$period}",
            300,
            $callback
        );
    }

    /**
     * Cache mine area production stats.
     *
     * @return array<string, mixed>
     */
    public static function mineAreaProduction(int $mineAreaId, string $period, Closure $callback): array
    {
        return Cache::remember(
            "mine_area_production_{$mineAreaId}_{$period}",
            600,
            $callback
        );
    }

    // ──────────────────────────────────────────────────────────────────────
    // Reports
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Cache the most-recent completed reports list for a team.
     *
     * @return array<string, mixed>
     */
    public static function recentReports(int $teamId, Closure $callback): array
    {
        return Cache::remember(
            "recent_reports_{$teamId}",
            60, // 1 minute
            $callback
        );
    }

    /**
     * Cache report metadata (not the file – just the DB record fields).
     *
     * @return array<string, mixed>
     */
    public static function reportMeta(int $reportId, Closure $callback): array
    {
        return Cache::remember(
            "report_meta_{$reportId}",
            3600, // 1 hour – completed reports don't change
            $callback
        );
    }

    // ──────────────────────────────────────────────────────────────────────
    // Fuel data
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Cache fuel tank summary for a team.
     *
     * @return array<string, mixed>
     */
    public static function fuelTankSummary(int $teamId, Closure $callback): array
    {
        return Cache::remember(
            "fuel_tank_summary_{$teamId}",
            120,
            $callback
        );
    }

    /**
     * Cache recent fuel transactions for a team.
     *
     * @return array<string, mixed>
     */
    public static function recentFuelTransactions(int $teamId, Closure $callback): array
    {
        return Cache::remember(
            "recent_fuel_transactions_{$teamId}",
            60,
            $callback
        );
    }

    /**
     * Cache fuel consumption metrics per machine.
     *
     * @return array<string, mixed>
     */
    public static function machineFuelMetrics(int $machineId, string $period, Closure $callback): array
    {
        return Cache::remember(
            "machine_fuel_metrics_{$machineId}_{$period}",
            300,
            $callback
        );
    }

    // ──────────────────────────────────────────────────────────────────────
    // Maintenance data
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Cache upcoming maintenance schedules.
     *
     * @return array<string, mixed>
     */
    public static function upcomingMaintenance(int $teamId, Closure $callback): array
    {
        return Cache::remember(
            "upcoming_maintenance_{$teamId}",
            300,
            $callback
        );
    }

    /**
     * Cache maintenance KPIs for a team.
     *
     * @return array<string, mixed>
     */
    public static function maintenanceKpis(int $teamId, string $period, Closure $callback): array
    {
        return Cache::remember(
            "maintenance_kpis_{$teamId}_{$period}",
            600,
            $callback
        );
    }

    /**
     * Cache overdue maintenance alerts count.
     */
    public static function overdueMaintenanceCount(int $teamId, Closure $callback): int
    {
        return Cache::remember(
            "overdue_maintenance_count_{$teamId}",
            300,
            $callback
        );
    }

    // ──────────────────────────────────────────────────────────────────────
    // Compliance & audit
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Cache compliance score for a team.
     *
     * @return array<string, mixed>
     */
    public static function complianceScore(int $teamId, Closure $callback): array
    {
        return Cache::remember(
            "compliance_score_{$teamId}",
            1800, // 30 minutes
            $callback
        );
    }

    /**
     * Cache team role/permission list (rarely changes).
     *
     * @return array<string, mixed>
     */
    public static function teamPermissions(int $teamId, Closure $callback): array
    {
        return Cache::remember(
            "team_permissions_{$teamId}",
            3600, // 1 hour
            $callback
        );
    }

    // ──────────────────────────────────────────────────────────────────────
    // Cache invalidation
    // ──────────────────────────────────────────────────────────────────────

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
