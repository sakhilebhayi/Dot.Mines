<?php

namespace Tests\Unit;

use App\Models\Geofence;
use App\Models\GeofenceEntry;
use App\Models\MineArea;
use App\Models\Team;
use App\Services\AI\DispatchAdvisorAgent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DispatchAdvisorAgentTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_recommends_rerouting_to_the_quieter_geofence_of_the_same_type(): void
    {
        $team = Team::factory()->create();
        $mineArea = MineArea::create(['team_id' => $team->id, 'name' => 'Test Area', 'status' => 'active']);

        $busyPit = Geofence::factory()->active()->create([
            'team_id' => $team->id,
            'mine_area_id' => $mineArea->id,
            'name' => 'Pit A',
            'type' => 'pit',
        ]);
        $quietPit = Geofence::factory()->active()->create([
            'team_id' => $team->id,
            'mine_area_id' => $mineArea->id,
            'name' => 'Pit B',
            'type' => 'pit',
        ]);

        // 3 machines currently queued (no exit_time) at the busy pit.
        GeofenceEntry::factory()->active()->count(3)->create([
            'team_id' => $team->id,
            'geofence_id' => $busyPit->id,
            'entry_time' => now()->subMinutes(20),
        ]);

        // None queued at the quiet pit.
        GeofenceEntry::factory()->completed()->count(2)->create([
            'team_id' => $team->id,
            'geofence_id' => $quietPit->id,
            'entry_time' => now()->subDays(1),
        ]);

        $agent = app(DispatchAdvisorAgent::class);
        $result = $agent->analyze($team);

        $this->assertCount(1, $result['recommendations']);
        $recommendation = $result['recommendations'][0];
        $this->assertSame('dispatch', $recommendation['category']);
        $this->assertStringContainsString('Pit B', $recommendation['proposed_action']);
        $this->assertSame(3, $recommendation['data']['busiest_queue']);
        $this->assertSame(0, $recommendation['data']['quietest_queue']);
    }

    public function test_it_does_not_recommend_anything_when_queues_are_balanced(): void
    {
        $team = Team::factory()->create();
        $mineArea = MineArea::create(['team_id' => $team->id, 'name' => 'Test Area', 'status' => 'active']);

        $pitA = Geofence::factory()->active()->create([
            'team_id' => $team->id,
            'mine_area_id' => $mineArea->id,
            'type' => 'pit',
        ]);
        $pitB = Geofence::factory()->active()->create([
            'team_id' => $team->id,
            'mine_area_id' => $mineArea->id,
            'type' => 'pit',
        ]);

        foreach ([$pitA, $pitB] as $pit) {
            GeofenceEntry::factory()->active()->create([
                'team_id' => $team->id,
                'geofence_id' => $pit->id,
                'entry_time' => now()->subMinutes(10),
            ]);
        }

        $agent = app(DispatchAdvisorAgent::class);
        $result = $agent->analyze($team);

        $this->assertCount(0, $result['recommendations']);
    }

    public function test_it_ignores_geofences_with_no_same_type_sibling_in_the_mine_area(): void
    {
        $team = Team::factory()->create();
        $mineArea = MineArea::create(['team_id' => $team->id, 'name' => 'Test Area', 'status' => 'active']);

        $onlyPit = Geofence::factory()->active()->create([
            'team_id' => $team->id,
            'mine_area_id' => $mineArea->id,
            'type' => 'pit',
        ]);

        GeofenceEntry::factory()->active()->count(5)->create([
            'team_id' => $team->id,
            'geofence_id' => $onlyPit->id,
            'entry_time' => now()->subMinutes(5),
        ]);

        $agent = app(DispatchAdvisorAgent::class);
        $result = $agent->analyze($team);

        $this->assertCount(0, $result['recommendations']);
    }

    /**
     * Gap-fill: nothing exercised the priority split (MIN_QUEUE_GAP=2 vs
     * 2x that for 'high'), the average-dwell-time calculation itself, its
     * 7-day history window, or the insight that's only supposed to appear
     * when the busiest geofence has real completed history.
     */
    public function test_it_assigns_medium_priority_when_the_gap_is_exactly_the_minimum(): void
    {
        $team = Team::factory()->create();
        $mineArea = MineArea::create(['team_id' => $team->id, 'name' => 'Test Area', 'status' => 'active']);

        $busyPit = Geofence::factory()->active()->create([
            'team_id' => $team->id, 'mine_area_id' => $mineArea->id, 'type' => 'pit',
        ]);
        $quietPit = Geofence::factory()->active()->create([
            'team_id' => $team->id, 'mine_area_id' => $mineArea->id, 'type' => 'pit',
        ]);

        GeofenceEntry::factory()->active()->count(2)->create([
            'team_id' => $team->id, 'geofence_id' => $busyPit->id, 'entry_time' => now()->subMinutes(10),
        ]);

        $result = app(DispatchAdvisorAgent::class)->analyze($team);

        $this->assertCount(1, $result['recommendations']);
        $this->assertSame('medium', $result['recommendations'][0]['priority']);
    }

    public function test_it_assigns_high_priority_when_the_gap_is_at_least_double_the_minimum(): void
    {
        $team = Team::factory()->create();
        $mineArea = MineArea::create(['team_id' => $team->id, 'name' => 'Test Area', 'status' => 'active']);

        $busyPit = Geofence::factory()->active()->create([
            'team_id' => $team->id, 'mine_area_id' => $mineArea->id, 'type' => 'pit',
        ]);
        $quietPit = Geofence::factory()->active()->create([
            'team_id' => $team->id, 'mine_area_id' => $mineArea->id, 'type' => 'pit',
        ]);

        GeofenceEntry::factory()->active()->count(4)->create([
            'team_id' => $team->id, 'geofence_id' => $busyPit->id, 'entry_time' => now()->subMinutes(10),
        ]);

        $result = app(DispatchAdvisorAgent::class)->analyze($team);

        $this->assertCount(1, $result['recommendations']);
        $this->assertSame('high', $result['recommendations'][0]['priority']);
    }

    public function test_it_averages_dwell_time_from_completed_entries_within_the_7_day_window_and_excludes_older_ones(): void
    {
        $team = Team::factory()->create();
        $mineArea = MineArea::create(['team_id' => $team->id, 'name' => 'Test Area', 'status' => 'active']);

        $busyPit = Geofence::factory()->active()->create([
            'team_id' => $team->id, 'mine_area_id' => $mineArea->id, 'name' => 'Busy Pit', 'type' => 'pit',
        ]);
        $quietPit = Geofence::factory()->active()->create([
            'team_id' => $team->id, 'mine_area_id' => $mineArea->id, 'name' => 'Quiet Pit', 'type' => 'pit',
        ]);

        // Queue imbalance so a recommendation (and its dwell data) is produced at all.
        GeofenceEntry::factory()->active()->count(3)->create([
            'team_id' => $team->id, 'geofence_id' => $busyPit->id, 'entry_time' => now()->subMinutes(10),
        ]);

        // Two completed entries inside the 7-day dwell-history window: 30 and 20 minutes -> average 25.
        GeofenceEntry::factory()->create([
            'team_id' => $team->id,
            'geofence_id' => $busyPit->id,
            'entry_time' => now()->subDays(1)->subMinutes(40),
            'exit_time' => now()->subDays(1)->subMinutes(10),
        ]);
        GeofenceEntry::factory()->create([
            'team_id' => $team->id,
            'geofence_id' => $busyPit->id,
            'entry_time' => now()->subDays(2)->subMinutes(20),
            'exit_time' => now()->subDays(2),
        ]);

        // A much longer completed entry, but 10 days old -- outside the window, must not skew the average.
        GeofenceEntry::factory()->create([
            'team_id' => $team->id,
            'geofence_id' => $busyPit->id,
            'entry_time' => now()->subDays(10)->subMinutes(1000),
            'exit_time' => now()->subDays(10),
        ]);

        $result = app(DispatchAdvisorAgent::class)->analyze($team);

        $this->assertCount(1, $result['recommendations']);
        $this->assertSame(25.0, $result['recommendations'][0]['data']['busiest_avg_dwell_minutes']);

        $this->assertCount(1, $result['insights']);
        $this->assertSame(25.0, $result['insights'][0]['data']['avg_dwell_minutes']);
        $this->assertStringContainsString('Busy Pit', $result['insights'][0]['title']);
    }

    public function test_it_reports_null_average_dwell_and_raises_no_insight_when_the_busiest_geofence_has_no_completed_history(): void
    {
        $team = Team::factory()->create();
        $mineArea = MineArea::create(['team_id' => $team->id, 'name' => 'Test Area', 'status' => 'active']);

        $busyPit = Geofence::factory()->active()->create([
            'team_id' => $team->id, 'mine_area_id' => $mineArea->id, 'type' => 'pit',
        ]);
        $quietPit = Geofence::factory()->active()->create([
            'team_id' => $team->id, 'mine_area_id' => $mineArea->id, 'type' => 'pit',
        ]);

        // Only ever-open entries at the busy pit -- nothing with an exit_time to average.
        GeofenceEntry::factory()->active()->count(2)->create([
            'team_id' => $team->id, 'geofence_id' => $busyPit->id, 'entry_time' => now()->subMinutes(10),
        ]);

        $result = app(DispatchAdvisorAgent::class)->analyze($team);

        $this->assertCount(1, $result['recommendations']);
        $this->assertNull($result['recommendations'][0]['data']['busiest_avg_dwell_minutes']);
        $this->assertCount(0, $result['insights']);
    }
}
