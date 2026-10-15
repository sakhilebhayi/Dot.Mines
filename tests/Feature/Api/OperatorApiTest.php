<?php

namespace Tests\Feature\Api;

use App\Models\Operator;
use App\Models\Team;
use App\Models\User;
use App\Services\TeamRoleProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The operator register over the API: team-scoped, permission-gated, and
 * free of the personal data the application keeps behind its own gates.
 */
class OperatorApiTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->update(['current_team_id' => $team->id]);
        TeamRoleProvisioner::assignRole($user, $team, $role);

        return $user->fresh();
    }

    public function test_lists_the_teams_operators_without_contact_or_medical_fields(): void
    {
        $user = $this->userWithRole('admin');
        $teamId = (int) $user->current_team_id;
        Operator::factory()->create(['team_id' => $teamId, 'first_name' => 'Operator', 'last_name' => 'One', 'employee_number' => 'E-1', 'phone' => '0800000000']);
        Operator::factory()->create(['team_id' => $teamId, 'first_name' => 'Operator', 'last_name' => 'Two', 'employment_status' => Operator::STATUS_INACTIVE]);
        Operator::factory()->create(['team_id' => Team::factory()->create()->id, 'last_name' => 'Elsewhere']);

        $response = $this->actingAs($user)->getJson('/api/v1/operators')->assertOk();

        $response->assertJsonStructure(['data', 'links', 'meta' => ['total']]);
        $this->assertSame(2, $response->json('meta.total'));
        $row = $response->json('data.0');
        $this->assertSame('Operator One', $row['name']);
        $this->assertSame('E-1', $row['employee_number']);
        foreach (['phone', 'email', 'emergency_contact_name', 'emergency_contact_phone', 'photo_path', 'notes', 'team_id', 'user_id'] as $hidden) {
            $this->assertArrayNotHasKey($hidden, $row, "`{$hidden}` must not be exposed by the operator resource.");
        }

        $active = $this->actingAs($user)->getJson('/api/v1/operators?employment_status=active')->assertOk()->json('data');
        $this->assertCount(1, $active);

        $found = $this->actingAs($user)->getJson('/api/v1/operators?search=E-1')->assertOk()->json('data');
        $this->assertCount(1, $found);
    }

    public function test_show_is_team_scoped_and_the_register_needs_the_view_permission(): void
    {
        $admin = $this->userWithRole('admin');
        $mine = Operator::factory()->create(['team_id' => $admin->current_team_id]);
        $theirs = Operator::factory()->create(['team_id' => Team::factory()->create()->id]);

        $this->actingAs($admin)->getJson('/api/v1/operators/'.$mine->id)->assertOk()->assertJsonPath('data.id', $mine->id);
        $this->actingAs($admin)->getJson('/api/v1/operators/'.$theirs->id)->assertNotFound();

        $viewer = $this->userWithRole('viewer');
        $this->actingAs($viewer)->getJson('/api/v1/operators')->assertForbidden();
    }
}
