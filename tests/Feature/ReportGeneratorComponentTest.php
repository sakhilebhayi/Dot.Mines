<?php

namespace Tests\Feature;

use App\Livewire\ReportGenerator;
use App\Models\Geofence;
use App\Models\Machine;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ReportGeneratorComponentTest extends TestCase
{
    use RefreshDatabase;

    public function test_select_all_and_clear_controls_update_generator_state(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->forceFill(['current_team_id' => $team->id])->save();

        $machines = collect([
            Machine::create([
                'team_id' => $team->id,
                'name' => 'Unit A',
                'machine_type' => 'haul_truck',
                'registration_number' => 'TRK-1001',
                'serial_number' => 'SN-1001',
                'status' => 'active',
            ]),
            Machine::create([
                'team_id' => $team->id,
                'name' => 'Unit B',
                'machine_type' => 'excavator',
                'registration_number' => 'TRK-1002',
                'serial_number' => 'SN-1002',
                'status' => 'active',
            ]),
        ]);
        $geofences = Geofence::factory()->count(2)->create(['team_id' => $team->id]);

        $this->actingAs($user);

        // Livewire 3 uses strict (===) JS comparison between checkbox value attributes
        // (always strings in the DOM) and the PHP array. Select All must store string IDs
        // so Alpine marks the boxes as checked after the server round-trip.
        $machineStringIds = $machines->pluck('id')->map(fn ($id) => (string) $id)->values()->all();
        $geofenceStringIds = $geofences->pluck('id')->map(fn ($id) => (string) $id)->values()->all();

        Livewire::test(ReportGenerator::class)
            ->call('selectAllMachines')
            ->assertSet('selectedMachines', $machineStringIds)
            ->call('clearMachines')
            ->assertSet('selectedMachines', [])
            ->call('selectAllGeofences')
            ->assertSet('selectedGeofences', $geofenceStringIds)
            ->call('clearGeofences')
            ->assertSet('selectedGeofences', []);
    }

    public function test_report_generation_requires_dates(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->forceFill(['current_team_id' => $team->id])->save();

        $this->actingAs($user);

        Livewire::test(ReportGenerator::class)
            ->set('step', 3)
            ->set('reportName', 'Production Summary')
            ->set('reportType', 'production')
            ->set('startDate', '')
            ->set('endDate', '')
            ->call('generateReport')
            ->assertHasErrors(['startDate' => 'required', 'endDate' => 'required']);
    }
}
