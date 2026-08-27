<?php

namespace Tests\Feature;

use App\Livewire\ProductionDashboard;
use App\Models\Machine;
use App\Models\ProductionRecord;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The Daily Production Trend chart must stay inside its card.
 *
 * The old markup put a rotated date label in every column's layout flow;
 * a rotated element keeps its UNROTATED box, so each column's min-content
 * width was the full label width and flex could not shrink it -- a month
 * or year range (31-365 columns) forced the row wider than the card and
 * the chart bled out of its container.
 */
class ProductionDashboardChartTest extends TestCase
{
    use RefreshDatabase;

    private function seedDays(Team $team, Machine $machine, int $days): void
    {
        for ($i = 0; $i < $days; $i++) {
            ProductionRecord::create([
                'team_id' => $team->id,
                'machine_id' => $machine->id,
                'record_date' => now()->subDays($i)->toDateString(),
                'shift' => 'day',
                'quantity_produced' => 100 + $i,
                'unit' => 'tonnes',
                'status' => 'completed',
            ]);
        }
    }

    public function test_a_quarter_of_daily_bars_renders_a_bounded_number_of_axis_labels(): void
    {
        $team = Team::factory()->create();
        $machine = Machine::factory()->create(['team_id' => $team->id, 'status' => 'active']);
        $this->seedDays($team, $machine, 90);
        $user = User::factory()->create(['current_team_id' => $team->id]);

        $component = Livewire::actingAs($user)->test(ProductionDashboard::class)
            ->set('startDate', now()->subDays(89)->toDateString())
            ->set('endDate', now()->toDateString());

        $html = $component->html();

        // Labels are thinned to ~10 per chart and live OUTSIDE layout flow;
        // every column that renders one is tagged data-chart-label.
        $labels = substr_count($html, 'data-chart-label');
        $this->assertGreaterThan(0, $labels);
        $this->assertLessThanOrEqual(24, $labels, 'Axis labels must be thinned on long ranges, not rendered per bar.');

        // Columns must be allowed to shrink below their content width, or
        // the row overflows the card on long ranges.
        $this->assertStringContainsString('min-w-0', $html);
    }

    public function test_a_week_of_bars_still_labels_every_day(): void
    {
        $team = Team::factory()->create();
        $machine = Machine::factory()->create(['team_id' => $team->id, 'status' => 'active']);
        $this->seedDays($team, $machine, 7);
        $user = User::factory()->create(['current_team_id' => $team->id]);

        $html = Livewire::actingAs($user)->test(ProductionDashboard::class)
            ->set('startDate', now()->subDays(6)->toDateString())
            ->set('endDate', now()->toDateString())
            ->html();

        // Short ranges keep a label under every bar (both charts).
        $this->assertSame(14, substr_count($html, 'data-chart-label'));
    }

    public function test_empty_data_renders_the_placeholder_not_a_broken_chart(): void
    {
        $team = Team::factory()->create();
        $user = User::factory()->create(['current_team_id' => $team->id]);

        $html = Livewire::actingAs($user)->test(ProductionDashboard::class)->html();

        $this->assertStringContainsString('No production data available', $html);
    }
}
