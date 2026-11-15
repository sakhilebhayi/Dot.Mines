<?php

namespace Tests\Feature;

use App\Livewire\Alerts;
use App\Livewire\Fleet;
use App\Livewire\HeaderSearch;
use App\Livewire\ProductionDashboard;
use App\Models\Alert;
use App\Models\Geofence;
use App\Models\Machine;
use App\Models\MaintenanceRecord;
use App\Models\MineArea;
use App\Models\Operator;
use App\Models\Permission;
use App\Models\ProductionRecord;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use App\Services\TeamRoleProvisioner;
use App\Support\Search\Highlight;
use App\Support\Search\MinesSearch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * R-52: the header search. A real search over machines, alerts, maintenance, operators and zones, scoped to the signed-in
 * person's team with an explicit team_id (not the models' global scope) and gated by the permissions the pages and policies ask for.
 */
class MinesSearchTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: User, 1: Team} */
    private function member(string $role, string $teamName = 'Kopano Colliery'): array
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id, 'name' => $teamName]);
        $user = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($user->id);
        TeamRoleProvisioner::assignRole($user, $team, $role);

        return [$user, $team];
    }

    private function fixtures(Team $team, string $marker): void
    {
        $machine = Machine::factory()->create(['team_id' => $team->id, 'name' => "{$marker} Haul Truck", 'machine_type' => 'adt', 'manufacturer' => 'Bell', 'serial_number' => "SN-{$marker}", 'registration_number' => "RG-{$marker}"]);
        Alert::factory()->create(['team_id' => $team->id, 'machine_id' => $machine->id, 'title' => "{$marker} engine over temperature", 'priority' => 'critical']);
        MaintenanceRecord::factory()->create(['team_id' => $team->id, 'machine_id' => $machine->id, 'title' => "{$marker} hydraulic service", 'work_order_number' => "WO-{$marker}"]);
        Operator::factory()->create(['team_id' => $team->id, 'first_name' => $marker, 'last_name' => 'Dlamini', 'employee_number' => "EMP-{$marker}"]);
        MineArea::factory()->create(['team_id' => $team->id, 'name' => "{$marker} North Pit"]);
        Geofence::factory()->create(['team_id' => $team->id, 'name' => "{$marker} Dump Zone"]);
    }

    public function test_a_manager_finds_every_kind_of_record_in_their_own_team(): void
    {
        [$manager, $team] = $this->member('fleet_manager');
        $this->fixtures($team, 'Zulu');
        $this->actingAs($manager);

        $groups = app(MinesSearch::class)->search($manager, 'zulu', 5);

        $this->assertEqualsCanonicalizing(['Machines', 'Alerts', 'Maintenance', 'Operators', 'Zones'], array_keys($groups));
        $this->assertSame('Zulu Haul Truck', $groups['Machines'][0]->title);
        $this->assertSame(route('fleet.show', Machine::where('name', 'Zulu Haul Truck')->value('id')), $groups['Machines'][0]->url);
        $this->assertSame('WO-Zulu', $groups['Maintenance'][0]->ref);
        $this->assertSame('EMP-Zulu', $groups['Operators'][0]->ref);
        $this->assertCount(2, $groups['Zones']);
    }

    public function test_another_teams_records_are_never_returned(): void
    {
        [$mine, $myTeam] = $this->member('fleet_manager', 'Mine A');
        [, $theirTeam] = $this->member('fleet_manager', 'Mine B');
        $this->fixtures($myTeam, 'Alpha');
        $this->fixtures($theirTeam, 'Bravo');
        $this->actingAs($mine);

        $this->assertSame([], app(MinesSearch::class)->search($mine, 'bravo', 10));
        $this->assertArrayHasKey('Machines', app(MinesSearch::class)->search($mine, 'alpha', 10));

        $page = $this->get('/search?q=bravo')->assertOk();
        $page->assertDontSee('Bravo Haul Truck')->assertDontSee('EMP-Bravo')->assertSee('Nothing matches');
    }

    public function test_the_other_teams_machine_name_does_not_leak_through_the_alert_or_maintenance_join(): void
    {
        [$mine, $myTeam] = $this->member('fleet_manager', 'Mine A');
        [, $theirTeam] = $this->member('fleet_manager', 'Mine B');
        $theirs = Machine::factory()->create(['team_id' => $theirTeam->id, 'name' => 'Secret Dozer']);
        Alert::factory()->create(['team_id' => $theirTeam->id, 'machine_id' => $theirs->id, 'title' => 'Hot brakes']);
        // an alert of MY team that names nothing of theirs
        Alert::factory()->create(['team_id' => $myTeam->id, 'machine_id' => Machine::factory()->create(['team_id' => $myTeam->id, 'name' => 'Own Loader'])->id, 'title' => 'Low oil']);
        $this->actingAs($mine);

        $this->assertSame([], app(MinesSearch::class)->search($mine, 'secret', 10));
        $this->assertSame([], app(MinesSearch::class)->search($mine, 'hot brakes', 10));
        $this->assertArrayHasKey('Alerts', app(MinesSearch::class)->search($mine, 'own loader', 10));
    }

    public function test_a_person_with_no_current_team_gets_nothing_not_everything(): void
    {
        [, $team] = $this->member('fleet_manager');
        $this->fixtures($team, 'Zulu');
        $nobody = User::factory()->create(['current_team_id' => null]);
        // The models' global team scope switches itself off for a request with no team; the search must not.
        $this->actingAs($nobody);

        $this->assertSame([], app(MinesSearch::class)->search($nobody, 'zulu', 10));
    }

    public function test_a_viewer_sees_what_a_viewer_sees_and_no_operator_register(): void
    {
        [$viewer, $team] = $this->member('viewer');
        $this->fixtures($team, 'Zulu');
        $this->actingAs($viewer);

        $groups = app(MinesSearch::class)->search($viewer, 'zulu', 5);

        $this->assertArrayHasKey('Machines', $groups);
        $this->assertArrayHasKey('Alerts', $groups);
        $this->assertArrayHasKey('Zones', $groups);
        $this->assertArrayNotHasKey('Operators', $groups, 'a viewer cannot open /operators, so the register is not searched for them');
        $this->get('/search?q=zulu')->assertOk()->assertDontSee('EMP-Zulu');
    }

    public function test_a_role_without_machine_access_does_not_get_machine_or_maintenance_rows(): void
    {
        [$user, $team] = $this->member('viewer');
        $this->fixtures($team, 'Zulu');
        // take view_machines away from the viewer role of this team
        $role = Role::where('team_id', $team->id)->where('name', 'viewer')->firstOrFail();
        $role->permissions()->detach(Permission::where('name', 'view_machines')->pluck('id')->all());
        $this->actingAs($user->fresh());

        $groups = app(MinesSearch::class)->search($user->fresh(), 'zulu', 5);

        $this->assertArrayNotHasKey('Machines', $groups);
        $this->assertArrayNotHasKey('Maintenance', $groups);
    }

    public function test_go_to_pages_are_only_those_the_role_may_open(): void
    {
        [$viewer] = $this->member('viewer');
        [$manager] = $this->member('fleet_manager', 'Other');
        $titles = fn (User $u): array => array_map(fn ($h) => $h->title, app(MinesSearch::class)->pages($u, null, 30));

        $this->assertNotContains('Operators', $titles($viewer));
        $this->assertContains('Operators', $titles($manager));
        $this->assertContains('Fleet', $titles($viewer));
        $this->assertContains('Alerts', $titles($viewer));
    }

    public function test_every_word_must_match_and_a_wildcard_is_literal(): void
    {
        [$manager, $team] = $this->member('fleet_manager');
        $this->fixtures($team, 'Zulu');
        Machine::factory()->create(['team_id' => $team->id, 'name' => 'Plain Loader', 'machine_type' => 'dozer']);
        $this->actingAs($manager);
        $search = app(MinesSearch::class);

        $this->assertArrayHasKey('Machines', $search->search($manager, 'haul zulu', 5));
        $this->assertArrayNotHasKey('Machines', $search->search($manager, 'haul loader', 5));
        $this->assertSame([], $search->search($manager, '%%', 5), 'a bare wildcard is not a match-everything');
        $this->assertSame([], $search->search($manager, '___', 5));
        $this->assertSame([], $search->search($manager, 'z', 5), 'one character is not a search');
        $this->assertSame(['zulu', 'haul'], MinesSearch::words("  ZULU  %haul_ \x00"));
    }

    public function test_deleted_operators_and_areas_are_not_found(): void
    {
        [$manager, $team] = $this->member('fleet_manager');
        $operator = Operator::factory()->create(['team_id' => $team->id, 'first_name' => 'Gone', 'last_name' => 'Person']);
        $area = MineArea::factory()->create(['team_id' => $team->id, 'name' => 'Gone Pit']);
        $operator->delete();
        $area->delete();
        $this->actingAs($manager);

        $this->assertSame([], app(MinesSearch::class)->search($manager, 'gone', 5));
    }

    public function test_the_query_is_parameterised(): void
    {
        [$manager, $team] = $this->member('fleet_manager');
        Machine::factory()->create(['team_id' => $team->id, 'name' => 'Safe Truck']);
        $this->actingAs($manager);

        $this->assertSame([], app(MinesSearch::class)->search($manager, "x' OR '1'='1", 5));
        $this->assertSame([], app(MinesSearch::class)->search($manager, 'machines; drop table users', 5));
        $this->assertSame(1, User::query()->where('id', $manager->id)->count());
    }

    public function test_the_results_page_needs_a_sign_in_and_shows_states(): void
    {
        $this->get('/search?q=zulu')->assertRedirect();

        [$manager, $team] = $this->member('fleet_manager');
        $this->fixtures($team, 'Zulu');
        $this->actingAs($manager);

        $this->get('/search')->assertOk()->assertSee('Go to')->assertSee('Type at least 2 characters');
        $this->get('/search?q=zulu')->assertOk()->assertSee('Machines')->assertSee('Zulu Haul Truck', false)->assertSee('<mark>Zulu</mark>', false);
        $this->get('/search?q=qqqqzz')->assertOk()->assertSee('Nothing matches');
    }

    public function test_a_record_name_is_escaped_in_the_highlight(): void
    {
        $html = (string) Highlight::mark('<script>alert(1)</script> Zulu', ['zulu']);

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('<mark>Zulu</mark>', $html);
    }

    public function test_the_header_dropdown_component_shows_go_to_then_rows_then_no_match(): void
    {
        [$manager, $team] = $this->member('fleet_manager');
        $this->fixtures($team, 'Zulu');
        [, $other] = $this->member('fleet_manager', 'Other mine');
        $this->fixtures($other, 'Xray');
        $this->actingAs($manager);

        Livewire::test(HeaderSearch::class)
            ->assertSee('Go to')->assertSee('Operators')
            ->set('q', 'zulu')
            ->assertSee('Zulu')->assertSee('See every result for zulu')->assertDontSee('Xray')
            ->set('q', 'nothingmatchesthis')
            ->assertSee('Nothing matches')
            ->set('q', str_repeat('a', 300))
            ->assertSet('q', str_repeat('a', MinesSearch::MAX_LENGTH));
    }

    public function test_the_alerts_page_accepts_the_search_term_from_the_link(): void
    {
        [$manager, $team] = $this->member('fleet_manager');
        $machine = Machine::factory()->create(['team_id' => $team->id]);
        Alert::factory()->create(['team_id' => $team->id, 'machine_id' => $machine->id, 'title' => 'Zulu engine over temperature']);
        Alert::factory()->create(['team_id' => $team->id, 'machine_id' => $machine->id, 'title' => 'Unrelated tyre pressure']);
        $this->actingAs($manager);

        Livewire::withQueryParams(['q' => 'Zulu engine'])->test(Alerts::class)
            ->assertSet('search', 'Zulu engine')
            ->assertSee('Zulu engine over temperature')
            ->assertDontSee('Unrelated tyre pressure');
    }

    /**
     * The in-page filters used to put an `or` beside the team filter at the top level. They were saved only by a global scope that
     * not every model has: Production records had none, so a note in another team's records matched. Each is grouped now.
     */
    public function test_the_in_page_filters_cannot_match_another_teams_rows(): void
    {
        [$manager, $team] = $this->member('fleet_manager', 'Mine A');
        [, $other] = $this->member('fleet_manager', 'Mine B');

        $mine = MineArea::factory()->create(['team_id' => $team->id, 'name' => 'Own Pit']);
        $theirs = MineArea::factory()->create(['team_id' => $other->id, 'name' => 'Their Pit']);
        $rec = fn (Team $t, MineArea $a, string $notes) => ProductionRecord::create(['team_id' => $t->id, 'mine_area_id' => $a->id, 'record_date' => now()->toDateString(), 'shift' => 'day', 'quantity_produced' => 100, 'unit' => 't', 'target_quantity' => 120, 'notes' => $notes, 'status' => 'completed']);
        $rec($team, $mine, 'own routine note');
        $rec($other, $theirs, 'confidential blasting note');
        Alert::factory()->create(['team_id' => $other->id, 'machine_id' => Machine::factory()->create(['team_id' => $other->id])->id, 'title' => 'their hot brakes', 'description' => 'confidential brakes']);
        Machine::factory()->create(['team_id' => $other->id, 'name' => 'confidential dozer', 'model' => 'confidential']);
        $this->actingAs($manager);

        $records = Livewire::test(ProductionDashboard::class)->set('search', 'confidential')->instance()->getProductionRecordsProperty();
        $this->assertSame(0, $records->total());

        Livewire::test(Alerts::class)->set('search', 'confidential')->assertDontSee('their hot brakes');
        Livewire::test(Fleet::class)->set('search', 'confidential')->assertDontSee('confidential dozer');
    }
}
