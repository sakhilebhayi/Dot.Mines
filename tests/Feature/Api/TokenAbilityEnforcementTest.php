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
 * Personal-access-token abilities are enforced across the whole API by HTTP
 * verb (EnsureTokenAbility on the api group). Before this, abilities were
 * issued but never checked -- a token minted with only 'read' could DELETE.
 * These tests freeze the fix; the read-token-deletes case is the exact
 * exploit reproduced in the API review.
 */
class TokenAbilityEnforcementTest extends TestCase
{
    use RefreshDatabase;

    private function seedMachine(): Machine
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->update(['current_team_id' => $team->id]);
        $area = MineArea::factory()->create(['team_id' => $team->id]);

        return Machine::factory()->create([
            'team_id' => $team->id,
            'mine_area_id' => $area->id,
            'model' => 'B45E',
        ]);
    }

    private function actingUserFor(Machine $machine, array $abilities): User
    {
        /** @var User $owner */
        $owner = $machine->team->owner;
        $owner->update(['current_team_id' => $machine->team_id]);

        // Give the user full RBAC permission so the controller policies allow
        // the action -- this test isolates the TOKEN-ABILITY layer, not the
        // role layer. A denial here must come from the token ability, not
        // from a missing 'delete_machines' permission.
        TeamRoleProvisioner::assignRole($owner, $machine->team, 'admin');

        Sanctum::actingAs($owner->fresh(), $abilities);

        return $owner;
    }

    public function test_read_token_can_list_machines(): void
    {
        $machine = $this->seedMachine();
        $this->actingUserFor($machine, ['read']);

        $this->getJson('/api/machines')->assertOk();
    }

    public function test_read_token_cannot_delete_a_machine(): void
    {
        $machine = $this->seedMachine();
        $this->actingUserFor($machine, ['read']);

        $this->deleteJson("/api/machines/{$machine->id}")->assertForbidden();

        $this->assertNotNull(
            Machine::find($machine->id),
            'A read-only token must not be able to delete a machine.'
        );
    }

    public function test_read_token_cannot_create_a_machine(): void
    {
        $machine = $this->seedMachine();
        $this->actingUserFor($machine, ['read']);

        // 403 (ability), not 422 (validation): the check must precede the controller.
        $this->postJson('/api/machines', [])->assertForbidden();
    }

    public function test_read_token_cannot_bulk_delete_notifications(): void
    {
        $machine = $this->seedMachine();
        $this->actingUserFor($machine, ['read']);

        $this->deleteJson('/api/notifications')->assertForbidden();
    }

    public function test_delete_token_can_delete_a_machine(): void
    {
        $machine = $this->seedMachine();
        $this->actingUserFor($machine, ['delete']);

        $this->deleteJson("/api/machines/{$machine->id}")->assertOk();
        $this->assertNull(Machine::find($machine->id));
    }

    public function test_delete_token_without_read_cannot_list_machines(): void
    {
        $machine = $this->seedMachine();
        $this->actingUserFor($machine, ['delete']);

        $this->getJson('/api/machines')->assertForbidden();
    }

    public function test_full_ability_token_is_unrestricted(): void
    {
        $machine = $this->seedMachine();
        // Sanctum's default -- and the TransientToken the first-party console
        // and browser sync client authenticate with -- grants every ability.
        $this->actingUserFor($machine, ['*']);

        $this->getJson('/api/machines')->assertOk();
        $this->deleteJson("/api/machines/{$machine->id}")->assertOk();
    }
}
