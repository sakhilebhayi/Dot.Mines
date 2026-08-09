<?php

namespace Tests\Feature;

use App\Livewire\Settings;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Regression test: Settings::inviteUser(), removeUser(), updateUserRole(),
 * and saveGeneralSettings() never called $this->authorize(), despite
 * app/Policies/TeamPolicy.php defining addTeamMember/updateTeamMember/
 * removeTeamMember/update specifically for these actions. Any authenticated
 * member of a team -- regardless of role -- could invite new members,
 * assign arbitrary roles (including admin), remove other members, or rename
 * the team. Fixed by wiring the existing policy into each action; this test
 * proves a non-owner is now blocked and the owner still works.
 */
class SettingsTeamAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function teamWithOwnerAndMember(): array
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);
        $owner->update(['current_team_id' => $team->id]);

        $member = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($member->id);

        return [$owner, $team, $member];
    }

    public function test_non_owner_team_member_cannot_invite_a_new_member(): void
    {
        [, $team, $member] = $this->teamWithOwnerAndMember();

        Livewire::actingAs($member)
            ->test(Settings::class)
            ->set('inviteEmail', 'newperson@example.com')
            ->set('selectedRole', 'admin')
            ->call('inviteUser');

        $this->assertDatabaseMissing('users', ['email' => 'newperson@example.com']);
    }

    public function test_team_owner_can_invite_a_new_member(): void
    {
        [$owner, $team] = $this->teamWithOwnerAndMember();
        Role::factory()->create(['name' => 'operator', 'team_id' => $team->id]);

        Livewire::actingAs($owner)
            ->test(Settings::class)
            ->set('inviteEmail', 'newperson@example.com')
            ->set('selectedRole', 'operator')
            ->call('inviteUser');

        $this->assertDatabaseHas('users', ['email' => 'newperson@example.com']);
    }

    public function test_non_owner_team_member_cannot_change_another_members_role(): void
    {
        [$owner, $team, $member] = $this->teamWithOwnerAndMember();
        $adminRole = Role::factory()->create(['name' => 'admin', 'team_id' => $team->id]);
        $member->roles()->attach($adminRole->id);

        Livewire::actingAs($member)
            ->test(Settings::class)
            ->call('updateUserRole', $owner->id, 'admin');

        $this->assertFalse($owner->fresh()->roles->contains('name', 'admin'));
    }

    public function test_non_owner_team_member_cannot_remove_another_member(): void
    {
        [$owner, $team, $member] = $this->teamWithOwnerAndMember();
        $otherMember = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($otherMember->id);

        Livewire::actingAs($member)
            ->test(Settings::class)
            ->call('removeUser', $otherMember->id);

        $this->assertTrue($team->fresh()->users->contains($otherMember->id));
    }

    public function test_non_owner_team_member_cannot_rename_the_team(): void
    {
        [$owner, $team, $member] = $this->teamWithOwnerAndMember();
        $originalName = $team->name;

        Livewire::actingAs($member)
            ->test(Settings::class)
            ->set('teamName', 'Hijacked Team Name')
            ->call('saveGeneralSettings');

        $this->assertSame($originalName, $team->fresh()->name);
    }
}
