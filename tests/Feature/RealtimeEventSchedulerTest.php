<?php

namespace Tests\Feature;

use App\Jobs\AlertGenerationJob;
use App\Jobs\GeofenceCrossingDetectionJob;
use App\Models\Geofence;
use App\Models\Machine;
use App\Models\Team;
use App\Services\RealtimeEventScheduler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * The scheduler's team sweeps queried teams.status = 'active' -- a column
 * the teams table has never had -- so every alert-generation and
 * geofence-detection tick threw, got swallowed into Log::error, and
 * dispatched NOTHING. Production logged the failure twice a minute while
 * both features silently never ran. These tests pin the observable
 * behavior: eligible teams actually get their jobs dispatched.
 */
class RealtimeEventSchedulerTest extends TestCase
{
    use RefreshDatabase;

    private function invokeScheduler(string $method): void
    {
        $reflection = new \ReflectionMethod(RealtimeEventScheduler::class, $method);
        $reflection->invoke(null);
    }

    public function test_alert_generation_dispatches_for_teams_with_machines(): void
    {
        Queue::fake();

        $team = Team::factory()->create();
        Machine::factory()->create(['team_id' => $team->id]);
        Team::factory()->create(); // machineless team -- must not dispatch

        $this->invokeScheduler('scheduleAlertGeneration');

        Queue::assertPushed(AlertGenerationJob::class, 1);
    }

    public function test_geofence_detection_dispatches_for_teams_with_geofences_and_machines(): void
    {
        Queue::fake();

        $team = Team::factory()->create();
        Machine::factory()->create(['team_id' => $team->id]);
        Geofence::factory()->create(['team_id' => $team->id]);

        $machinesOnly = Team::factory()->create();
        Machine::factory()->create(['team_id' => $machinesOnly->id]); // no geofences -- must not dispatch

        $this->invokeScheduler('scheduleGeofenceDetection');

        Queue::assertPushed(GeofenceCrossingDetectionJob::class, 1);
    }
}
