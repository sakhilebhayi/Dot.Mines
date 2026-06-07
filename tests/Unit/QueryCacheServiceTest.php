<?php

namespace Tests\Unit;

use App\Services\QueryCacheService;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class QueryCacheServiceTest extends TestCase
{
    #[Test]
    public function dashboard_stats_caches_and_returns_result(): void
    {
        Cache::flush();

        $called = 0;
        $result = QueryCacheService::dashboardStats(1, function () use (&$called) {
            $called++;

            return ['total_machines' => 5];
        });

        $this->assertEquals(['total_machines' => 5], $result);
        $this->assertEquals(1, $called);

        // Second call should use cache
        QueryCacheService::dashboardStats(1, function () use (&$called) {
            $called++;

            return ['total_machines' => 99];
        });

        $this->assertEquals(1, $called, 'Callback should not be called again — result is cached');
    }

    #[Test]
    public function alert_stats_caches_result(): void
    {
        Cache::flush();

        $result = QueryCacheService::alertStats(42, fn () => ['active' => 3]);

        $this->assertEquals(['active' => 3], $result);
    }

    #[Test]
    public function fleet_summary_caches_result(): void
    {
        Cache::flush();

        $result = QueryCacheService::fleetSummary(7, fn () => ['machines' => 10]);

        $this->assertEquals(['machines' => 10], $result);
    }

    #[Test]
    public function production_chart_caches_by_team_and_period(): void
    {
        Cache::flush();

        $r1 = QueryCacheService::productionChart(1, 'week', fn () => ['week_data']);
        $r2 = QueryCacheService::productionChart(1, 'month', fn () => ['month_data']);

        $this->assertEquals(['week_data'], $r1);
        $this->assertEquals(['month_data'], $r2);
    }

    #[Test]
    public function invalidate_dashboard_clears_dashboard_cache(): void
    {
        Cache::flush();
        QueryCacheService::dashboardStats(1, fn () => ['x' => 1]);

        QueryCacheService::invalidateDashboard(1);

        $called = 0;
        QueryCacheService::dashboardStats(1, function () use (&$called) {
            $called++;

            return ['x' => 2];
        });

        $this->assertEquals(1, $called, 'Cache should have been cleared — callback should re-run');
    }

    #[Test]
    public function invalidate_alerts_clears_alert_and_dashboard_cache(): void
    {
        Cache::flush();
        QueryCacheService::dashboardStats(5, fn () => ['y' => 1]);
        QueryCacheService::alertStats(5, fn () => ['a' => 1]);

        QueryCacheService::invalidateAlerts(5);

        $dashCalled = 0;
        QueryCacheService::dashboardStats(5, function () use (&$dashCalled) {
            $dashCalled++;

            return ['y' => 2];
        });

        $this->assertEquals(1, $dashCalled);
    }

    #[Test]
    public function report_templates_uses_long_ttl(): void
    {
        Cache::flush();

        $first = QueryCacheService::reportTemplates(fn () => ['tmpl_1']);
        $second = QueryCacheService::reportTemplates(fn () => ['tmpl_2']);

        $this->assertEquals(['tmpl_1'], $first);
        $this->assertEquals(['tmpl_1'], $second, 'Should serve cached value');
    }

    #[Test]
    public function fuel_tank_summary_caches_result(): void
    {
        Cache::flush();

        $result = QueryCacheService::fuelTankSummary(3, fn () => ['liters' => 500]);

        $this->assertEquals(['liters' => 500], $result);
    }

    #[Test]
    public function upcoming_maintenance_caches_result(): void
    {
        Cache::flush();

        $result = QueryCacheService::upcomingMaintenance(9, fn () => ['count' => 4]);

        $this->assertEquals(['count' => 4], $result);
    }

    #[Test]
    public function compliance_score_caches_result(): void
    {
        Cache::flush();

        $result = QueryCacheService::complianceScore(2, fn () => ['score' => 95]);

        $this->assertEquals(['score' => 95], $result);
    }

    #[Test]
    public function team_permissions_caches_result(): void
    {
        Cache::flush();

        $result = QueryCacheService::teamPermissions(11, fn () => ['perms' => ['view', 'edit']]);

        $this->assertEquals(['perms' => ['view', 'edit']], $result);
    }

    #[Test]
    public function clear_team_cache_removes_all_team_keys(): void
    {
        Cache::flush();
        QueryCacheService::dashboardStats(20, fn () => ['d']);
        QueryCacheService::alertStats(20, fn () => ['a']);
        QueryCacheService::integrationStatus(20, fn () => ['i']);

        QueryCacheService::clearTeamCache(20);

        $dashCalled = 0;
        QueryCacheService::dashboardStats(20, function () use (&$dashCalled) {
            $dashCalled++;

            return ['d2'];
        });

        $this->assertEquals(1, $dashCalled, 'Dashboard cache should have been cleared');
    }
}
