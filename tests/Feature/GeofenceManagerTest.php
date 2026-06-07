<?php

namespace Tests\Feature;

use App\Models\Geofence;
use App\Models\Role;
use App\Models\User;
use App\Services\TeamRoleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
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

    // ===================== API: Store =====================

    #[Test]
    public function admin_can_create_a_geofence(): void
    {
        $user = $this->adminUser();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/geofences', $this->validGeofencePayload())
            ->assertCreated()
            ->assertJsonPath('data.name', 'Test Pit Zone')
            ->assertJsonPath('data.type', 'pit');

        $geofence = Geofence::find($response->json('data.id'));
        $this->assertNotNull($geofence);
        $this->assertSame($user->current_team_id, $geofence->team_id);
    }

    #[Test]
    public function store_requires_name_field(): void
    {
        $user = $this->adminUser();
        $payload = $this->validGeofencePayload();
        unset($payload['name']);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/geofences', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    #[Test]
    public function store_requires_type_field(): void
    {
        $user = $this->adminUser();
        $payload = $this->validGeofencePayload();
        unset($payload['type']);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/geofences', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['type']);
    }

    #[Test]
    public function store_rejects_invalid_type_value(): void
    {
        $user = $this->adminUser();
        $payload = array_merge($this->validGeofencePayload(), ['type' => 'invalid_type']);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/geofences', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['type']);
    }

    #[Test]
    public function store_requires_coordinates_field(): void
    {
        $user = $this->adminUser();
        $payload = $this->validGeofencePayload();
        unset($payload['coordinates']);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/geofences', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['coordinates']);
    }

    #[Test]
    public function viewer_without_create_permission_cannot_create_geofence(): void
    {
        $user = $this->viewerUser();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/geofences', $this->validGeofencePayload())
            ->assertForbidden();
    }

    #[Test]
    public function unauthenticated_request_to_store_is_rejected(): void
    {
        $this->postJson('/api/v1/geofences', $this->validGeofencePayload())
            ->assertUnauthorized();
    }

    // ===================== API: Update =====================

    #[Test]
    public function admin_can_update_a_geofence(): void
    {
        $user = $this->adminUser();
        $geofence = Geofence::factory()->create(['team_id' => $user->current_team_id]);

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/v1/geofences/{$geofence->id}", ['name' => 'Updated Name'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Updated Name');

        $this->assertSame('Updated Name', $geofence->fresh()->name);
    }

    #[Test]
    public function viewer_cannot_update_a_geofence(): void
    {
        $user = $this->viewerUser();
        $geofence = Geofence::factory()->create(['team_id' => $user->current_team_id]);

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/v1/geofences/{$geofence->id}", ['name' => 'Hacked'])
            ->assertForbidden();
    }

    // ===================== API: Destroy =====================

    #[Test]
    public function admin_can_delete_a_geofence(): void
    {
        $user = $this->adminUser();
        $geofence = Geofence::factory()->create(['team_id' => $user->current_team_id]);

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/v1/geofences/{$geofence->id}")
            ->assertOk();

        $this->assertNull(Geofence::find($geofence->id));
    }

    #[Test]
    public function viewer_cannot_delete_a_geofence(): void
    {
        $user = $this->viewerUser();
        $geofence = Geofence::factory()->create(['team_id' => $user->current_team_id]);

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/v1/geofences/{$geofence->id}")
            ->assertForbidden();
    }

    // ===================== API: Index / Filters =====================

    #[Test]
    public function index_returns_only_own_team_geofences(): void
    {
        $userA = $this->adminUser();
        $userB = $this->adminUser();

        Geofence::factory()->count(2)->create(['team_id' => $userA->current_team_id, 'type' => 'pit']);
        Geofence::factory()->count(5)->create(['team_id' => $userB->current_team_id, 'type' => 'pit']);

        $response = $this->actingAs($userA, 'sanctum')
            ->getJson('/api/v1/geofences')
            ->assertOk();

        $this->assertCount(2, $response->json('data'));
    }

    #[Test]
    public function index_type_filter_returns_only_matching_geofences(): void
    {
        $user = $this->adminUser();
        Geofence::factory()->count(3)->create(['team_id' => $user->current_team_id, 'type' => 'pit']);
        Geofence::factory()->count(2)->create(['team_id' => $user->current_team_id, 'type' => 'stockpile']);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/geofences?type=pit')
            ->assertOk();

        $this->assertCount(3, $response->json('data'));

        foreach ($response->json('data') as $item) {
            $this->assertSame('pit', $item['type']);
        }
    }

    #[Test]
    public function cross_team_geofence_show_returns_404(): void
    {
        $userA = $this->adminUser();
        $userB = $this->adminUser();

        $geofenceB = Geofence::factory()->create(['team_id' => $userB->current_team_id]);

        // HasTeamFilters makes foreign records invisible → route model binding returns 404
        $this->actingAs($userA, 'sanctum')
            ->getJson("/api/v1/geofences/{$geofenceB->id}")
            ->assertNotFound();
    }

    // ===================== Helpers =====================

    /** Create a user with full admin RBAC. */
    private function adminUser(): User
    {
        $user = User::factory()->withPersonalTeam()->create();
        TeamRoleService::provisionTeam($user->currentTeam, $user);

        return $user;
    }

    /** Create a user with viewer role — has view_geofences but NOT create/update/delete. */
    private function viewerUser(): User
    {
        $user = User::factory()->withPersonalTeam()->create();
        TeamRoleService::provisionTeam($user->currentTeam, $user);

        $user->roles()->detach();
        $viewerRole = Role::where('team_id', $user->current_team_id)->where('name', 'viewer')->firstOrFail();
        $user->roles()->attach($viewerRole);

        return $user->fresh() ?? $user;
    }

    /**
     * Valid geofence creation payload.
     *
     * @return array<string, mixed>
     */
    private function validGeofencePayload(): array
    {
        $lat = -27.5;
        $lng = 27.5;

        return [
            'name' => 'Test Pit Zone',
            'type' => 'pit',
            'description' => 'A test pit area',
            'coordinates' => json_encode([
                [$lng, $lat],
                [$lng + 0.01, $lat],
                [$lng + 0.01, $lat + 0.01],
                [$lng, $lat + 0.01],
                [$lng, $lat],
            ]),
            'center_latitude' => $lat,
            'center_longitude' => $lng,
            'area_sqm' => 50000,
            'perimeter_m' => 900,
        ];
    }
}
