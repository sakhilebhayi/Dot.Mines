<?php

namespace Tests\Unit;

use App\Models\FuelTank;
use App\Models\FuelTransaction;
use App\Models\Machine;
use App\Models\Team;
use App\Models\User;
use App\Services\FuelReserveRunwayCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FuelReserveRunwayCalculatorTest extends TestCase
{
    use RefreshDatabase;

    private ?User $currentUser = null;

    private function actingAsTeamMember(): Team
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->update(['current_team_id' => $team->id]);
        $this->actingAs($user);
        $this->currentUser = $user;

        return $team;
    }

    private function createTank(Team $team, string $status, float $currentLevel, float $capacity = 10000): FuelTank
    {
        return FuelTank::create([
            'team_id' => $team->id,
            'name' => 'Tank '.uniqid(),
            'capacity_liters' => $capacity,
            'current_level_liters' => $currentLevel,
            'minimum_level_liters' => $capacity * 0.1,
            'fuel_type' => 'diesel',
            'status' => $status,
        ]);
    }

    private function createTransaction(Team $team, FuelTank $tank, string $type, float $quantity, \DateTimeInterface $date, ?Machine $machine = null): FuelTransaction
    {
        return FuelTransaction::create([
            'team_id' => $team->id,
            'fuel_tank_id' => $tank->id,
            'machine_id' => $machine?->id,
            'user_id' => $this->currentUser->id,
            'transaction_type' => $type,
            'quantity_liters' => $quantity,
            'unit_price' => 20,
            'total_cost' => $quantity * 20,
            'fuel_type' => 'diesel',
            'transaction_date' => $date,
        ]);
    }

    public function test_reserves_only_count_active_tanks(): void
    {
        $team = $this->actingAsTeamMember();
        $active = $this->createTank($team, 'active', 5000);
        $this->createTank($team, 'maintenance', 3000); // excluded

        $this->createTransaction($team, $active, 'dispensing', 100, now());

        $result = (new FuelReserveRunwayCalculator)->calculate();

        $this->assertEqualsWithDelta(5000.0, $result['current_reserves_liters'], 0.01);
    }

    public function test_consumption_rate_excludes_non_dispensing_types(): void
    {
        $team = $this->actingAsTeamMember();
        $tank = $this->createTank($team, 'active', 5000);

        $this->createTransaction($team, $tank, 'dispensing', 100, now());
        $this->createTransaction($team, $tank, 'refill', 9999, now());
        $this->createTransaction($team, $tank, 'theft', 500, now());

        $result = (new FuelReserveRunwayCalculator)->calculate();

        $this->assertEqualsWithDelta(100.0, $result['daily_consumption_liters'], 0.01);
    }

    public function test_insufficient_data_when_no_tanks(): void
    {
        $this->actingAsTeamMember();

        $result = (new FuelReserveRunwayCalculator)->calculate();

        $this->assertFalse($result['available']);
        $this->assertSame('insufficient_data', $result['reason']);
    }

    public function test_insufficient_data_when_no_dispensing_transactions(): void
    {
        $team = $this->actingAsTeamMember();
        $this->createTank($team, 'active', 5000);
        // No transactions at all.

        $result = (new FuelReserveRunwayCalculator)->calculate();

        $this->assertFalse($result['available']);
    }

    public function test_days_computed_correctly(): void
    {
        $team = $this->actingAsTeamMember();
        $tank = $this->createTank($team, 'active', 3000);

        $this->createTransaction($team, $tank, 'dispensing', 100, now());

        $result = (new FuelReserveRunwayCalculator)->calculate();

        $this->assertSame(30, $result['days']); // 3000 / 100
    }

    public function test_what_if_identifies_top_consuming_machine(): void
    {
        $team = $this->actingAsTeamMember();
        $tank = $this->createTank($team, 'active', 5000);
        $bigMachine = Machine::create(['team_id' => $team->id, 'name' => 'Excavator 1', 'status' => 'active', 'machine_type' => 'excavator']);
        $smallMachine = Machine::create(['team_id' => $team->id, 'name' => 'Truck 1', 'status' => 'active', 'machine_type' => 'truck']);

        $this->createTransaction($team, $tank, 'dispensing', 300, now(), $bigMachine);
        $this->createTransaction($team, $tank, 'dispensing', 50, now(), $smallMachine);

        $result = (new FuelReserveRunwayCalculator)->calculate();

        $this->assertNotNull($result['what_if']);
        $this->assertSame('Excavator 1', $result['what_if']['machine_name']);
    }

    public function test_what_if_is_null_without_machine_attributed_dispensing(): void
    {
        $team = $this->actingAsTeamMember();
        $tank = $this->createTank($team, 'active', 5000);

        $this->createTransaction($team, $tank, 'dispensing', 100, now(), null);

        $result = (new FuelReserveRunwayCalculator)->calculate();

        $this->assertNull($result['what_if']);
    }

    public function test_a_second_teams_data_never_affects_the_first_teams_figures(): void
    {
        $team = $this->actingAsTeamMember();
        $tank = $this->createTank($team, 'active', 5000);
        $this->createTransaction($team, $tank, 'dispensing', 100, now());

        $otherTeam = Team::factory()->create();
        $otherTank = FuelTank::create([
            'team_id' => $otherTeam->id, 'name' => 'Other Tank', 'capacity_liters' => 99999,
            'current_level_liters' => 99999, 'minimum_level_liters' => 100, 'fuel_type' => 'diesel', 'status' => 'active',
        ]);
        FuelTransaction::create([
            'team_id' => $otherTeam->id, 'fuel_tank_id' => $otherTank->id, 'user_id' => $this->currentUser->id, 'transaction_type' => 'dispensing',
            'quantity_liters' => 9999, 'unit_price' => 20, 'total_cost' => 9999 * 20, 'fuel_type' => 'diesel', 'transaction_date' => now(),
        ]);

        $result = (new FuelReserveRunwayCalculator)->calculate();

        $this->assertEqualsWithDelta(5000.0, $result['current_reserves_liters'], 0.01);
    }
}
