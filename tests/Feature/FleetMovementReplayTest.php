<?php

namespace Tests\Feature;

use App\Livewire\FleetMovementReplay;
use App\Models\Machine;
use App\Models\MachineMetric;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Fleet Movement Replay was unusable: $selectedMachine was typed ?Machine
 * while the select's wire:model bound the string machine id into it -- a
 * TypeError on the very first selection. The replay also fabricated an
 * "Auto-calculated Route" via an external OSRM call inside render() when no
 * saved routes existed, and filtered/ordered history by sync time
 * (created_at) instead of the provider's reading time (recorded_at).
 */
class FleetMovementReplayTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{Team, Machine, User}
     */
    private function teamWithMachine(): array
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->update(['current_team_id' => $team->id]);
        $machine = Machine::factory()->create(['team_id' => $team->id, 'status' => 'active']);

        return [$team, $machine, $user];
    }

    public function test_selecting_a_machine_loads_its_real_gps_history_without_crashing(): void
    {
        [$team, $machine, $user] = $this->teamWithMachine();

        foreach ([[26.10, 28.05, 3], [26.11, 28.06, 2], [26.12, 28.07, 1]] as [$lat, $lng, $hoursAgo]) {
            MachineMetric::factory()->create([
                'team_id' => $team->id,
                'machine_id' => $machine->id,
                'latitude' => -$lat,
                'longitude' => $lng,
                'recorded_at' => now()->subHours($hoursAgo),
            ]);
        }

        // The select submits a STRING id, exactly as wire:model does.
        $component = Livewire::actingAs($user)
            ->test(FleetMovementReplay::class)
            ->set('selectedMachine', (string) $machine->id)
            ->assertStatus(200);

        $path = $component->viewData('pathCoordinates');
        $this->assertCount(3, $path);
        // Ordered by the provider's reading time, oldest first.
        $this->assertEqualsWithDelta(-26.10, $path[0]['lat'], 0.001);
        $this->assertEqualsWithDelta(-26.12, $path[2]['lat'], 0.001);
    }

    public function test_replay_never_fabricates_a_route_when_none_are_saved(): void
    {
        [$team, $machine, $user] = $this->teamWithMachine();

        MachineMetric::factory()->create([
            'team_id' => $team->id,
            'machine_id' => $machine->id,
            'latitude' => -26.10,
            'longitude' => 28.05,
            'recorded_at' => now()->subHours(2),
        ]);
        MachineMetric::factory()->create([
            'team_id' => $team->id,
            'machine_id' => $machine->id,
            'latitude' => -26.20,
            'longitude' => 28.15,
            'recorded_at' => now()->subHour(),
        ]);

        $routes = Livewire::actingAs($user)
            ->test(FleetMovementReplay::class)
            ->set('selectedMachine', (string) $machine->id)
            ->viewData('routes');

        // No saved routes => no routes drawn. Previously an external OSRM
        // call synthesized an "Auto-calculated Route" the machine never drove.
        $this->assertSame([], $routes);
    }

    public function test_machines_outside_the_team_are_not_selectable_data_sources(): void
    {
        [, , $user] = $this->teamWithMachine();

        $otherTeam = Team::factory()->create();
        $foreign = Machine::factory()->create(['team_id' => $otherTeam->id]);
        MachineMetric::factory()->create([
            'team_id' => $otherTeam->id,
            'machine_id' => $foreign->id,
            'latitude' => -26.5,
            'longitude' => 28.5,
            'recorded_at' => now()->subHour(),
        ]);

        $component = Livewire::actingAs($user)
            ->test(FleetMovementReplay::class)
            ->set('selectedMachine', (string) $foreign->id);

        // A foreign id yields no machine details AND no GPS history -- the
        // metrics query used to run unscoped on the raw machine_id, which
        // would have replayed another team's movements.
        $this->assertNull($component->viewData('selectedMachineDetails'));
        $this->assertSame([], $component->viewData('pathCoordinates'));
    }
}
