<?php

namespace Tests\Feature;

use App\Livewire\ProductionDashboard;
use App\Models\MineArea;
use App\Models\ProductionRecord;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProductionDashboardTest extends TestCase
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

        Livewire::test(ProductionDashboard::class)->assertOk();
    }

    #[Test]
    public function creating_a_production_record_persists_to_database(): void
    {
        [$user, $team] = $this->makeAdminUser();
        $mineArea = MineArea::factory()->create(['team_id' => $team->id]);

        $this->actingAs($user);

        Livewire::test(ProductionDashboard::class)
            ->set('record_date', now()->format('Y-m-d'))
            ->set('shift', 'day')
            ->set('quantity_produced', '500')
            ->set('target_quantity', '600')
            ->set('mine_area_id', $mineArea->id)
            ->set('status', 'completed')
            ->call('saveRecord');

        $this->assertDatabaseHas('production_records', [
            'team_id' => $team->id,
            'shift' => 'day',
            'quantity_produced' => 500,
            'status' => 'completed',
        ]);
    }

    #[Test]
    public function creating_record_fails_validation_without_required_fields(): void
    {
        [$user] = $this->makeAdminUser();
        $this->actingAs($user);

        Livewire::test(ProductionDashboard::class)
            ->set('record_date', '')
            ->set('shift', '')
            ->set('quantity_produced', '')
            ->call('saveRecord')
            ->assertHasErrors(['record_date', 'shift', 'quantity_produced']);
    }

    #[Test]
    public function editing_a_record_updates_the_database(): void
    {
        [$user, $team] = $this->makeAdminUser();
        $record = ProductionRecord::create([
            'team_id' => $team->id,
            'record_date' => now()->format('Y-m-d'),
            'shift' => 'day',
            'quantity_produced' => 300,
            'target_quantity' => 400,
            'status' => 'completed',
        ]);

        $this->actingAs($user);

        Livewire::test(ProductionDashboard::class)
            ->call('openEditModal', $record->id)
            ->set('quantity_produced', '750')
            ->call('saveRecord');

        $this->assertDatabaseHas('production_records', [
            'id' => $record->id,
            'quantity_produced' => 750,
        ]);
    }

    #[Test]
    public function deleting_record_removes_it_from_database(): void
    {
        [$user, $team] = $this->makeAdminUser();
        $record = ProductionRecord::create([
            'team_id' => $team->id,
            'record_date' => now()->format('Y-m-d'),
            'shift' => 'day',
            'quantity_produced' => 200,
            'target_quantity' => 300,
            'status' => 'completed',
        ]);

        $this->actingAs($user);

        Livewire::test(ProductionDashboard::class)
            ->call('deleteRecord', $record->id);

        $this->assertSoftDeleted('production_records', ['id' => $record->id]);
    }

    #[Test]
    public function deleting_record_from_another_team_throws_not_found(): void
    {
        [$user] = $this->makeAdminUser();
        [, $otherTeam] = $this->makeAdminUser();
        $record = ProductionRecord::create([
            'team_id' => $otherTeam->id,
            'record_date' => now()->format('Y-m-d'),
            'shift' => 'day',
            'quantity_produced' => 100,
            'target_quantity' => 150,
            'status' => 'completed',
        ]);

        $this->actingAs($user);

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        Livewire::test(ProductionDashboard::class)
            ->call('deleteRecord', $record->id);
    }

    #[Test]
    public function date_filter_updates_start_and_end_dates(): void
    {
        [$user] = $this->makeAdminUser();
        $this->actingAs($user);

        Livewire::test(ProductionDashboard::class)
            ->set('dateFilter', 'week')
            ->assertSet('startDate', now()->startOfWeek()->format('Y-m-d'))
            ->assertSet('endDate', now()->endOfWeek()->format('Y-m-d'));
    }

    #[Test]
    public function view_mode_switches_correctly(): void
    {
        [$user] = $this->makeAdminUser();
        $this->actingAs($user);

        Livewire::test(ProductionDashboard::class)
            ->assertSet('viewMode', 'overview')
            ->call('switchView', 'records')
            ->assertSet('viewMode', 'records');
    }
}
