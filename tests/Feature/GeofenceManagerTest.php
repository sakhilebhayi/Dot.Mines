<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GeofenceManagerTest extends TestCase
{
    use RefreshDatabase;

    public function test_geofences_page_renders_without_route_not_defined_error(): void
    {
        $user = User::factory()->withPersonalTeam()->create();

        $response = $this->actingAs($user)->get('/geofences');

        $response->assertStatus(200);
        $response->assertDontSee('Route [mine-areas.dashboard] not defined');
    }

    public function test_mine_areas_route_is_accessible(): void
    {
        $user = User::factory()->withPersonalTeam()->create();

        $response = $this->actingAs($user)->get(route('mine-areas'));

        $response->assertStatus(200);
    }
}
