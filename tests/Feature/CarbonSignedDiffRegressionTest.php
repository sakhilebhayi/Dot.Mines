<?php

namespace Tests\Feature;

use App\Models\AIAgent;
use App\Models\AIAnalysisSession;
use App\Models\Geofence;
use App\Models\GeofenceEntry;
use App\Models\Machine;
use App\Models\Team;
use App\Models\User;
use App\Services\AI\AnomalyDetectorAgent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Carbon 3 made diffInX() signed floats: now()->diffInHours($past) is
 * NEGATIVE. Several call sites still assumed Carbon 2's absolute integers,
 * producing negative durations, comparisons that could never be true, and
 * -- for AIAnalysisSession::markAsCompleted on Postgres -- a fatal write of
 * a negative float into an integer column (SQLSTATE 22P02, observed live).
 * These tests pin the corrected, forward-measured behaviour.
 */
class CarbonSignedDiffRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_analysis_session_processing_time_is_a_non_negative_integer(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);
        $agent = AIAgent::factory()->create();

        $session = AIAnalysisSession::create([
            'team_id' => $team->id,
            'ai_agent_id' => $agent->id,
            'analysis_type' => 'on_demand',
            'status' => 'running',
            'started_at' => now()->subSeconds(3),
        ]);

        $session->markAsCompleted(['summary' => 'ok'], 2);

        $session->refresh();
        $this->assertSame('completed', $session->status);
        // Previously now()->diffInMilliseconds($past) => about -3000.0, which
        // Postgres rejects for the integer processing_time_ms column.
        $this->assertIsInt($session->processing_time_ms);
        $this->assertGreaterThanOrEqual(3000, $session->processing_time_ms);
    }

    public function test_geofence_entry_duration_is_positive(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);
        $machine = Machine::factory()->create(['team_id' => $team->id]);
        $geofence = Geofence::factory()->create(['team_id' => $team->id]);

        $entry = GeofenceEntry::factory()->create([
            'team_id' => $team->id,
            'machine_id' => $machine->id,
            'geofence_id' => $geofence->id,
            'entry_time' => now()->subMinutes(45),
            'exit_time' => now(),
        ]);

        // Previously exit->diffInMinutes(entry) => -45.
        $this->assertSame(45, $entry->getDurationMinutes());
    }

    public function test_stale_machine_anomaly_fires_for_machines_not_updated_in_a_day(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);
        $machine = Machine::factory()->create([
            'team_id' => $team->id,
            'status' => 'active',
            'mine_area_id' => null,
        ]);
        Machine::where('id', $machine->id)->update(['updated_at' => now()->subHours(30)]);

        $result = app(AnomalyDetectorAgent::class)->analyze($team);

        // Previously now()->diffInHours($past) => -30, so "> 24" was never
        // true and this anomaly could not fire at all.
        $stale = collect($result['insights'])->firstWhere('title', 'Stale Machine Data');
        $this->assertNotNull($stale, 'Stale-data anomaly did not fire for a machine 30 hours out of date.');
        $this->assertGreaterThan(24, $stale['data']['hours_since_update']);
    }
}
