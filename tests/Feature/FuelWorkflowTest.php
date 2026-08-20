<?php

namespace Tests\Feature;

use App\Livewire\FuelManagement;
use App\Models\FuelMonthlyAllocation;
use App\Models\FuelTank;
use App\Models\Machine;
use App\Models\Team;
use App\Models\User;
use App\Services\AI\FuelPredictorAgent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The guided fuel workflow (Tank -> Allocation -> Dispensing -> Review)
 * that replaced the single three-tab "Manage Fuel" modal, plus the truth
 * fixes found while building it.
 */
class FuelWorkflowTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{Team, User}
     */
    private function teamWithUser(): array
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->update(['current_team_id' => $team->id]);

        return [$team, $user];
    }

    public function test_workflow_opens_at_the_first_incomplete_step(): void
    {
        [$team, $user] = $this->teamWithUser();

        // No tanks at all: start at step 1 (Tank).
        Livewire::actingAs($user)
            ->test(FuelManagement::class)
            ->call('openManageModal')
            ->assertSet('manageStep', 1);

        FuelTank::factory()->create(['team_id' => $team->id, 'status' => 'active']);

        // Tank exists but no allocation this month: start at step 2.
        Livewire::actingAs($user)
            ->test(FuelManagement::class)
            ->call('openManageModal')
            ->assertSet('manageStep', 2);
    }

    public function test_dispensing_step_is_gated_on_tank_and_allocation(): void
    {
        [$team, $user] = $this->teamWithUser();

        // No tanks: any forward step falls back to 1.
        Livewire::actingAs($user)
            ->test(FuelManagement::class)
            ->call('openManageModal')
            ->call('goToStep', 3)
            ->assertSet('manageStep', 1);

        FuelTank::factory()->create(['team_id' => $team->id, 'status' => 'active']);

        // Tank but no allocation: dispensing falls back to step 2.
        Livewire::actingAs($user)
            ->test(FuelManagement::class)
            ->call('openManageModal')
            ->call('goToStep', 3)
            ->assertSet('manageStep', 2);
    }

    public function test_dispensing_records_against_the_machine_and_advances_to_review(): void
    {
        [$team, $user] = $this->teamWithUser();
        $machine = Machine::factory()->create(['team_id' => $team->id]);
        $tank = FuelTank::factory()->create([
            'team_id' => $team->id,
            'status' => 'active',
            'capacity_liters' => 20000,
            'current_level_liters' => 20000,
        ]);
        FuelMonthlyAllocation::create([
            'team_id' => $team->id,
            'year' => now()->year,
            'month' => now()->month,
            'allocated_liters' => 10000,
            'consumed_liters' => 0,
            'remaining_liters' => 10000,
            'fuel_price_per_liter' => 20,
            'total_budget_zar' => 200000,
        ]);

        $component = Livewire::actingAs($user)
            ->test(FuelManagement::class)
            ->call('openManageModal')
            ->assertSet('manageStep', 3)
            ->set('transactionTankId', (string) $tank->id)
            ->set('transactionMachineId', (string) $machine->id)
            ->set('transactionQuantity', '400')
            ->call('recordDispensingTransaction')
            ->assertSet('transactionError', '')
            // Successful dispense lands on the Review step.
            ->assertSet('manageStep', 4);

        $this->assertDatabaseHas('fuel_transactions', [
            'team_id' => $team->id,
            'machine_id' => $machine->id,
            'fuel_tank_id' => $tank->id,
            'transaction_type' => 'dispensing',
        ]);

        // The trucks view answers: which machine, how much, from which tank.
        $activity = $component->viewData('machineFuelActivity');
        $this->assertCount(1, $activity);
        $this->assertSame($machine->id, $activity[0]['machine']->id);
        $this->assertEqualsWithDelta(400.0, $activity[0]['period_dispensed'], 0.01);
        $this->assertSame($tank->id, $activity[0]['last_dispense']->fuel_tank_id);
    }

    public function test_ai_agent_never_fabricates_a_supply_prediction_without_consumption_history(): void
    {
        [$team, $user] = $this->teamWithUser();
        FuelTank::factory()->create([
            'team_id' => $team->id,
            'status' => 'active',
            'capacity_liters' => 20000,
            'current_level_liters' => 20000,
        ]);

        $analysis = (new FuelPredictorAgent)->analyze($team);

        // A full tank with zero dispensing history used to produce
        // "CRITICAL: inventory will last 0 days (confidence 88%)".
        $titles = collect($analysis['recommendations'] ?? [])->pluck('title');
        $this->assertFalse($titles->contains('Low Fuel Inventory'), 'Fabricated low-inventory recommendation emitted with no consumption history.');
    }

    public function test_page_shows_no_ai_section_data_when_no_fuel_system_exists(): void
    {
        [, $user] = $this->teamWithUser();

        $component = Livewire::actingAs($user)->test(FuelManagement::class);

        $this->assertCount(0, $component->viewData('aiRecommendations'));
        $this->assertCount(0, $component->viewData('aiInsights'));
    }
}
