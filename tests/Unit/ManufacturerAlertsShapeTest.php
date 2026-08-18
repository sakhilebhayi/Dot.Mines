<?php

namespace Tests\Unit;

use App\Services\Integration\CATService;
use ReflectionMethod;
use Tests\TestCase;

/**
 * The alerts table's chk_alert_status_values constraint (Postgres/MySQL)
 * only allows: active, acknowledged, resolved, dismissed,
 * dismissed_unresolved. BaseManufacturerService::parseAlerts() used to
 * default missing statuses to the legacy 'new', so every synced alert
 * from a manufacturer API that omits a status failed to insert on
 * Postgres. parseAlerts() is protected (a shared helper, not part of
 * ManufacturerServiceInterface), so it is invoked via reflection -- the
 * same approach ManufacturerMetricsShapeTest uses for the other shared
 * helpers.
 */
class ManufacturerAlertsShapeTest extends TestCase
{
    private function invokeProtected(object $service, string $method, array $args): mixed
    {
        $reflection = new ReflectionMethod($service, $method);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs($service, $args);
    }

    public function test_parse_alerts_defaults_missing_status_to_active_not_the_legacy_new(): void
    {
        $service = new CATService(['base_url' => 'https://api.example.test']);

        $parsed = $this->invokeProtected($service, 'parseAlerts', [[
            ['id' => 'fault-1', 'title' => 'Low oil pressure', 'severity' => 'high'],
        ]]);

        $this->assertCount(1, $parsed);
        $this->assertSame('active', $parsed[0]['status']);
    }

    public function test_parse_alerts_keeps_a_status_the_manufacturer_actually_provided(): void
    {
        $service = new CATService(['base_url' => 'https://api.example.test']);

        $parsed = $this->invokeProtected($service, 'parseAlerts', [[
            ['id' => 'fault-2', 'title' => 'Filter replaced', 'severity' => 'low', 'status' => 'resolved'],
        ]]);

        $this->assertSame('resolved', $parsed[0]['status']);
    }
}
