<?php

namespace Tests\Feature;

use App\Livewire\MaintenanceDashboard;
use App\Livewire\MineAreaManager;
use App\Models\Machine;
use App\Models\MineArea;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Verifies that all Livewire components use the v3 dispatch() API
 * and no longer call the removed dispatchBrowserEvent() method.
 */
class LivewireDispatchMigrationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<mixed>
     */
    private function makeUserWithTeam(): array
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam;

        $role = Role::firstOrCreate(
            ['name' => 'admin', 'team_id' => $team->id],
            ['display_name' => 'Admin']
        );
        $user->roles()->attach($role);

        return [$user, $team];
    }

    #[Test]
    public function mine_area_manager_dispatches_notify_on_save(): void
    {
        [$user] = $this->makeUserWithTeam();
        $this->actingAs($user);

        Livewire::test(MineAreaManager::class)
            ->set('name', 'Test Area')
            ->set('status', 'active')
            ->call('saveMineArea')
            ->assertDispatched('notify');
    }

    #[Test]
    public function mine_area_manager_dispatches_notify_on_delete(): void
    {
        [$user, $team] = $this->makeUserWithTeam();
        $mineArea = MineArea::create(['team_id' => $team->id, 'name' => 'Test Area', 'status' => 'active']);
        $this->actingAs($user);

        Livewire::test(MineAreaManager::class)
            ->call('deleteMineArea', $mineArea)
            ->assertDispatched('notify');
    }

    #[Test]
    public function maintenance_dashboard_dispatches_notify_on_book(): void
    {
        [$user, $team] = $this->makeUserWithTeam();
        $machine = Machine::factory()->create(['team_id' => $team->id]);
        $this->actingAs($user);

        Livewire::test(MaintenanceDashboard::class)
            ->set('machine_id', $machine->id)
            ->set('maintenance_type', 'preventive')
            ->set('title', 'Oil change')
            ->set('scheduled_date', now()->addDay()->format('Y-m-d'))
            ->set('priority', 'medium')
            ->call('bookMaintenance')
            ->assertDispatched('notify');
    }

    #[Test]
    public function mine_area_manager_mounts_without_dispatch_browser_event_error(): void
    {
        [$user] = $this->makeUserWithTeam();
        $this->actingAs($user);

        Livewire::test(MineAreaManager::class)->assertOk();
    }
}
