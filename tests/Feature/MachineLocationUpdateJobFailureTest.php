<?php

namespace Tests\Feature;

use App\Jobs\MachineLocationUpdateJob;
use App\Models\Integration;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Integration::last_error is exposed directly via Api\IntegrationController
 * ::show() and the Integration Manager UI (both real, live paths -- not
 * hypothetical). This job's failed() handler used to store the raw
 * exception message verbatim, which can include third-party API response
 * bodies or other internal detail. The real message is still logged;
 * last_error now gets a generic, safe one.
 */
class MachineLocationUpdateJobFailureTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_failure_does_not_store_the_raw_exception_message(): void
    {
        $team = Team::factory()->create();
        $integration = Integration::factory()->forProvider('volvo')->create(['team_id' => $team->id]);

        $job = new MachineLocationUpdateJob($integration);
        $job->failed(new \RuntimeException('SQLSTATE[08006]: connection to server at "internal-db.private" failed'));

        $integration->refresh();

        $this->assertNotNull($integration->last_error);
        $this->assertStringNotContainsString('SQLSTATE', $integration->last_error);
        $this->assertStringNotContainsString('internal-db.private', $integration->last_error);
        $this->assertNotNull($integration->last_error_at);
    }
}
