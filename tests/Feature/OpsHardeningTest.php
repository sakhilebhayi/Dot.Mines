<?php

namespace Tests\Feature;

use App\Jobs\ArchiveOldMetricsJob;
use App\Jobs\PurgeExpiredSoftDeletesJob;
use App\Models\Machine;
use App\Models\MineArea;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * C4 slice of the #27 split: liveness/readiness probes and the scheduled
 * data-retention jobs. Deploy smoke tests target /ready; the framework's
 * /up only proves the app boots, not that it can serve real requests.
 */
class OpsHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_ready_probe_returns_ok_when_the_database_is_reachable(): void
    {
        $response = $this->get('/ready');

        $response->assertOk();
        $response->assertJson(['status' => 'ready']);
    }

    public function test_health_probe_reports_component_checks(): void
    {
        $response = $this->get('/health');

        $response->assertOk();
        $response->assertJson(['status' => 'ok']);
        $response->assertJsonStructure(['status', 'timestamp', 'checks' => ['database', 'cache', 'queue', 'storage']]);
    }

    public function test_probes_do_not_require_authentication(): void
    {
        // Probes are hit by orchestrators and load balancers -- a redirect
        // to /login would read as "unhealthy" and restart healthy pods.
        $this->get('/ready')->assertOk();
        $this->get('/health')->assertOk();
    }

    public function test_archive_job_moves_only_rows_older_than_the_retention_window(): void
    {
        $team = Team::factory()->create();
        $machine = Machine::factory()->create(['team_id' => $team->id]);

        $oldId = DB::table('machine_metrics')->insertGetId([
            'team_id' => $team->id,
            'machine_id' => $machine->id,
            'recorded_at' => now()->subDays(120),
            'created_at' => now()->subDays(120),
            'updated_at' => now()->subDays(120),
        ]);
        $freshId = DB::table('machine_metrics')->insertGetId([
            'team_id' => $team->id,
            'machine_id' => $machine->id,
            'recorded_at' => now()->subDays(5),
            'created_at' => now()->subDays(5),
            'updated_at' => now()->subDays(5),
        ]);

        (new ArchiveOldMetricsJob(retentionDays: 90))->handle();

        $this->assertDatabaseMissing('machine_metrics', ['id' => $oldId]);
        $this->assertDatabaseHas('machine_metrics', ['id' => $freshId]);
        $this->assertDatabaseHas('machine_metrics_archive', ['machine_id' => $machine->id, 'team_id' => $team->id]);
        $this->assertSame(1, DB::table('machine_metrics_archive')->count());
    }

    public function test_purge_job_only_deletes_soft_deleted_rows_past_the_grace_period(): void
    {
        $team = Team::factory()->create();

        $expired = MineArea::create(['team_id' => $team->id, 'name' => 'Old Pit', 'status' => 'inactive']);
        $expired->delete();
        MineArea::withTrashed()->whereKey($expired->id)->update(['deleted_at' => now()->subDays(45)]);

        $recent = MineArea::create(['team_id' => $team->id, 'name' => 'Recent Pit', 'status' => 'inactive']);
        $recent->delete();

        $active = MineArea::create(['team_id' => $team->id, 'name' => 'Live Pit', 'status' => 'active']);

        (new PurgeExpiredSoftDeletesJob)->handle();

        $this->assertDatabaseMissing('mine_areas', ['id' => $expired->id]);
        $this->assertNotNull(MineArea::withTrashed()->find($recent->id), 'Rows inside the grace period must survive.');
        $this->assertNotNull(MineArea::find($active->id));
    }
}
