<?php

namespace Tests\Feature;

use App\Contracts\ManufacturerAdapterInterface;
use App\Jobs\DispatchIntegrationSyncsJob;
use App\Jobs\SyncIntegrationJob;
use App\Models\Integration;
use App\Models\IntegrationSyncLog;
use App\Models\Machine;
use App\Models\User;
use App\Services\Integration\AdapterRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SyncIntegrationJobTest extends TestCase
{
    use RefreshDatabase;

    private Integration $integration;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->withPersonalTeam()->create();
        $this->integration = Integration::factory()->create([
            'team_id' => $user->currentTeam->id,
            'provider' => 'bell',
            'name' => 'Test Bell',
            'credentials' => ['api_url' => 'https://api.example.com', 'username' => 'u', 'password' => 'p'],
            'status' => 'connected',
        ]);
    }

    #[Test]
    public function sync_job_creates_machines_and_sync_log(): void
    {
        // Arrange: mock the adapter to return two machines
        $mockAdapter = $this->createMock(ManufacturerAdapterInterface::class);
        $mockAdapter->method('fetchFleet')->willReturn([
            ['external_id' => 'EQ-001', 'name' => 'Bell B50E #1', 'model' => 'B50E', 'manufacturer' => 'Bell Equipment', 'serial_number' => 'SN001', 'latitude' => -26.02, 'longitude' => 28.94, 'engine_running' => true, 'fuel_remaining_percent' => 72.0, 'operating_hours' => 1200.0, 'load_count' => 500, 'telemetry_date' => null],
            ['external_id' => 'EQ-002', 'name' => 'Bell B50E #2', 'model' => 'B50E', 'manufacturer' => 'Bell Equipment', 'serial_number' => 'SN002', 'latitude' => -26.03, 'longitude' => 28.95, 'engine_running' => false, 'fuel_remaining_percent' => 45.0, 'operating_hours' => 980.0, 'load_count' => 310, 'telemetry_date' => null],
        ]);

        $registry = $this->createMock(AdapterRegistry::class);
        $registry->method('resolve')->willReturn($mockAdapter);
        $this->app->instance(AdapterRegistry::class, $registry);

        // Act
        (new SyncIntegrationJob($this->integration->id))->handle($registry);

        // Assert: machines created for correct team
        $machines = Machine::where('team_id', $this->integration->team_id)->get();
        $this->assertCount(2, $machines);
        $this->assertSame('Bell B50E #1', $machines->firstWhere('external_id', 'EQ-001')->name);

        // Assert: integration updated
        $this->integration->refresh();
        $this->assertSame('connected', $this->integration->status);
        $this->assertSame(2, $this->integration->machines_count);

        // Assert: sync log written
        $log = IntegrationSyncLog::where('integration_id', $this->integration->id)->first();
        $this->assertNotNull($log);
        $this->assertSame('success', $log->status);
        $this->assertSame(2, $log->machines_synced);
    }

    #[Test]
    public function sync_job_does_not_create_machines_for_other_teams(): void
    {
        $otherUser = User::factory()->withPersonalTeam()->create();

        // Pre-create a machine for the other team with the same external_id
        Machine::factory()->create([
            'team_id' => $otherUser->currentTeam->id,
            'external_id' => 'EQ-001',
        ]);

        $mockAdapter = $this->createMock(ManufacturerAdapterInterface::class);
        $mockAdapter->method('fetchFleet')->willReturn([
            ['external_id' => 'EQ-001', 'name' => 'Our Machine', 'model' => 'B50E', 'manufacturer' => 'Bell Equipment', 'serial_number' => 'SN001', 'latitude' => null, 'longitude' => null, 'engine_running' => null, 'fuel_remaining_percent' => null, 'operating_hours' => null, 'load_count' => null, 'telemetry_date' => null],
        ]);

        $registry = $this->createMock(AdapterRegistry::class);
        $registry->method('resolve')->willReturn($mockAdapter);

        (new SyncIntegrationJob($this->integration->id))->handle($registry);

        // The other team's machine must be untouched
        $otherMachine = Machine::where('team_id', $otherUser->currentTeam->id)->where('external_id', 'EQ-001')->first();
        $this->assertNotNull($otherMachine);
        $this->assertNotSame($this->integration->team_id, $otherMachine->team_id);
    }

    #[Test]
    public function dispatch_syncs_job_dispatches_for_due_integrations(): void
    {
        Queue::fake();

        // Make the integration overdue (last_sync_at = 10 minutes ago, frequency = every 5 min)
        $this->integration->update([
            'last_sync_at' => now()->subMinutes(10),
            'config' => ['sync_frequency' => 'every_5_minutes'],
        ]);

        (new DispatchIntegrationSyncsJob)->handle();

        Queue::assertPushed(SyncIntegrationJob::class, fn ($job) => true);
    }

    #[Test]
    public function dispatch_syncs_job_skips_manual_integrations(): void
    {
        Queue::fake();

        $this->integration->update([
            'last_sync_at' => now()->subMinutes(10),
            'config' => ['sync_frequency' => 'manual'],
        ]);

        (new DispatchIntegrationSyncsJob)->handle();

        Queue::assertNotPushed(SyncIntegrationJob::class);
    }
}
