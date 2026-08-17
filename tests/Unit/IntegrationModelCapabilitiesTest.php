<?php

namespace Tests\Unit;

use App\Models\Integration;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IntegrationModelCapabilitiesTest extends TestCase
{
    use RefreshDatabase;

    public function test_has_capability_reflects_the_capabilities_array(): void
    {
        $integration = Integration::factory()->forProvider('bell')->create([
            'team_id' => Team::factory()->create()->id,
            'capabilities' => ['fleet', 'telemetry'],
        ]);

        $this->assertTrue($integration->hasCapability('fleet'));
        $this->assertTrue($integration->hasCapability('telemetry'));
        $this->assertFalse($integration->hasCapability('production'));
    }

    public function test_has_capability_is_false_when_capabilities_is_null(): void
    {
        $integration = Integration::factory()->forProvider('bell')->create([
            'team_id' => Team::factory()->create()->id,
            'capabilities' => null,
        ]);

        $this->assertFalse($integration->hasCapability('fleet'));
    }

    public function test_stream_status_returns_the_matching_entry(): void
    {
        $integration = Integration::factory()->forProvider('bell')->create([
            'team_id' => Team::factory()->create()->id,
            'sync_streams' => [
                'fleet' => ['status' => 'active', 'last_synced_at' => '2026-08-11T00:00:00+00:00', 'records' => 4],
                'production' => ['status' => 'unavailable', 'last_synced_at' => null, 'records' => 0],
            ],
        ]);

        $this->assertSame('active', $integration->streamStatus('fleet')['status']);
        $this->assertSame('unavailable', $integration->streamStatus('production')['status']);
        $this->assertNull($integration->streamStatus('telemetry'));
    }
}
