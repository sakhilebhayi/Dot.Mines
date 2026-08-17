<?php

namespace Tests\Unit;

use App\Jobs\SyncIntegrationMachinesJob;
use App\Models\Integration;
use App\Models\Team;
use App\Services\Integration\IntegrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SyncIntegrationMachinesJobStreamsTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_successful_run_refreshes_last_synced_at_for_each_active_stream(): void
    {
        Http::fake(['*' => Http::response(['data' => []], 200)]);

        $integration = Integration::factory()->forProvider('hitachi')->create([
            'team_id' => Team::factory()->create()->id,
            'status' => 'connected',
            'credentials' => ['api_key' => 'key', 'base_url' => 'https://api.example.test'],
            'capabilities' => ['fleet', 'telemetry'],
            'sync_streams' => [
                'fleet' => ['status' => 'active', 'last_synced_at' => '2026-08-01T00:00:00+00:00', 'records' => 3],
                'telemetry' => ['status' => 'active', 'last_synced_at' => '2026-08-01T00:00:00+00:00', 'records' => 3],
                'production' => ['status' => 'unavailable', 'last_synced_at' => null, 'records' => 0],
                'location' => ['status' => 'unavailable', 'last_synced_at' => null, 'records' => 0],
            ],
        ]);

        (new SyncIntegrationMachinesJob($integration))->handle(app(IntegrationService::class));

        $fresh = $integration->fresh();
        $this->assertNotSame('2026-08-01T00:00:00+00:00', $fresh->sync_streams['fleet']['last_synced_at']);
        $this->assertNotSame('2026-08-01T00:00:00+00:00', $fresh->sync_streams['telemetry']['last_synced_at']);
        // Never-provided streams stay unavailable -- a scheduled sync run
        // must not fabricate a stream the account never demonstrated.
        $this->assertSame('unavailable', $fresh->sync_streams['production']['status']);
    }
}
