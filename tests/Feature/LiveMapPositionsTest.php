<?php

namespace Tests\Feature;

use App\Livewire\LiveMap;
use App\Models\Integration;
use App\Models\Machine;
use App\Models\Team;
use App\Models\User;
use App\Services\Integration\IntegrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Slice 5 of the live-operations UX program: the live map moves markers in
 * place from real GPS data on a poll cadence -- no map reloads, no
 * fabricated movement, and position ages that reflect when the machine
 * actually reported (never the sync moment).
 */
class LiveMapPositionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_refresh_positions_dispatches_current_machine_positions_to_the_map(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $machine = Machine::factory()->create([
            'team_id' => $user->currentTeam->id,
            'last_location_latitude' => -26.02,
            'last_location_longitude' => 28.93,
        ]);

        Livewire::actingAs($user)
            ->test(LiveMap::class)
            ->call('refreshPositions')
            ->assertDispatched('machines-positions-updated');
    }

    public function test_refresh_positions_stays_silent_when_machines_layer_is_off(): void
    {
        $user = User::factory()->withPersonalTeam()->create();

        Livewire::actingAs($user)
            ->test(LiveMap::class)
            ->call('toggleMachines')
            ->call('refreshPositions')
            ->assertNotDispatched('machines-positions-updated');
    }

    public function test_map_page_polls_positions_only_while_visible(): void
    {
        $user = User::factory()->withPersonalTeam()->create();

        $response = $this->actingAs($user)->get('/map');

        $response->assertOk();
        $response->assertSee('wire:poll.visible.60s="refreshPositions"', false);
    }

    public function test_synced_position_timestamp_is_the_providers_reading_time_not_the_sync_moment(): void
    {
        $team = Team::factory()->create();
        $integration = Integration::factory()->forProvider('bell')->create(['team_id' => $team->id]);

        $machine = app(IntegrationService::class)->syncMachine($integration, [
            'external_id' => 'ASA B50E#0001',
            'model' => 'B50E',
            'status' => 'active',
            'last_location' => [
                'latitude' => -26.02,
                'longitude' => 28.93,
                'timestamp' => '2026-08-21T14:05:00Z',
            ],
            'metrics' => [],
            'alerts' => [],
        ]);

        $this->assertNotNull($machine);
        $this->assertSame('2026-08-21 14:05:00', $machine->last_location_update->utc()->format('Y-m-d H:i:s'));
    }

    public function test_sync_without_a_position_keeps_the_old_position_timestamp(): void
    {
        $team = Team::factory()->create();
        $integration = Integration::factory()->forProvider('bell')->create(['team_id' => $team->id]);
        $machine = Machine::factory()->create([
            'team_id' => $team->id,
            'manufacturer' => 'bell',
            'manufacturer_id' => 'ASA B50E#0002',
            'last_location_latitude' => -26.0,
            'last_location_longitude' => 28.9,
            'last_location_update' => '2026-08-20 08:00:00',
        ]);

        app(IntegrationService::class)->syncMachine($integration, [
            'external_id' => 'ASA B50E#0002',
            'status' => 'idle',
            'last_location' => null,
            'metrics' => [],
            'alerts' => [],
        ]);

        // Stamping now() here made a machine that stopped reporting look
        // perpetually fresh -- the exact dishonesty the brief forbids.
        $this->assertSame('2026-08-20 08:00:00', $machine->fresh()->last_location_update->format('Y-m-d H:i:s'));
    }
}
