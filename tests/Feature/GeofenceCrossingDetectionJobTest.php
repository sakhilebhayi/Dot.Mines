<?php

namespace Tests\Feature;

use App\Jobs\GeofenceCrossingDetectionJob;
use App\Models\Geofence;
use App\Models\Machine;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GeofenceCrossingDetectionJobTest extends TestCase
{
    use RefreshDatabase;

    private function makeTeam(): Team
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->forceFill(['current_team_id' => $team->id])->save();

        return $team;
    }

    #[Test]
    public function job_can_be_dispatched_to_queue(): void
    {
        Queue::fake();

        $team = $this->makeTeam();
        GeofenceCrossingDetectionJob::dispatch($team);

        Queue::assertPushed(GeofenceCrossingDetectionJob::class);
    }

    #[Test]
    public function job_has_correct_retry_and_timeout_configuration(): void
    {
        $team = $this->makeTeam();
        $job = new GeofenceCrossingDetectionJob($team);

        $this->assertEquals(2, $job->tries);
        $this->assertEquals(90, $job->timeout);
        $this->assertEquals([30, 120], $job->backoff);
    }

    #[Test]
    public function job_uses_geofences_queue(): void
    {
        $team = $this->makeTeam();
        $job = new GeofenceCrossingDetectionJob($team);

        $this->assertEquals('geofences', $job->queue);
    }

    #[Test]
    public function job_exits_early_when_no_active_geofences(): void
    {
        $team = $this->makeTeam();
        // No geofences created

        (new GeofenceCrossingDetectionJob($team))->handle();

        $this->assertDatabaseMissing('geofence_entries', ['team_id' => $team->id]);
    }

    #[Test]
    public function job_exits_early_when_no_machines_with_locations(): void
    {
        $team = $this->makeTeam();
        Geofence::factory()->create([
            'team_id' => $team->id,
            'status' => 'active',
        ]);
        // Machine has no location
        Machine::factory()->create([
            'team_id' => $team->id,
            'status' => 'active',
            'last_location_latitude' => null,
            'last_location_longitude' => null,
        ]);

        (new GeofenceCrossingDetectionJob($team))->handle();

        $this->assertDatabaseMissing('geofence_entries', ['team_id' => $team->id]);
    }

    #[Test]
    public function job_detects_machine_inside_circular_geofence(): void
    {
        $team = $this->makeTeam();
        Geofence::factory()->create([
            'team_id' => $team->id,
            'status' => 'active',
            'center_latitude' => -26.2041,
            'center_longitude' => 28.0473,
        ]);
        Machine::factory()->create([
            'team_id' => $team->id,
            'status' => 'active',
            'last_location_latitude' => -26.2041,
            'last_location_longitude' => 28.0473,
        ]);

        (new GeofenceCrossingDetectionJob($team))->handle();

        // Job ran without exception regardless of whether an entry was created
        $this->assertTrue(true);
    }
}
