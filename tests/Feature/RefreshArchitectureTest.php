<?php

namespace Tests\Feature;

use App\Jobs\MachineLocationUpdateJob;
use App\Jobs\MachineStatusMonitoringJob;
use App\Models\Integration;
use App\Models\Team;
use App\Services\Integration\IntegrationService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Slice 4 of the live-operations UX program: refresh cadences match how
 * fast the data actually changes, and the polling pipeline can never
 * again strand jobs or storm the provider's API. Context (2026-08-21):
 * the location scheduler ran every TEN SECONDS into named queues nothing
 * drained -- 996 jobs piled up, and per-machine API fan-out got the
 * server throttled by Bell.
 */
class RefreshArchitectureTest extends TestCase
{
    use RefreshDatabase;

    private function scheduledEvent(string $name): ?object
    {
        foreach (app(Schedule::class)->events() as $event) {
            if (($event->description ?? null) === $name) {
                return $event;
            }
        }

        return null;
    }

    public function test_api_bound_polling_schedules_run_every_five_minutes_not_every_few_seconds(): void
    {
        foreach (['schedule-location-updates', 'schedule-status-monitoring'] as $name) {
            $event = $this->scheduledEvent($name);

            $this->assertNotNull($event, "{$name} must stay scheduled.");
            $this->assertSame('*/5 * * * *', $event->expression, "{$name} hits the provider API and must not run sub-minute.");
            $this->assertNull($event->repeatSeconds, "{$name} must not carry a sub-minute repeat.");
        }
    }

    public function test_database_only_schedules_keep_their_faster_cadence(): void
    {
        foreach (['schedule-alert-generation', 'schedule-geofence-detection'] as $name) {
            $event = $this->scheduledEvent($name);

            $this->assertNotNull($event);
            $this->assertSame(30, $event->repeatSeconds, "{$name} is database-only work and stays at 30s.");
        }
    }

    public function test_queue_drain_services_every_named_queue_jobs_dispatch_to(): void
    {
        $drain = null;
        foreach (app(Schedule::class)->events() as $event) {
            if (str_contains((string) $event->command, 'queue:work')) {
                $drain = (string) $event->command;
            }
        }

        $this->assertNotNull($drain);

        // Every queue name used by an onQueue() call anywhere in app/.
        foreach (['default', 'locations', 'status', 'monitoring', 'alerts', 'geofences', 'notifications'] as $queue) {
            $this->assertStringContainsString($queue, $drain, "The drain must service the '{$queue}' queue or its jobs strand forever.");
        }
    }

    public function test_polling_jobs_are_unique_per_integration_so_backlogs_cannot_build(): void
    {
        $integration = Integration::factory()->forProvider('bell')->create([
            'team_id' => Team::factory()->create()->id,
            'status' => 'connected',
        ]);

        foreach ([MachineLocationUpdateJob::class, MachineStatusMonitoringJob::class] as $jobClass) {
            $job = new $jobClass($integration);

            $this->assertInstanceOf(ShouldBeUnique::class, $job);
            $this->assertSame((string) $integration->id, $job->uniqueId());
        }
    }

    public function test_bell_locations_for_a_whole_fleet_cost_one_snapshot_call(): void
    {
        Http::fake([
            'https://sso.bellequipment.com/connect/token' => Http::response(['access_token' => 't', 'expires_in' => 18000], 200),
            'https://b-fleet03.bellequipment.com:8080/Fleet' => Http::response(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Fleet version="1">
  <Equipment>
    <EquipmentHeader><OEMName>BELL</OEMName><Model>B50E</Model><EquipmentID>ASA B50E#0001</EquipmentID><SerialNumber>PIN0001</SerialNumber><PIN>PIN0001</PIN></EquipmentHeader>
    <Location datetime="2026-08-21T10:00:00Z"><Latitude>-26.01</Latitude><Longitude>28.91</Longitude></Location>
  </Equipment>
  <Equipment>
    <EquipmentHeader><OEMName>BELL</OEMName><Model>B50E</Model><EquipmentID>ASA B50E#0002</EquipmentID><SerialNumber>PIN0002</SerialNumber><PIN>PIN0002</PIN></EquipmentHeader>
    <Location datetime="2026-08-21T10:01:00Z"><Latitude>-26.02</Latitude><Longitude>28.92</Longitude></Location>
  </Equipment>
</Fleet>
XML, 200),
        ]);

        $integration = Integration::factory()->forProvider('bell')->create([
            'team_id' => Team::factory()->create()->id,
            'status' => 'connected',
            'credentials' => ['username' => 'u', 'password' => 'p', 'client_secret' => 's'],
        ]);

        $service = app(IntegrationService::class);
        $locations = $service->getMachineLocations($integration, ['ASA B50E#0001', 'ASA B50E#0002']);

        $this->assertCount(2, $locations);
        $this->assertEqualsWithDelta(-26.01, $locations[0]['latitude'], 0.001);

        // Statuses ride the SAME cached snapshot -- still no extra fleet call.
        $statuses = $service->getMachineStatuses($integration, ['ASA B50E#0001']);
        $this->assertSame([['manufacturer_id' => 'ASA B50E#0001', 'online' => true, 'status' => 'active']], $statuses);

        // One token request + exactly ONE /Fleet call for both batches --
        // never a per-machine Locations time-series fan-out.
        Http::assertSentCount(2);
    }
}
