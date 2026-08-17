<?php

namespace Tests\Unit;

use App\Services\Integration\IntegrationService;
use Tests\TestCase;

class IntegrationServiceCapabilitiesTest extends TestCase
{
    public function test_fleet_is_always_present_when_a_machine_was_returned(): void
    {
        $capabilities = app(IntegrationService::class)->deriveCapabilities([
            'external_id' => 'X1',
            'metrics' => [],
        ]);

        $this->assertContains('fleet', $capabilities);
        $this->assertNotContains('telemetry', $capabilities);
        $this->assertNotContains('production', $capabilities);
        $this->assertNotContains('location', $capabilities);
    }

    public function test_telemetry_is_detected_from_a_real_metric_field(): void
    {
        $capabilities = app(IntegrationService::class)->deriveCapabilities([
            'external_id' => 'X1',
            'metrics' => ['fuel_level' => 82.5, 'operating_hours' => 1200],
        ]);

        $this->assertContains('telemetry', $capabilities);
    }

    public function test_production_is_detected_from_bells_own_raw_data_shape(): void
    {
        $capabilities = app(IntegrationService::class)->deriveCapabilities([
            'external_id' => 'X1',
            'metrics' => [
                'raw_data' => ['cumulative_payload' => 4032.1, 'load_count' => 118],
            ],
        ]);

        $this->assertContains('production', $capabilities);
    }

    public function test_location_is_detected_from_a_valid_last_location(): void
    {
        $capabilities = app(IntegrationService::class)->deriveCapabilities([
            'external_id' => 'X1',
            'metrics' => [],
            'last_location' => ['latitude' => -26.2, 'longitude' => 28.0],
        ]);

        $this->assertContains('location', $capabilities);
    }

    public function test_a_null_metric_value_does_not_count_as_present(): void
    {
        $capabilities = app(IntegrationService::class)->deriveCapabilities([
            'external_id' => 'X1',
            'metrics' => ['fuel_level' => null, 'operating_hours' => null],
        ]);

        $this->assertNotContains('telemetry', $capabilities);
    }

    public function test_an_empty_sample_returns_no_capabilities_at_all(): void
    {
        $this->assertSame([], app(IntegrationService::class)->deriveCapabilities([]));
    }
}
