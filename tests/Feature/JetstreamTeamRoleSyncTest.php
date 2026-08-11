<?php

namespace Tests\Feature;

use App\Actions\Jetstream\AddTeamMember;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Jetstream\Actions\UpdateTeamMemberRole;
use Tests\TestCase;

/**
 * Regression test: teams.show (Jetstream's native team page) manages
 * membership through its own team_user pivot role and never touched the
 * app's custom roles/permissions tables that every $this->authorize()
 * policy check in this app is built on. A member added, invited, or
 * role-changed through that page was silently denied every gated action
 * even though the page showed them with a real role. Fixed by registering
 * the same four role keys (admin/fleet_manager/operator/viewer) with
 * Jetstream that TeamRoleProvisioner already uses, and syncing
 * TeamMemberAdded/TeamMemberUpdated into TeamRoleProvisioner on every
 * change.
 */
class JetstreamTeamRoleSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_added_via_jetstream_gets_a_working_custom_role(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);
        $member = User::factory()->create();

        app(AddTeamMember::class)->add($owner, $team, $member->email, 'admin');

        $member->refresh();
        $member->update(['current_team_id' => $team->id]);
        $this->assertTrue($member->hasRole('admin'));
        $this->assertTrue($member->hasPermission('view_alerts'));
    }

    public function test_role_updated_via_jetstream_resyncs_the_custom_role(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);
        $member = User::factory()->create();

        app(AddTeamMember::class)->add($owner, $team, $member->email, 'fleet_manager');
        $member->refresh();
        $member->update(['current_team_id' => $team->id]);
        $this->assertTrue($member->hasRole('fleet_manager'));

        app(UpdateTeamMemberRole::class)->update($owner, $team, $member->id, 'admin');

        $member->refresh();
        $this->assertTrue($member->hasRole('admin'));
        $this->assertFalse($member->hasRole('fleet_manager'));
    }
}
