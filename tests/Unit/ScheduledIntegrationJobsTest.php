<?php

namespace Tests\Unit;

use App\Jobs\MachineLocationUpdateJob;
use App\Jobs\MachineStatusMonitoringJob;
use App\Jobs\SyncIntegrationMachinesJob;
use App\Jobs\SyncMachineMetricsJob;
use App\Models\Integration;
use App\Models\Machine;
use App\Models\Team;
use App\Services\Integration\IntegrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Regression test: RealtimeEventScheduler dispatches MachineLocationUpdateJob
 * every 10 seconds and MachineStatusMonitoringJob every 20 seconds for every
 * connected integration -- both actually run in production, unlike some of
 * the other integration jobs. Both called
 * IntegrationService::getMachineLocations()/getMachineStatuses(), methods
 * that were never defined anywhere, so both fataled on every single
 * scheduled run for any team with a connected integration.
 */
class ScheduledIntegrationJobsTest extends TestCase
{
    use RefreshDatabase;

    public function test_location_update_job_no_longer_fatals_and_updates_the_machine(): void
    {
        Http::fake([
            '*' => Http::response(['latitude' => -26.5, 'longitude' => 28.1], 200),
        ]);

        $team = Team::factory()->create();
        $integration = Integration::factory()->forProvider('hitachi')->create([
            'team_id' => $team->id,
            'status' => 'connected',
            'credentials' => ['api_key' => 'key', 'base_url' => 'https://api.example.test'],
        ]);
        $machine = Machine::factory()->create([
            'team_id' => $team->id,
            'integration_id' => $integration->id,
            'manufacturer' => 'hitachi',
            'manufacturer_id' => 'HIT-1',
            'status' => 'active',
            'last_location_latitude' => null,
            'last_location_longitude' => null,
        ]);

        (new MachineLocationUpdateJob($integration))->handle(app(IntegrationService::class));

        $machine->refresh();
        $this->assertSame(-26.5, $machine->last_location_latitude);
        $this->assertSame(28.1, $machine->last_location_longitude);
    }

    public function test_status_monitoring_job_no_longer_fatals(): void
    {
        Http::fake([
            '*' => Http::response(['latitude' => -26.5, 'longitude' => 28.1], 200),
        ]);

        $team = Team::factory()->create();
        $integration = Integration::factory()->forProvider('hitachi')->create([
            'team_id' => $team->id,
            'status' => 'connected',
            'credentials' => ['api_key' => 'key', 'base_url' => 'https://api.example.test'],
        ]);
        Machine::factory()->create([
            'team_id' => $team->id,
            'integration_id' => $integration->id,
            'manufacturer' => 'hitachi',
            'manufacturer_id' => 'HIT-2',
            'status' => 'active',
        ]);

        (new MachineStatusMonitoringJob($integration))->handle(app(IntegrationService::class));

        $this->assertTrue(true); // Reaching this line without a fatal error is the assertion.
    }

    /**
     * SyncMachineMetricsJob had $tries but no $backoff, unlike its three
     * sibling sync jobs -- its retries fired back-to-back instead of giving
     * a transient failure time to clear. All four now agree.
     */
    public function test_every_sync_job_has_a_real_backoff_between_retries(): void
    {
        $team = Team::factory()->create();
        $integration = Integration::factory()->forProvider('hitachi')->create(['team_id' => $team->id]);
        $machine = Machine::factory()->create(['team_id' => $team->id]);

        $this->assertNotEmpty((new MachineLocationUpdateJob($integration))->backoff);
        $this->assertNotEmpty((new MachineStatusMonitoringJob($integration))->backoff);
        $this->assertNotEmpty((new SyncIntegrationMachinesJob($integration))->backoff);
        $this->assertNotEmpty((new SyncMachineMetricsJob($machine))->backoff);
    }
}
