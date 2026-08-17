<?php

namespace Tests\Feature;

use App\Models\Integration;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A real HTTP render proof that the per-stream status chips and Retest
 * button added to resources/views/livewire/integration-manager.blade.php
 * actually render for a connected integration with real sync_streams data
 * -- not just that the component's PHP methods return the right array
 * shape (already covered by IntegrationConnectFlowTest).
 */
class IntegrationSyncStreamsRenderTest extends TestCase
{
    use RefreshDatabase;

    public function test_integration_row_with_sync_streams_renders_chips_without_error(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->update(['current_team_id' => $team->id]);

        Integration::factory()->forProvider('bell')->create([
            'team_id' => $team->id,
            'status' => 'connected',
            'capabilities' => ['fleet', 'telemetry', 'production'],
            'sync_streams' => [
                'fleet' => ['status' => 'active', 'last_synced_at' => now()->toIso8601String(), 'records' => 3],
                'telemetry' => ['status' => 'active', 'last_synced_at' => now()->toIso8601String(), 'records' => 3],
                'production' => ['status' => 'active', 'last_synced_at' => now()->toIso8601String(), 'records' => 3],
                'location' => ['status' => 'unavailable', 'last_synced_at' => null, 'records' => 0],
            ],
        ]);

        $response = $this->actingAs($user)->get('/integrations');

        $response->assertOk();
        $response->assertSee('Fleet: Active');
        $response->assertSee('Production: Active');
        $response->assertSee('Location: Unavailable');
        $response->assertSee('Retest');
    }
}
