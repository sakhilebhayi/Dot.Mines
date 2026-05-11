<?php

namespace Tests\Feature;

use App\Livewire\HaulDispatchDashboard;
use App\Models\HaulDispatch;
use App\Models\Machine;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class HaulDispatchDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->forceFill(['current_team_id' => $team->id])->save();
        return $user;
    }

    public function test_loading_clears_after_mount_with_no_dispatches(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user);

        Livewire::test(HaulDispatchDashboard::class)
            ->assertSet('isLoading', false)
            ->assertSet('activeDispatches', []);
    }

    public function test_loading_clears_after_mount_with_active_dispatches(): void
    {
        $user = $this->makeUser();
        $team = $user->currentTeam;
        $machine = Machine::create([
            'team_id'             => $team->id,
            'name'                => 'Hauler 1',
            'machine_type'        => 'haul_truck',
            'registration_number' => 'H-001',
            'serial_number'       => 'SN-001',
            'status'              => 'active',
        ]);

        HaulDispatch::create([
            'team_id'           => $team->id,
            'machine_id'        => $machine->id,
            'status'            => 'hauling',
            'current_speed_kmh' => 25.0,
            'current_tonnage'   => 100.0,
        ]);

        $this->actingAs($user);

        Livewire::test(HaulDispatchDashboard::class)
            ->assertSet('isLoading', false)
            ->assertCount('activeDispatches', 1);
    }

    public function test_completed_dispatches_are_excluded(): void
    {
        $user = $this->makeUser();
        $team = $user->currentTeam;
        $machine = Machine::create([
            'team_id'             => $team->id,
            'name'                => 'Hauler 2',
            'machine_type'        => 'haul_truck',
            'registration_number' => 'H-002',
            'serial_number'       => 'SN-002',
            'status'              => 'active',
        ]);

        HaulDispatch::create([
            'team_id'           => $team->id,
            'machine_id'        => $machine->id,
            'status'            => 'completed',
            'completed_at'      => now(),
            'current_speed_kmh' => 0.0,
            'current_tonnage'   => 0.0,
        ]);

        $this->actingAs($user);

        Livewire::test(HaulDispatchDashboard::class)
            ->assertSet('isLoading', false)
            ->assertSet('activeDispatches', []);
    }

    public function test_filter_by_status_works(): void
    {
        $user = $this->makeUser();
        $team = $user->currentTeam;
        $machine = Machine::create([
            'team_id'             => $team->id,
            'name'                => 'Hauler 3',
            'machine_type'        => 'haul_truck',
            'registration_number' => 'H-003',
            'serial_number'       => 'SN-003',
            'status'              => 'active',
        ]);

        HaulDispatch::create([
            'team_id'           => $team->id,
            'machine_id'        => $machine->id,
            'status'            => 'loading',
            'current_speed_kmh' => 0.0,
            'current_tonnage'   => 0.0,
        ]);

        $this->actingAs($user);

        Livewire::test(HaulDispatchDashboard::class)
            ->assertSet('isLoading', false)
            ->assertCount('activeDispatches', 1)
            ->call('filterByStatus', 'hauling')
            ->assertSet('statusFilter', 'hauling')
            ->assertSet('isLoading', false)
            ->assertSet('activeDispatches', []);
    }

    public function test_select_dispatch_toggles_selection(): void
    {
        $user = $this->makeUser();
        $team = $user->currentTeam;
        $machine = Machine::create([
            'team_id'             => $team->id,
            'name'                => 'Hauler 4',
            'machine_type'        => 'haul_truck',
            'registration_number' => 'H-004',
            'serial_number'       => 'SN-004',
            'status'              => 'active',
        ]);

        $dispatch = HaulDispatch::create([
            'team_id'           => $team->id,
            'machine_id'        => $machine->id,
            'status'            => 'hauling',
            'current_speed_kmh' => 20.0,
            'current_tonnage'   => 80.0,
        ]);

        $this->actingAs($user);

        Livewire::test(HaulDispatchDashboard::class)
            ->call('selectDispatch', $dispatch->id)
            ->assertSet('selectedDispatchId', $dispatch->id)
            ->call('selectDispatch', $dispatch->id)
            ->assertSet('selectedDispatchId', null);
    }
}
