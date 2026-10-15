<?php

namespace Tests\Feature;

use App\Models\Machine;
use App\Models\MachineMetric;
use App\Models\Notification;
use App\Models\Operator;
use App\Models\OperatorMachineAssignment;
use App\Models\SyncTombstone;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Hybrid architecture Slice 1: the incremental sync API. Tenant isolation is
 * the load-bearing property here (brief §9) -- the server derives the team
 * from the session and nothing the client sends can widen it.
 */
class SyncApiTest extends TestCase
{
    use RefreshDatabase;

    private function syncUser(): User
    {
        return User::factory()->withPersonalTeam()->create();
    }

    public function test_returns_only_rows_changed_after_the_cursor(): void
    {
        $user = $this->syncUser();
        $machine = Machine::factory()->create(['team_id' => $user->currentTeam->id]);

        $first = $this->actingAs($user)->getJson('/api/v1/sync?since=0&scopes=fleet')->assertOk()->json();

        $this->assertCount(1, $first['changes']['fleet']);
        $this->assertSame($machine->id, $first['changes']['fleet'][0]['id']);
        $this->assertFalse($first['has_more']);
        $this->assertGreaterThan(0, $first['version']);

        // Nothing changed: the same cursor now yields an empty delta.
        $second = $this->actingAs($user)->getJson("/api/v1/sync?since={$first['version']}&scopes=fleet")->assertOk()->json();
        $this->assertCount(0, $second['changes']['fleet']);
    }

    public function test_new_telemetry_advances_the_machine_in_the_fleet_scope(): void
    {
        $user = $this->syncUser();
        $machine = Machine::factory()->create(['team_id' => $user->currentTeam->id]);

        $cursor = $this->actingAs($user)->getJson('/api/v1/sync?since=0&scopes=fleet')->json('version');

        MachineMetric::factory()->create([
            'team_id' => $user->currentTeam->id,
            'machine_id' => $machine->id,
            'latitude' => -26.2041,
            'longitude' => 28.0473,
        ]);

        $delta = $this->actingAs($user)->getJson("/api/v1/sync?since={$cursor}&scopes=fleet")->assertOk()->json();

        $this->assertCount(1, $delta['changes']['fleet']);
        $this->assertEqualsWithDelta(-26.2041, (float) $delta['changes']['fleet'][0]['latitude'], 0.0001);
        $this->assertNotNull($delta['changes']['fleet'][0]['last_seen_at']);
    }

    public function test_sync_never_leaks_another_teams_data(): void
    {
        $user = $this->syncUser();
        $stranger = $this->syncUser();

        Machine::factory()->create(['team_id' => $stranger->currentTeam->id, 'name' => 'FOREIGN-MACHINE']);
        Notification::factory()->create(['team_id' => $stranger->currentTeam->id, 'title' => 'FOREIGN-NOTE']);

        $response = $this->actingAs($user)
            ->getJson('/api/v1/sync?since=0&scopes=fleet,notifications')
            ->assertOk();

        $this->assertCount(0, $response->json('changes.fleet'));
        $this->assertCount(0, $response->json('changes.notifications'));
        $this->assertStringNotContainsString('FOREIGN', $response->getContent());
    }

    public function test_deletions_surface_as_tombstones_scoped_to_the_team(): void
    {
        $user = $this->syncUser();
        $stranger = $this->syncUser();

        $mine = Machine::factory()->create(['team_id' => $user->currentTeam->id]);
        $foreign = Machine::factory()->create(['team_id' => $stranger->currentTeam->id]);

        $mine->delete();
        $foreign->delete();

        $deleted = $this->actingAs($user)->getJson('/api/v1/sync?since=0&scopes=fleet')->assertOk()->json('deleted');

        $this->assertCount(1, $deleted);
        $this->assertSame('machines', $deleted[0]['entity_type']);
        $this->assertSame($mine->id, $deleted[0]['entity_id']);
        $this->assertSame(2, SyncTombstone::query()->count(), 'Both deletions tombstoned; only one visible to this team.');
    }

    public function test_pagination_truncates_with_a_resumable_cursor(): void
    {
        config(['sync.page_size' => 2]);
        $user = $this->syncUser();
        Machine::factory()->count(5)->create(['team_id' => $user->currentTeam->id]);

        $seen = [];
        $cursor = 0;

        for ($i = 0; $i < 4; $i++) {
            $page = $this->actingAs($user)->getJson("/api/v1/sync?since={$cursor}&scopes=fleet")->assertOk()->json();

            foreach ($page['changes']['fleet'] as $row) {
                $seen[$row['id']] = true;
            }

            $cursor = $page['version'];

            if (! $page['has_more']) {
                break;
            }
        }

        $this->assertCount(5, $seen, 'Repeated pulls drain every machine exactly once each by id.');
    }

    public function test_rejects_unknown_scopes_and_guests(): void
    {
        $this->getJson('/api/v1/sync?since=0&scopes=fleet')->assertUnauthorized();

        $user = $this->syncUser();
        $this->actingAs($user)->getJson('/api/v1/sync?since=0&scopes=billing')->assertStatus(422);
        $this->actingAs($user)->getJson('/api/v1/sync?since=0')->assertStatus(422);
    }

    public function test_operators_and_their_assignments_sync_with_the_machines_canonical_type(): void
    {
        $user = $this->syncUser();
        $teamId = $user->currentTeam->id;
        $machine = Machine::factory()->create(['team_id' => $teamId, 'machine_type' => 'adt']);
        $operator = Operator::factory()->create(['team_id' => $teamId, 'first_name' => 'Operator', 'last_name' => 'One', 'employee_number' => 'E-1', 'phone' => '0800000000']);
        $assignment = OperatorMachineAssignment::create(['team_id' => $teamId, 'operator_id' => $operator->id, 'machine_id' => $machine->id, 'shift' => 'day', 'assigned_at' => now()]);
        Operator::factory()->create(['team_id' => $user->ownedTeams()->create(['name' => 'Other', 'personal_team' => false])->id]);

        $delta = $this->actingAs($user)->getJson('/api/v1/sync?since=0&scopes=fleet,operators,operator_assignments')->assertOk()->json();

        $this->assertSame('adt', $delta['changes']['fleet'][0]['equipment_type']);
        $this->assertCount(1, $delta['changes']['operators']);
        $row = $delta['changes']['operators'][0];
        $this->assertSame(['id', 'employee_number', 'first_name', 'last_name', 'preferred_name', 'employment_status', 'default_shift', 'mine_area_id', 'sync_version'], array_keys($row));
        $this->assertSame('E-1', $row['employee_number']);
        $this->assertSame($assignment->id, $delta['changes']['operator_assignments'][0]['id']);
        $this->assertNull($delta['changes']['operator_assignments'][0]['unassigned_at']);

        $cursor = $delta['version'];
        $assignment->update(['unassigned_at' => now(), 'reason' => 'End of shift']);
        $operator->delete();

        $next = $this->actingAs($user)->getJson("/api/v1/sync?since={$cursor}&scopes=operators,operator_assignments")->assertOk()->json();
        $this->assertCount(1, $next['changes']['operator_assignments']);
        $this->assertNotNull($next['changes']['operator_assignments'][0]['unassigned_at']);
        $this->assertSame([['entity_type' => 'operators', 'entity_id' => $operator->id]], array_map(fn (array $t) => ['entity_type' => $t['entity_type'], 'entity_id' => $t['entity_id']], $next['deleted']));
    }
}
