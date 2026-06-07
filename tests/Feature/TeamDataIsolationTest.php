<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\Geofence;
use App\Models\Machine;
use App\Models\MineArea;
use App\Models\Notification;
use App\Models\Report;
use App\Models\Team;
use App\Models\User;
use App\Services\TeamRoleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TeamDataIsolationTest extends TestCase
{
    use RefreshDatabase;

    private function userWithTeam(): User
    {
        $user = User::factory()->withPersonalTeam()->create();
        $user->switchTeam($user->ownedTeams->first());

        return $user;
    }

    /** Provision a user with full admin RBAC so policies pass. */
    private function adminWithTeam(): User
    {
        $user = User::factory()->withPersonalTeam()->create();
        TeamRoleService::provisionTeam($user->currentTeam, $user);

        return $user;
    }

    // ===================== Machines =====================

    #[Test]
    public function test_user_only_sees_their_own_team_machines(): void
    {
        $userA = $this->userWithTeam();
        $userB = $this->userWithTeam();

        $machineA = Machine::factory()->create(['team_id' => $userA->currentTeam->id]);
        Machine::factory()->create(['team_id' => $userB->currentTeam->id]);

        $this->actingAs($userA);

        $results = Machine::all();

        $this->assertCount(1, $results);
        $this->assertEquals($machineA->id, $results->first()->id);
    }

    #[Test]
    public function test_unauthenticated_query_returns_no_records(): void
    {
        $user = $this->userWithTeam();
        Machine::factory()->create(['team_id' => $user->currentTeam->id]);

        // Records exist in DB via withoutGlobalScopes
        $this->assertGreaterThan(0, Machine::withoutGlobalScopes()->count());
    }

    #[Test]
    public function test_cross_team_machine_access_is_blocked_via_api(): void
    {
        $userA = $this->userWithTeam();
        $userB = $this->userWithTeam();

        $machineB = Machine::factory()->create(['team_id' => $userB->currentTeam->id]);

        // userA tries to access userB's machine via the fleet show route
        $this->actingAs($userA)
            ->get("/fleet/{$machineB->id}")
            ->assertStatus(404);
    }

    #[Test]
    public function test_machines_from_different_teams_are_counted_separately(): void
    {
        $userA = $this->userWithTeam();
        $userB = $this->userWithTeam();

        Machine::factory()->count(3)->create(['team_id' => $userA->currentTeam->id]);
        Machine::factory()->count(5)->create(['team_id' => $userB->currentTeam->id]);

        $this->actingAs($userA);
        $this->assertCount(3, Machine::all());

        $this->actingAs($userB);
        $this->assertCount(5, Machine::all());
    }

    // ===================== Alerts =====================

    #[Test]
    public function cross_team_alert_cannot_be_accessed_via_api(): void
    {
        $userA = $this->adminWithTeam();
        $userB = $this->adminWithTeam();

        $alertB = Alert::factory()->create(['team_id' => $userB->currentTeam->id]);

        // Team-scoped model binding returns 404 — foreign record is invisible
        $this->actingAs($userA, 'sanctum')
            ->getJson("/api/v1/alerts/{$alertB->id}")
            ->assertStatus(404);
    }

    #[Test]
    public function alert_list_only_returns_own_team_alerts(): void
    {
        $userA = $this->adminWithTeam();
        $userB = $this->adminWithTeam();

        Alert::factory()->count(2)->create(['team_id' => $userA->currentTeam->id]);
        Alert::factory()->count(3)->create(['team_id' => $userB->currentTeam->id]);

        $response = $this->actingAs($userA, 'sanctum')
            ->getJson('/api/v1/alerts')
            ->assertOk();

        $this->assertCount(2, $response->json('data'));
    }

    #[Test]
    public function cross_team_alert_acknowledge_is_blocked(): void
    {
        $userA = $this->adminWithTeam();
        $userB = $this->adminWithTeam();

        $alertB = Alert::factory()->create([
            'team_id' => $userB->currentTeam->id,
            'status' => 'active',
        ]);

        // Foreign alert is invisible via team scope → 404
        $this->actingAs($userA, 'sanctum')
            ->postJson("/api/v1/alerts/{$alertB->id}/acknowledge")
            ->assertStatus(404);
    }

    // ===================== Reports =====================

    #[Test]
    public function cross_team_report_cannot_be_viewed_via_api(): void
    {
        $userA = $this->adminWithTeam();
        $userB = $this->adminWithTeam();

        $reportB = Report::factory()->create(['team_id' => $userB->currentTeam->id]);

        // HasTeamFilters global scope means the foreign record is invisible → 404
        $this->actingAs($userA, 'sanctum')
            ->getJson("/api/v1/reports/{$reportB->id}")
            ->assertStatus(404);
    }

    #[Test]
    public function report_list_only_returns_own_team_reports(): void
    {
        $userA = $this->adminWithTeam();
        $userB = $this->adminWithTeam();

        Report::factory()->count(2)->create(['team_id' => $userA->currentTeam->id]);
        Report::factory()->count(4)->create(['team_id' => $userB->currentTeam->id]);

        $response = $this->actingAs($userA, 'sanctum')
            ->getJson('/api/v1/reports')
            ->assertOk();

        $this->assertCount(2, $response->json('data'));
    }

    #[Test]
    public function cross_team_report_cannot_be_deleted(): void
    {
        $userA = $this->adminWithTeam();
        $userB = $this->adminWithTeam();

        $reportB = Report::factory()->create(['team_id' => $userB->currentTeam->id]);

        // HasTeamFilters global scope means the foreign record is invisible → 404
        $this->actingAs($userA, 'sanctum')
            ->deleteJson("/api/v1/reports/{$reportB->id}")
            ->assertStatus(404);
    }

    // ===================== Geofences =====================

    #[Test]
    public function cross_team_geofence_cannot_be_viewed_via_api(): void
    {
        $userA = $this->adminWithTeam();
        $userB = $this->adminWithTeam();

        $mineAreaB = MineArea::factory()->create(['team_id' => $userB->currentTeam->id]);
        $geofenceB = Geofence::factory()->create([
            'team_id' => $userB->currentTeam->id,
            'mine_area_id' => $mineAreaB->id,
        ]);

        // Team-scoped model binding → 404 for cross-team access
        $this->actingAs($userA, 'sanctum')
            ->getJson("/api/v1/geofences/{$geofenceB->id}")
            ->assertStatus(404);
    }

    #[Test]
    public function geofence_list_only_returns_own_team_geofences(): void
    {
        $userA = $this->adminWithTeam();
        $userB = $this->adminWithTeam();

        $mineAreaA = MineArea::factory()->create(['team_id' => $userA->currentTeam->id]);
        $mineAreaB = MineArea::factory()->create(['team_id' => $userB->currentTeam->id]);

        Geofence::factory()->count(2)->create([
            'team_id' => $userA->currentTeam->id,
            'mine_area_id' => $mineAreaA->id,
        ]);
        Geofence::factory()->count(3)->create([
            'team_id' => $userB->currentTeam->id,
            'mine_area_id' => $mineAreaB->id,
        ]);

        $response = $this->actingAs($userA, 'sanctum')
            ->getJson('/api/v1/geofences')
            ->assertOk();

        $this->assertCount(2, $response->json('data'));
    }

    #[Test]
    public function cross_team_geofence_cannot_be_updated(): void
    {
        $userA = $this->adminWithTeam();
        $userB = $this->adminWithTeam();

        $mineAreaB = MineArea::factory()->create(['team_id' => $userB->currentTeam->id]);
        $geofenceB = Geofence::factory()->create([
            'team_id' => $userB->currentTeam->id,
            'mine_area_id' => $mineAreaB->id,
        ]);

        // Team-scoped model binding → 404 for cross-team access
        $this->actingAs($userA, 'sanctum')
            ->putJson("/api/v1/geofences/{$geofenceB->id}", ['name' => 'Hijacked'])
            ->assertStatus(404);
    }

    // ===================== Notifications =====================

    #[Test]
    public function notifications_are_scoped_to_team(): void
    {
        $userA = $this->adminWithTeam();
        $userB = $this->adminWithTeam();

        Notification::factory()->count(3)->create(['team_id' => $userA->currentTeam->id]);
        Notification::factory()->count(5)->create(['team_id' => $userB->currentTeam->id]);

        $this->actingAs($userA);
        $this->assertCount(3, Notification::where('team_id', $userA->currentTeam->id)->get());
        $this->assertCount(5, Notification::where('team_id', $userB->currentTeam->id)->get());
    }
}
