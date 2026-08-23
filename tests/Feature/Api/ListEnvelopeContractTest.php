<?php

namespace Tests\Feature\Api;

use App\Models\Machine;
use App\Models\MineArea;
use App\Models\Team;
use App\Models\User;
use App\Services\TeamRoleProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Every list endpoint returns ONE envelope.
 *
 * The API previously shipped three shapes -- `{data, pagination}`, the raw
 * Laravel paginator (`current_page`, `first_page_url`, `links`, ...), and
 * `{data, meta}` -- so no integrator could write a single response handler.
 * All paginated lists now go through App\Support\ApiResponse::paginated()
 * and emit `{data, links, meta}` (Laravel's Resource Collection shape).
 *
 * This test is the ratchet: add a list endpoint, add it here. A new
 * hand-built envelope fails immediately rather than drifting unnoticed --
 * which is exactly how the three shapes accumulated.
 */
class ListEnvelopeContractTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Every paginated list endpoint in the API.
     *
     * @return array<string, array{0: string}>
     */
    public static function paginatedListEndpoints(): array
    {
        return [
            'machines' => ['/api/machines'],
            'alerts' => ['/api/alerts'],
            'geofences' => ['/api/geofences'],
            'reports' => ['/api/reports'],
            'notifications' => ['/api/notifications'],
            'notifications unread' => ['/api/notifications/unread'],
            'fuel tanks' => ['/api/fuel/tanks'],
            'fuel transactions' => ['/api/fuel/transactions'],
            'maintenance records' => ['/api/maintenance/records'],
            'maintenance schedules' => ['/api/maintenance/schedules'],
            'machine health' => ['/api/maintenance/health'],
            'assignments available' => ['/api/assignments/available'],
            'webhooks' => ['/api/webhooks'],
        ];
    }

    private function actingUser(): User
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->update(['current_team_id' => $team->id]);
        TeamRoleProvisioner::assignRole($user, $team, 'admin');

        $area = MineArea::factory()->create(['team_id' => $team->id]);
        Machine::factory()->create([
            'team_id' => $team->id,
            'mine_area_id' => $area->id,
            'model' => 'B45E',
        ]);

        Sanctum::actingAs($user->fresh(), ['*']);

        return $user;
    }

    /**
     * @dataProvider paginatedListEndpoints
     */
    public function test_paginated_list_endpoint_uses_the_standard_envelope(string $endpoint): void
    {
        $this->actingUser();

        $response = $this->getJson($endpoint);

        $response->assertOk();
        $response->assertJsonStructure([
            'data',
            'links' => ['first', 'last', 'prev', 'next'],
            'meta' => ['current_page', 'from', 'to', 'per_page', 'last_page', 'total'],
        ]);

        // The legacy shapes must be gone: no `pagination` key, and no raw
        // paginator fields leaking at the top level.
        $body = $response->json();
        $this->assertArrayNotHasKey('pagination', $body, "{$endpoint} still uses the old {data, pagination} envelope.");
        $this->assertArrayNotHasKey('current_page', $body, "{$endpoint} still returns the raw Laravel paginator.");
        $this->assertArrayNotHasKey('first_page_url', $body, "{$endpoint} still returns the raw Laravel paginator.");
        $this->assertIsArray($body['data'], "{$endpoint} must return a list under `data`.");
    }

    public function test_non_paginated_list_endpoints_share_the_data_and_meta_shape(): void
    {
        $user = $this->actingUser();
        $machine = Machine::where('team_id', $user->current_team_id)->firstOrFail();

        foreach ([
            "/api/alerts/machine/{$machine->id}",
            "/api/assignments/machines/{$machine->id}/history",
        ] as $endpoint) {
            $response = $this->getJson($endpoint);

            $response->assertOk();
            $response->assertJsonStructure(['data', 'meta' => ['total']]);
            $this->assertIsArray($response->json('data'), "{$endpoint} must return a list under `data`.");
        }
    }

    public function test_pagination_metadata_reports_real_numbers(): void
    {
        $user = $this->actingUser();
        $team = $user->currentTeam;
        $area = MineArea::factory()->create(['team_id' => $team->id]);
        Machine::factory()->count(4)->create([
            'team_id' => $team->id,
            'mine_area_id' => $area->id,
            'model' => 'B45E',
        ]);

        // 5 machines total (1 from actingUser + 4), 2 per page.
        $response = $this->getJson('/api/machines?per_page=2');

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
        $this->assertSame(5, $response->json('meta.total'));
        $this->assertSame(2, $response->json('meta.per_page'));
        $this->assertSame(1, $response->json('meta.current_page'));
        $this->assertSame(3, $response->json('meta.last_page'));
        $this->assertNull($response->json('links.prev'), 'Page 1 has no previous link.');
        $this->assertNotNull($response->json('links.next'), 'Page 1 of 3 must offer a next link.');
    }
}
