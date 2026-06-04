<?php

namespace Tests\Feature;

use App\Livewire\FuelManagement;
use App\Models\FuelMonthlyAllocation;
use App\Models\FuelTank;
use App\Models\MineArea;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FuelManagementTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdminUser(): array
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id, 'personal_team' => true]);
        $user->forceFill(['current_team_id' => $team->id])->save();

        $role = Role::firstOrCreate(
            ['name' => 'admin'],
            ['display_name' => 'Admin', 'team_id' => $team->id]
        );
        $user->roles()->attach($role);

        return [$user, $team];
    }

    #[Test]
    public function component_mounts_successfully(): void
    {
        [$user] = $this->makeAdminUser();
        $this->actingAs($user);

        Livewire::test(FuelManagement::class)->assertOk();
    }

    #[Test]
    public function creating_a_fuel_tank_persists_to_database(): void
    {
        [$user, $team] = $this->makeAdminUser();
        $this->actingAs($user);

        Livewire::test(FuelManagement::class)
            ->set('tankName', 'Main Diesel Tank')
            ->set('tankNumber', 'TK-001')
            ->set('tankCapacity', 10000)
            ->set('tankMinimumLevel', 500)
            ->set('tankFuelType', 'diesel')
            ->call('saveTank');

        $this->assertDatabaseHas('fuel_tanks', [
            'team_id' => $team->id,
            'name' => 'Main Diesel Tank',
            'tank_number' => 'TK-001',
            'capacity_liters' => 10000,
            'fuel_type' => 'diesel',
        ]);
    }

    #[Test]
    public function creating_tank_fails_validation_without_required_fields(): void
    {
        [$user] = $this->makeAdminUser();
        $this->actingAs($user);

        Livewire::test(FuelManagement::class)
            ->set('tankName', '')
            ->set('tankCapacity', '')
            ->call('saveTank')
            ->assertHasErrors(['tankName', 'tankCapacity']);
    }

    #[Test]
    public function creating_tank_rejects_invalid_fuel_type(): void
    {
        [$user] = $this->makeAdminUser();
        $this->actingAs($user);

        Livewire::test(FuelManagement::class)
            ->set('tankName', 'Tank A')
            ->set('tankCapacity', 5000)
            ->set('tankMinimumLevel', 100)
            ->set('tankFuelType', 'rocket_fuel')
            ->call('saveTank')
            ->assertHasErrors(['tankFuelType']);
    }

    #[Test]
    public function dispensing_transaction_blocked_without_allocation(): void
    {
        [$user, $team] = $this->makeAdminUser();
        $mineArea = MineArea::factory()->create(['team_id' => $team->id]);
        $tank = FuelTank::factory()->create([
            'team_id' => $team->id,
            'mine_area_id' => $mineArea->id,
            'capacity_liters' => 5000,
            'current_level_liters' => 4000,
            'fuel_type' => 'diesel',
        ]);

        $this->actingAs($user);

        Livewire::test(FuelManagement::class)
            ->set('transactionTankId', $tank->id)
            ->set('transactionQuantity', 100)
            ->call('recordDispensingTransaction');

        // No transaction should be created — no allocation exists
        $this->assertDatabaseCount('fuel_transactions', 0);
    }

    #[Test]
    public function dispensing_transaction_is_blocked_when_quantity_exceeds_allocation(): void
    {
        [$user, $team] = $this->makeAdminUser();
        $mineArea = MineArea::factory()->create(['team_id' => $team->id]);
        $tank = FuelTank::factory()->create([
            'team_id' => $team->id,
            'mine_area_id' => $mineArea->id,
            'capacity_liters' => 10000,
            'current_level_liters' => 9000,
            'fuel_type' => 'diesel',
        ]);

        FuelMonthlyAllocation::create([
            'team_id' => $team->id,
            'mine_area_id' => $mineArea->id,
            'year' => now()->year,
            'month' => now()->month,
            'allocated_liters' => 200,
            'remaining_liters' => 200,
            'consumed_liters' => 0,
            'fuel_price_per_liter' => 20,
            'total_budget_zar' => 4000,
            'remaining_budget_zar' => 4000,
            'spent_zar' => 0,
            'status' => 'active',
        ]);

        $this->actingAs($user);

        Livewire::test(FuelManagement::class)
            ->set('transactionTankId', $tank->id)
            ->set('transactionQuantity', 500)   // exceeds 200 L allocation
            ->call('recordDispensingTransaction');

        $this->assertDatabaseCount('fuel_transactions', 0);
    }

    #[Test]
    public function deleting_tank_removes_it_from_database(): void
    {
        [$user, $team] = $this->makeAdminUser();
        $tank = FuelTank::factory()->create(['team_id' => $team->id]);

        $this->actingAs($user);

        Livewire::test(FuelManagement::class)
            ->call('deleteTank', $tank->id);

        $this->assertDatabaseMissing('fuel_tanks', ['id' => $tank->id]);
    }

    #[Test]
    public function cross_team_tank_cannot_be_deleted(): void
    {
        [$user] = $this->makeAdminUser();
        [, $otherTeam] = $this->makeAdminUser();
        $tank = FuelTank::factory()->create(['team_id' => $otherTeam->id]);

        $this->actingAs($user);

        Livewire::test(FuelManagement::class)
            ->call('deleteTank', $tank->id);

        $this->assertDatabaseHas('fuel_tanks', ['id' => $tank->id]);
    }
}
