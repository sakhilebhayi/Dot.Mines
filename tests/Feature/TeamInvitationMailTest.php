<?php

namespace Tests\Feature;

use App\Actions\Jetstream\AddTeamMember;
use App\Livewire\Settings;
use App\Mail\TeamInvitationMail;
use App\Models\Role;
use App\Models\TeamInvitation;
use App\Models\User;
use App\Services\TeamRoleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TeamInvitationMailTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<mixed>
     */
    private function makeAdminUser(): array
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam;
        TeamRoleService::provisionTeam($team, $user);

        return [$user, $team];
    }

    #[Test]
    public function inviting_a_new_user_queues_team_invitation_mail(): void
    {
        Mail::fake();
        [$admin, $team] = $this->makeAdminUser();

        Livewire::actingAs($admin)
            ->test(Settings::class)
            ->set('inviteEmail', 'newuser@example.com')
            ->set('selectedRole', 'operator')
            ->call('inviteUser')
            ->assertDispatched('notify');

        Mail::assertQueued(TeamInvitationMail::class, function (TeamInvitationMail $mail) {
            return $mail->invitation->email === 'newuser@example.com';
        });
    }

    #[Test]
    public function inviting_creates_team_invitation_record(): void
    {
        Mail::fake();
        [$admin, $team] = $this->makeAdminUser();

        Livewire::actingAs($admin)
            ->test(Settings::class)
            ->set('inviteEmail', 'invited@example.com')
            ->set('selectedRole', 'fleet_manager')
            ->call('inviteUser');

        $this->assertDatabaseHas('team_invitations', [
            'team_id' => $team->id,
            'email' => 'invited@example.com',
            'role' => 'fleet_manager',
        ]);
    }

    #[Test]
    public function inviting_already_invited_email_shows_error(): void
    {
        Mail::fake();
        [$admin, $team] = $this->makeAdminUser();

        TeamInvitation::create([
            'team_id' => $team->id,
            'email' => 'dup@example.com',
            'role' => 'operator',
        ]);

        Livewire::actingAs($admin)
            ->test(Settings::class)
            ->set('inviteEmail', 'dup@example.com')
            ->set('selectedRole', 'operator')
            ->call('inviteUser')
            ->assertDispatched('notify');

        Mail::assertNotQueued(TeamInvitationMail::class);
    }

    #[Test]
    public function inviting_existing_team_member_shows_error(): void
    {
        Mail::fake();
        [$admin, $team] = $this->makeAdminUser();
        $member = User::factory()->create();
        $team->users()->attach($member->id);

        Livewire::actingAs($admin)
            ->test(Settings::class)
            ->set('inviteEmail', $member->email)
            ->set('selectedRole', 'operator')
            ->call('inviteUser')
            ->assertDispatched('notify');

        Mail::assertNotQueued(TeamInvitationMail::class);
    }

    #[Test]
    public function accepting_invitation_assigns_rbac_role(): void
    {
        [$admin, $team] = $this->makeAdminUser();
        $invitee = User::factory()->create();

        $action = app(AddTeamMember::class);
        $action->add($admin, $team, $invitee->email, 'operator');

        $operatorRole = Role::where('team_id', $team->id)->where('name', 'operator')->first();

        $this->assertTrue(
            $invitee->roles()->where('roles.id', $operatorRole->id)->exists(),
            'Accepted invitation should assign the operator RBAC role'
        );
    }
}
