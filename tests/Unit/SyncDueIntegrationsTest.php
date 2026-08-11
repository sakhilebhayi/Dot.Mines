<?php

namespace Tests\Unit;

use App\Jobs\SyncIntegrationMachinesJob;
use App\Models\Integration;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

/**
 * Nothing previously scheduled any manufacturer sync -- machine/metric data
 * only ever moved if a user manually clicked "Sync Now" or hit the API
 * directly. `integrations:sync-due` (scheduled every 5 minutes in
 * routes/console.php) is the fix; these tests cover its actual dispatch
 * decisions rather than just that the command runs.
 */
class SyncDueIntegrationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_connected_integration_that_has_never_synced_is_dispatched(): void
    {
        Bus::fake();

        Integration::factory()->forProvider('bell')->create([
            'team_id' => Team::factory()->create()->id,
            'status' => 'connected',
            'last_sync_at' => null,
        ]);

        $this->artisan('integrations:sync-due')->assertSuccessful();

        Bus::assertDispatched(SyncIntegrationMachinesJob::class, 1);
    }

    public function test_a_connected_integration_whose_interval_has_not_elapsed_is_skipped(): void
    {
        Bus::fake();

        // Bell's own config declares a 900-second (15 minute) interval.
        Integration::factory()->forProvider('bell')->create([
            'team_id' => Team::factory()->create()->id,
            'status' => 'connected',
            'last_sync_at' => now()->subMinutes(5),
        ]);

        $this->artisan('integrations:sync-due')->assertSuccessful();

        Bus::assertNotDispatched(SyncIntegrationMachinesJob::class);
    }

    public function test_a_connected_integration_whose_interval_has_elapsed_is_dispatched(): void
    {
        Bus::fake();

        Integration::factory()->forProvider('bell')->create([
            'team_id' => Team::factory()->create()->id,
            'status' => 'connected',
            'last_sync_at' => now()->subMinutes(20),
        ]);

        $this->artisan('integrations:sync-due')->assertSuccessful();

        Bus::assertDispatched(SyncIntegrationMachinesJob::class, 1);
    }

    public function test_a_disconnected_integration_is_never_dispatched_regardless_of_timing(): void
    {
        Bus::fake();

        Integration::factory()->forProvider('bell')->create([
            'team_id' => Team::factory()->create()->id,
            'status' => 'disconnected',
            'last_sync_at' => null,
        ]);

        $this->artisan('integrations:sync-due')->assertSuccessful();

        Bus::assertNotDispatched(SyncIntegrationMachinesJob::class);
    }
}
