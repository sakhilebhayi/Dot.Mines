<?php

namespace Tests\Feature;

use App\Models\Machine;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression test for the live-map component throwing
 * Livewire\Features\SupportMultipleRootElementDetection\MultipleRootElementsDetectedException
 * whenever config('app.debug') is true (as it is in this repo's .env) and the
 * requesting user has a team. Root cause: PHP's libxml2-based HTML parser
 * misparses HTML-like markup inside the component's inline <script> body
 * (Leaflet marker/popup HTML built via JS template literals), hoisting
 * fragments out as siblings of the component's root element. Fixed by
 * moving that script to resources/js/live-map.js.
 */
class LiveMapTest extends TestCase
{
    use RefreshDatabase;

    public function test_map_page_renders_for_a_user_with_a_team_when_app_debug_is_true(): void
    {
        config(['app.debug' => true]);

        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->update(['current_team_id' => $team->id]);

        Machine::factory()->create([
            'team_id' => $team->id,
            'last_location_latitude' => -28.4793,
            'last_location_longitude' => 24.6727,
        ]);

        $response = $this->actingAs($user)->get('/map');

        $response->assertOk();
    }
}
