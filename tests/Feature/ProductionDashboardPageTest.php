<?php

namespace Tests\Feature;

use App\Livewire\ProductionDashboard;
use App\Models\OperatorFatigue;
use App\Models\ProductionRecord;
use App\Models\Team;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The /production page had no feature test coverage before this file, so
 * nothing verified the Blade view actually rendered. Added while re-theming
 * resources/views/livewire/production-dashboard.blade.php -- which was
 * almost entirely built on light-mode-base + dark:-override pairs that
 * collapse to dead CSS since the app always forces <html class="dark"> with
 * no toggle -- to confirm the page still compiles and renders, including the
 * empty states for the daily chart, material breakdown, fatigue table, and
 * area performance table.
 */
class ProductionDashboardPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_away_from_production_dashboard(): void
    {
        $response = $this->get('/production');

        $response->assertRedirect('/login');
    }

    public function test_authenticated_user_with_a_team_can_view_the_production_dashboard(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->update(['current_team_id' => $team->id]);

        $response = $this->actingAs($user)->get('/production');

        $response->assertOk();
        $response->assertSee('Production Dashboard');
    }

    /**
     * A telemetry-derived record (what the Bell integration sync writes)
     * must surface its real load/cycle counts and tonnage on the page --
     * the summary tiles used to count records, which would render one
     * "load" for a whole synced day of hauling.
     */
    public function test_telemetry_production_records_render_with_real_load_and_cycle_counts(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->update(['current_team_id' => $team->id]);

        ProductionRecord::create([
            'team_id' => $team->id,
            'record_date' => Carbon::yesterday(),
            'shift' => 'continuous',
            'quantity_produced' => 750.25,
            'unit' => 'tonnes',
            'status' => 'completed',
            'metadata' => ['source' => 'telemetry', 'provider' => 'bell', 'loads' => 150, 'cycles' => 150],
        ]);

        Livewire::actingAs($user)
            ->test(ProductionDashboard::class)
            ->assertViewHas('summary', function (array $summary) {
                return $summary['total_loads'] === 150
                    && $summary['total_cycles'] === 150
                    && abs($summary['total_tonnage'] - 750.25) < 0.01
                    && abs($summary['total_bcm'] - 750.25) < 0.01;
            })
            ->assertSee('750');
    }

    /**
     * dateFilter defaults to the string 'month' and used to be passed
     * straight into where('record_date', $this->dateFilter) -- an equality
     * match against a preset keyword instead of a range. SQLite tolerates
     * comparing a date column to an arbitrary string (silently returns zero
     * rows), which is why this shipped without failing tests; Postgres
     * rejects it outright with SQLSTATE[22007], a hard 500 on every load of
     * /production. Assert the filter now returns a real date-bounded range.
     */
    public function test_date_filter_returns_records_within_the_selected_range(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->update(['current_team_id' => $team->id]);

        $recent = ProductionRecord::create([
            'team_id' => $team->id,
            'record_date' => Carbon::today()->subDays(10),
            'shift' => 'day',
            'quantity_produced' => 100,
            'unit' => 'tonnes',
            'status' => 'completed',
        ]);

        $stale = ProductionRecord::create([
            'team_id' => $team->id,
            'record_date' => Carbon::today()->subYears(2),
            'shift' => 'day',
            'quantity_produced' => 50,
            'unit' => 'tonnes',
            'status' => 'completed',
        ]);

        // productionRecords is a computed property, not rendered by the
        // Blade view -- assert against the component's data directly rather
        // than the HTML output.
        $ids = Livewire::actingAs($user)
            ->test(ProductionDashboard::class)
            ->set('dateFilter', 'month')
            ->instance()
            ->productionRecords
            ->pluck('id');

        $this->assertTrue($ids->contains($recent->id));
        $this->assertFalse($ids->contains($stale->id));
    }

    public function test_fatigue_section_shows_real_operator_fatigue_rows_bucketed_by_canonical_alert_level(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->update(['current_team_id' => $team->id]);

        $makeShift = function (Team $forTeam, string $level, int $score) {
            $operator = User::factory()->create();

            return OperatorFatigue::create([
                'user_id' => $operator->id,
                'team_id' => $forTeam->id,
                'shift_date' => now()->toDateString(),
                'shift_type' => 'morning',
                'shift_start' => '06:00',
                'shift_end' => '15:00',
                'hours_worked' => 9,
                'consecutive_days' => 3,
                'fatigue_score' => $score,
                'alert_level' => $level,
            ]);
        };

        $makeShift($team, 'low', 25);
        $makeShift($team, 'medium', 45);
        $makeShift($team, 'critical', 85);

        // Another tenant's shift must never leak into this dashboard.
        $otherTeam = Team::factory()->create();
        $makeShift($otherTeam, 'critical', 95);

        $component = Livewire::actingAs($user)->test(ProductionDashboard::class);

        $fatigueData = $component->get('fatigueData');
        $this->assertCount(3, $fatigueData);
        // Ordered by fatigue score within the same shift date, worst first.
        $this->assertSame(85, $fatigueData[0]['fatigue_score']);
        $this->assertSame('critical', $fatigueData[0]['alert_level']);

        $stats = $component->get('fatigueStats');
        $this->assertSame(1, $stats['well_rested']);
        $this->assertSame(1, $stats['needs_monitoring']);
        $this->assertSame(0, $stats['high_fatigue']);
        $this->assertSame(1, $stats['needs_rest']);
        // The four buckets are disjoint and cover every listed operator.
        $this->assertSame(count($fatigueData), array_sum($stats));
    }

    public function test_fatigue_section_lists_only_the_latest_shift_per_operator(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->update(['current_team_id' => $team->id]);

        $operator = User::factory()->create();
        OperatorFatigue::create([
            'user_id' => $operator->id,
            'team_id' => $team->id,
            'shift_date' => now()->subDays(2)->toDateString(),
            'shift_type' => 'night',
            'shift_start' => '18:00',
            'shift_end' => '06:00',
            'hours_worked' => 12,
            'consecutive_days' => 5,
            'fatigue_score' => 70,
            'alert_level' => 'high',
        ]);
        OperatorFatigue::create([
            'user_id' => $operator->id,
            'team_id' => $team->id,
            'shift_date' => now()->toDateString(),
            'shift_type' => 'morning',
            'shift_start' => '06:00',
            'shift_end' => '14:00',
            'hours_worked' => 8,
            'consecutive_days' => 1,
            'fatigue_score' => 15,
            'alert_level' => 'none',
        ]);

        $component = Livewire::actingAs($user)->test(ProductionDashboard::class);

        $fatigueData = $component->get('fatigueData');
        $this->assertCount(1, $fatigueData, 'Only the most recent shift per operator should be listed.');
        $this->assertSame(15, $fatigueData[0]['fatigue_score']);
        $this->assertSame(['well_rested' => 1, 'needs_monitoring' => 0, 'high_fatigue' => 0, 'needs_rest' => 0], $component->get('fatigueStats'));
    }
}
