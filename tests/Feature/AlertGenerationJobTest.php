<?php

namespace Tests\Feature;

use App\Jobs\AlertGenerationJob;
use App\Models\Machine;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AlertGenerationJobTest extends TestCase
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

        AlertGenerationJob::dispatch($team);

        Queue::assertPushed(AlertGenerationJob::class);
    }

    #[Test]
    public function job_has_correct_retry_and_timeout_configuration(): void
    {
        $team = $this->makeTeam();
        $job = new AlertGenerationJob($team);

        $this->assertEquals(2, $job->tries);
        $this->assertEquals(60, $job->timeout);
        $this->assertEquals([30, 120], $job->backoff);
    }

    #[Test]
    public function job_uses_alerts_queue(): void
    {
        $team = $this->makeTeam();
        $job = new AlertGenerationJob($team);

        $this->assertEquals('alerts', $job->queue);
    }

    #[Test]
    public function job_runs_without_error_when_no_machines_exist(): void
    {
        $team = $this->makeTeam();

        // No machines — should complete silently
        (new AlertGenerationJob($team))->handle();

        $this->assertTrue(true); // If we reach here no exception was thrown
    }

    #[Test]
    public function job_runs_without_error_with_active_machine(): void
    {
        $team = $this->makeTeam();
        Machine::factory()->create([
            'team_id' => $team->id,
            'status' => 'active',
        ]);

        (new AlertGenerationJob($team))->handle();

        $this->assertTrue(true);
    }

    #[Test]
    public function job_skips_offline_machines(): void
    {
        $team = $this->makeTeam();
        Machine::factory()->create([
            'team_id' => $team->id,
            'status' => 'offline',
        ]);

        // Offline machines are excluded from alert evaluation
        (new AlertGenerationJob($team))->handle();

        $this->assertDatabaseMissing('alerts', ['team_id' => $team->id]);
    }
}
