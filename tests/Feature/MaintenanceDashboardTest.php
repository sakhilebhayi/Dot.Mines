<?php

namespace Tests\Feature;

use App\Livewire\MaintenanceDashboard;
use App\Models\Machine;
use App\Models\MaintenanceRecord;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MaintenanceDashboardTest extends TestCase
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

        Livewire::test(MaintenanceDashboard::class)->assertOk();
    }

    #[Test]
    public function booking_maintenance_creates_record(): void
    {
        [$user, $team] = $this->makeAdminUser();
        $machine = Machine::factory()->create(['team_id' => $team->id]);

        $this->actingAs($user);

        Livewire::test(MaintenanceDashboard::class)
            ->set('machine_id', $machine->id)
            ->set('maintenance_type', 'preventive')
            ->set('title', 'Engine oil change')
            ->set('scheduled_date', now()->addDay()->format('Y-m-d'))
            ->set('priority', 'medium')
            ->call('bookMaintenance');

        $this->assertDatabaseHas('maintenance_records', [
            'team_id' => $team->id,
            'machine_id' => $machine->id,
            'title' => 'Engine oil change',
            'maintenance_type' => 'preventive',
            'status' => 'scheduled',
        ]);
    }

    #[Test]
    public function booking_maintenance_fails_without_required_fields(): void
    {
        [$user] = $this->makeAdminUser();
        $this->actingAs($user);

        Livewire::test(MaintenanceDashboard::class)
            ->set('machine_id', '')
            ->set('title', '')
            ->call('bookMaintenance')
            ->assertHasErrors(['machine_id', 'title']);
    }

    #[Test]
    public function booking_rejects_machine_from_another_team(): void
    {
        [$user] = $this->makeAdminUser();
        [, $otherTeam] = $this->makeAdminUser();
        $machine = Machine::factory()->create(['team_id' => $otherTeam->id]);

        $this->actingAs($user);

        Livewire::test(MaintenanceDashboard::class)
            ->set('machine_id', $machine->id)
            ->set('maintenance_type', 'preventive')
            ->set('title', 'Sneaky schedule')
            ->set('scheduled_date', now()->addDay()->format('Y-m-d'))
            ->set('priority', 'medium')
            ->call('bookMaintenance');

        $this->assertDatabaseCount('maintenance_records', 0);
    }

    #[Test]
    public function completing_scheduled_maintenance_sets_status_and_timestamp(): void
    {
        [$user, $team] = $this->makeAdminUser();
        $machine = Machine::factory()->create(['team_id' => $team->id]);
        $record = MaintenanceRecord::factory()->create([
            'team_id' => $team->id,
            'machine_id' => $machine->id,
            'status' => 'scheduled',
        ]);

        $this->actingAs($user);

        Livewire::test(MaintenanceDashboard::class)
            ->call('completeScheduledMaintenance', $record->id);

        $this->assertDatabaseHas('maintenance_records', [
            'id' => $record->id,
            'status' => 'completed',
            'completed_by' => $user->id,
        ]);
        $this->assertNotNull($record->fresh()->completed_at);
    }

    #[Test]
    public function cancelling_scheduled_maintenance_sets_cancelled_status(): void
    {
        [$user, $team] = $this->makeAdminUser();
        $machine = Machine::factory()->create(['team_id' => $team->id]);
        $record = MaintenanceRecord::factory()->create([
            'team_id' => $team->id,
            'machine_id' => $machine->id,
            'status' => 'scheduled',
        ]);

        $this->actingAs($user);

        Livewire::test(MaintenanceDashboard::class)
            ->call('cancelScheduledMaintenance', $record->id);

        $this->assertDatabaseHas('maintenance_records', [
            'id' => $record->id,
            'status' => 'cancelled',
        ]);
    }

    #[Test]
    public function completing_record_from_another_team_is_rejected(): void
    {
        [$user] = $this->makeAdminUser();
        [, $otherTeam] = $this->makeAdminUser();
        $record = MaintenanceRecord::factory()->create([
            'team_id' => $otherTeam->id,
            'status' => 'scheduled',
        ]);

        $this->actingAs($user);

        Livewire::test(MaintenanceDashboard::class)
            ->call('completeScheduledMaintenance', $record->id);

        // Status must remain unchanged
        $this->assertSame('scheduled', $record->fresh()->status);
    }

    #[Test]
    public function booking_rejects_past_scheduled_date(): void
    {
        [$user, $team] = $this->makeAdminUser();
        $machine = Machine::factory()->create(['team_id' => $team->id]);

        $this->actingAs($user);

        Livewire::test(MaintenanceDashboard::class)
            ->set('machine_id', $machine->id)
            ->set('maintenance_type', 'preventive')
            ->set('title', 'Past booking')
            ->set('scheduled_date', now()->subDay()->format('Y-m-d'))
            ->set('priority', 'medium')
            ->call('bookMaintenance')
            ->assertHasErrors(['scheduled_date']);
    }
}
