<?php

namespace Tests\Unit;

use App\Models\FuelTank;
use App\Models\Team;
use App\Models\User;
use App\Services\FuelManagementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * fuel_transactions.currency defaults to 'ZAR' at the database level and was
 * never set in the create payload, so every transaction was 'ZAR' regardless
 * of the team's actual Team::currency preference (itself unreachable until
 * Team::$fillable was fixed to include it). recordTransaction() now defaults
 * a transaction's currency to its team's, so the two settings agree.
 */
class FuelManagementServiceCurrencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_recorded_transaction_defaults_to_the_teams_currency(): void
    {
        $team = Team::factory()->create(['currency' => 'EUR']);
        $tank = $this->makeTank($team);

        $transaction = app(FuelManagementService::class)->recordTransaction([
            'team_id' => $team->id,
            'fuel_tank_id' => $tank->id,
            'user_id' => User::factory()->create()->id,
            'transaction_type' => 'refill',
            'quantity_liters' => 100,
            'unit_price' => 20,
            'total_cost' => 2000,
            'fuel_type' => $tank->fuel_type,
            'transaction_date' => now(),
        ]);

        $this->assertSame('EUR', $transaction->currency);
    }

    public function test_an_explicitly_passed_currency_is_not_overridden(): void
    {
        $team = Team::factory()->create(['currency' => 'EUR']);
        $tank = $this->makeTank($team);

        $transaction = app(FuelManagementService::class)->recordTransaction([
            'team_id' => $team->id,
            'fuel_tank_id' => $tank->id,
            'user_id' => User::factory()->create()->id,
            'transaction_type' => 'refill',
            'quantity_liters' => 100,
            'unit_price' => 20,
            'total_cost' => 2000,
            'fuel_type' => $tank->fuel_type,
            'transaction_date' => now(),
            'currency' => 'GBP',
        ]);

        $this->assertSame('GBP', $transaction->currency);
    }

    /**
     * FuelTankFactory pulls in MineArea::factory() for mine_area_id, and
     * MineArea has no factory defined -- unrelated to this fix, so tanks are
     * created directly here (mine_area_id is nullable) instead of going
     * through FuelTank::factory().
     */
    private function makeTank(Team $team): FuelTank
    {
        return FuelTank::create([
            'team_id' => $team->id,
            'name' => 'Test Tank',
            'capacity_liters' => 10000,
            'current_level_liters' => 5000,
            'minimum_level_liters' => 1000,
            'fuel_type' => 'diesel',
            'status' => 'active',
        ]);
    }
}
