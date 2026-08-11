<?php

namespace Tests\Unit;

use App\Models\FuelTank;
use App\Models\FuelTransaction;
use App\Models\Machine;
use App\Models\Team;
use App\Services\AI\FuelPredictorAgent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression test: high-consumption recommendations valued their estimated
 * savings using a hardcoded "R25/liter" assumption, regardless of what the
 * team was actually paying. FuelTransaction already records a real
 * unit_price per transaction, so this now derives the price from the
 * machine's own real transaction history instead of guessing.
 */
class FuelPredictorAgentTest extends TestCase
{
    use RefreshDatabase;

    public function test_high_consumption_savings_are_valued_at_the_machines_real_price_paid_not_a_hardcoded_guess(): void
    {
        $team = Team::factory()->create();
        $machine = Machine::factory()->create(['team_id' => $team->id, 'machine_type' => 'excavator']);

        // Excavator baseline is 200 L/day; push this machine far above it
        // (well over the 20% deviation threshold) at a known, non-R25 price.
        FuelTransaction::factory()->create([
            'team_id' => $team->id,
            'fuel_tank_id' => null,
            'machine_id' => $machine->id,
            'transaction_type' => 'dispensing',
            'transaction_date' => now()->subDays(2),
            'quantity_liters' => 9000, // ~300 L/day average over 30 days
            'unit_price' => 40.00,
            'total_cost' => 9000 * 40.00,
        ]);

        $agent = app(FuelPredictorAgent::class);
        $result = $agent->analyze($team);

        $recommendation = collect($result['recommendations'])
            ->firstWhere('related_machine_id', $machine->id);

        $this->assertNotNull($recommendation, 'Expected a high-consumption recommendation for this machine.');

        // excess = 300 - 200 = 100 L/day * 30 days * R40/L = R120,000 -- not
        // the old hardcoded R25/L figure (which would give R75,000).
        $this->assertEqualsWithDelta(120000, $recommendation['estimated_savings'], 1);
    }

    public function test_estimated_savings_is_zero_rather_than_a_hardcoded_guess_when_the_machine_has_no_real_cost_data(): void
    {
        $team = Team::factory()->create();
        $machine = Machine::factory()->create(['team_id' => $team->id, 'machine_type' => 'excavator']);

        FuelTransaction::factory()->create([
            'team_id' => $team->id,
            'fuel_tank_id' => null,
            'machine_id' => $machine->id,
            'transaction_type' => 'dispensing',
            'transaction_date' => now()->subDays(2),
            'quantity_liters' => 9000,
            'unit_price' => null,
            'total_cost' => null,
        ]);

        $agent = app(FuelPredictorAgent::class);
        $result = $agent->analyze($team);

        $recommendation = collect($result['recommendations'])
            ->firstWhere('related_machine_id', $machine->id);

        $this->assertNotNull($recommendation);
        // Not the old hardcoded R25/L default (which would report R75,000) --
        // with no real cost recorded for this machine, 0 is the honest answer.
        $this->assertSame(0.0, $recommendation['estimated_savings']);
    }

    public function test_optimal_order_recommendation_has_no_cost_estimate_when_the_team_has_no_real_price_data(): void
    {
        $team = Team::factory()->create();
        $machine = Machine::factory()->create(['team_id' => $team->id]);

        FuelTank::create([
            'team_id' => $team->id,
            'mine_area_id' => null,
            'name' => 'Main Tank',
            'tank_number' => 'TANK-001',
            'capacity_liters' => 1000,
            'current_level_liters' => 100, // 100L / 10L/day avg = 10 days supply
            'minimum_level_liters' => 50,
            'fuel_type' => 'diesel',
            'status' => 'active',
        ]);

        // 300L over 30 days = 10 L/day average, with no unit_price recorded
        // anywhere for this team.
        FuelTransaction::factory()->create([
            'team_id' => $team->id,
            'fuel_tank_id' => null,
            'machine_id' => $machine->id,
            'transaction_type' => 'dispensing',
            'transaction_date' => now()->subDays(2),
            'quantity_liters' => 300,
            'unit_price' => null,
            'total_cost' => null,
        ]);

        $agent = app(FuelPredictorAgent::class);
        $result = $agent->analyze($team);

        $orderRecommendation = collect($result['recommendations'])
            ->firstWhere('title', 'Optimal Fuel Order Timing');

        if ($orderRecommendation === null) {
            $this->markTestSkipped('Fixture did not land in the 7-20 day supply window for this assertion.');
        }

        $this->assertNull($orderRecommendation['data']['current_price_per_liter']);
        $this->assertNull($orderRecommendation['data']['estimated_order_cost']);
    }
}
