<?php

namespace Tests\Feature;

use App\Livewire\Dashboard;
use App\Models\Alert;
use App\Models\Team;
use App\Models\User;
use App\Services\TeamRoleProvisioner;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Acknowledging from the dashboard board goes through AlertPolicy::acknowledge
 * (own team and the acknowledge_alerts permission), exactly like the Alerts
 * page. It used to update the alert with only a team scope, so a read-only
 * viewer could acknowledge from the board.
 */
class DashboardAcknowledgeAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: Team}
     */
    private function memberWithRole(string $role): array
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);
        $member = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($member->id);
        TeamRoleProvisioner::assignRole($member, $team, $role);

        return [$member, $team];
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function rolesHoldingThePermission(): array
    {
        return [
            'admin' => ['admin'],
            'fleet manager' => ['fleet_manager'],
            'operator' => ['operator'],
        ];
    }

    #[DataProvider('rolesHoldingThePermission')]
    public function test_roles_with_the_acknowledge_permission_can_acknowledge_from_the_board(string $role): void
    {
        [$member, $team] = $this->memberWithRole($role);
        $alert = Alert::factory()->create(['team_id' => $team->id, 'status' => 'active']);

        Livewire::actingAs($member)
            ->test(Dashboard::class)
            ->call('acknowledgeAlert', $alert->id)
            ->assertOk();

        $alert->refresh();
        $this->assertSame('acknowledged', $alert->status);
        $this->assertSame($member->id, $alert->acknowledged_by);
    }

    public function test_a_viewer_is_refused(): void
    {
        [$viewer, $team] = $this->memberWithRole('viewer');
        $alert = Alert::factory()->create(['team_id' => $team->id, 'status' => 'active']);

        Livewire::actingAs($viewer)
            ->test(Dashboard::class)
            ->call('acknowledgeAlert', $alert->id)
            ->assertForbidden();

        $alert->refresh();
        $this->assertSame('active', $alert->status);
        $this->assertNull($alert->acknowledged_by);
    }

    public function test_a_member_with_no_role_is_refused(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->update(['current_team_id' => $team->id]);
        $alert = Alert::factory()->create(['team_id' => $team->id, 'status' => 'active']);

        Livewire::actingAs($user)
            ->test(Dashboard::class)
            ->call('acknowledgeAlert', $alert->id)
            ->assertForbidden();

        $this->assertSame('active', $alert->fresh()?->status);
    }

    #[DataProvider('rolesHoldingThePermission')]
    public function test_another_teams_alert_is_refused_whatever_the_role(string $role): void
    {
        [$member] = $this->memberWithRole($role);

        $otherTeam = Team::factory()->create(['user_id' => User::factory()->create()->id]);
        $foreign = Alert::factory()->create(['team_id' => $otherTeam->id, 'status' => 'active']);

        // The team scope turns another team's alert into "not found" before
        // the policy is even asked; the policy's own team check backs it up.
        try {
            Livewire::actingAs($member)
                ->test(Dashboard::class)
                ->call('acknowledgeAlert', $foreign->id);

            $this->fail('Acknowledging another team\'s alert must not succeed.');
        } catch (ModelNotFoundException $e) {
            $this->assertSame(Alert::class, $e->getModel());
        }

        $this->assertFalse($member->can('acknowledge', $foreign));

        $foreign->refresh();
        $this->assertSame('active', $foreign->status);
        $this->assertNull($foreign->acknowledged_by);
    }
}
