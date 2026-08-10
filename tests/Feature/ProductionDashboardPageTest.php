<?php

namespace Tests\Feature;

use App\Livewire\ProductionDashboard;
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
}
