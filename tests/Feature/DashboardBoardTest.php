<?php

namespace Tests\Feature;

use App\Livewire\Dashboard;
use App\Models\Alert;
use App\Models\Machine;
use App\Models\MachineMetric;
use App\Models\MaintenanceRecord;
use App\Models\ProductionRecord;
use App\Models\Team;
use App\Models\User;
use App\Services\TeamRoleProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The operations board: open alerts with the stored priority word,
 * Acknowledge only for people who may, the fleet table, production and
 * the on-shift rows. Every figure is the current team's own.
 */
class DashboardBoardTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: User, 1: Team} */
    private function memberWithRole(string $role): array
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id, 'name' => 'Kopano Colliery']);
        $member = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($member->id);
        TeamRoleProvisioner::assignRole($member, $team, $role);

        return [$member, $team];
    }

    private function alert(Team $team, string $priority, string $title, ?Machine $machine = null): Alert
    {
        return Alert::create([
            'team_id' => $team->id,
            'machine_id' => $machine?->id,
            'type' => 'sensor',
            'title' => $title,
            'description' => $title,
            'priority' => $priority,
            'status' => 'active',
            'triggered_at' => now()->subMinutes(2),
        ]);
    }

    public function test_a_critical_alert_is_printed_as_critical_in_the_signal_colour_never_as_low(): void
    {
        [$member, $team] = $this->memberWithRole('operator');
        $this->alert($team, 'critical', 'Engine Over Temperature');
        $this->alert($team, 'high', 'Low Fuel Level');
        $this->alert($team, 'medium', 'Filter due');
        $this->alert($team, 'low', 'Door ajar');

        $html = Livewire::actingAs($member)->test(Dashboard::class)->html();

        preg_match_all('/<span class="([^"]*)" data-priority>([^<]+)<\/span>/', $html, $chips, PREG_SET_ORDER);
        $byWord = [];
        foreach ($chips as [, $class, $word]) {
            $byWord[$word] = str_contains($class, 'dm-signal');
        }

        $this->assertEquals(['Critical' => true, 'High' => true, 'Medium' => false, 'Low' => false], $byWord);
        $this->assertSame(1, substr_count($html, '>Low<'), 'LOW appears for the one low alert only');
    }

    public function test_priority_chip_maps_every_stored_word_and_never_folds_critical_into_low(): void
    {
        $this->assertSame(['label' => 'Critical', 'signal' => true], Dashboard::priorityChip('critical'));
        $this->assertSame(['label' => 'High', 'signal' => true], Dashboard::priorityChip('high'));
        $this->assertSame(['label' => 'Medium', 'signal' => false], Dashboard::priorityChip('medium'));
        $this->assertSame(['label' => 'Low', 'signal' => false], Dashboard::priorityChip('low'));
    }

    /** @return array<string, array{0: string}> */
    public static function rolesThatMayAcknowledge(): array
    {
        return ['admin' => ['admin'], 'fleet manager' => ['fleet_manager'], 'operator' => ['operator']];
    }

    #[DataProvider('rolesThatMayAcknowledge')]
    public function test_roles_with_the_permission_see_the_acknowledge_control(string $role): void
    {
        [$member, $team] = $this->memberWithRole($role);
        $this->alert($team, 'critical', 'Engine Over Temperature');

        $html = Livewire::actingAs($member)->test(Dashboard::class)->html();

        $this->assertSame(1, substr_count($html, 'data-acknowledge'));
        $this->assertStringContainsString('Acknowledge', $html);
    }

    public function test_a_viewer_does_not_see_the_control_and_the_server_still_refuses(): void
    {
        [$viewer, $team] = $this->memberWithRole('viewer');
        $alert = $this->alert($team, 'critical', 'Engine Over Temperature');

        $component = Livewire::actingAs($viewer)->test(Dashboard::class);

        $html = $component->html();
        $this->assertStringContainsString('Engine Over Temperature', $html);
        $this->assertStringNotContainsString('data-acknowledge', $html);
        $this->assertStringNotContainsString('Acknowledge', $html);

        // Hiding the control is not the guard: a hand-made call is refused.
        $component->call('acknowledgeAlert', $alert->id)->assertForbidden();
        $this->assertSame('active', $alert->fresh()?->status);
    }

    public function test_acknowledging_removes_the_row_and_the_open_count(): void
    {
        [$member, $team] = $this->memberWithRole('operator');
        $keep = $this->alert($team, 'high', 'Low Fuel Level');
        $clear = $this->alert($team, 'critical', 'Engine Over Temperature');

        $component = Livewire::actingAs($member)->test(Dashboard::class)
            ->assertSee('Engine Over Temperature')
            ->call('acknowledgeAlert', $clear->id)
            ->assertDispatched('alert-updated');

        $component->assertDontSee('Engine Over Temperature')->assertSee($keep->title);
        $this->assertSame('1', $this->reading($component->html(), 'active-alerts'));
    }

    private function reading(string $html, string $key): ?string
    {
        return preg_match('/data-reading="'.preg_quote($key, '/').'">([^<]*)</', $html, $m) === 1 ? trim($m[1]) : null;
    }

    public function test_the_dashboard_has_one_primary_action_and_the_viewer_never_gets_alerts_as_primary(): void
    {
        [$viewer, $team] = $this->memberWithRole('viewer');
        $this->alert($team, 'critical', 'Engine Over Temperature');
        Machine::factory()->create(['team_id' => $team->id]);
        \App\Models\MineArea::factory()->create(['team_id' => $team->id]);

        $html = (string) $this->actingAs($viewer)->get('/dashboard')->getContent();

        $this->assertSame(1, substr_count($html, 'data-primary-action'));
        $this->assertMatchesRegularExpression('/data-primary-action[^>]*>\s*Open the live map/', $html);
    }

    public function test_the_fleet_table_shows_state_fuel_alerts_and_the_machines_own_age(): void
    {
        [$member, $team] = $this->memberWithRole('operator');
        $moving = Machine::factory()->create(['team_id' => $team->id, 'name' => 'Volvo A45G #001', 'status' => 'active']);
        $silent = Machine::factory()->create(['team_id' => $team->id, 'name' => 'Bell B40E #004', 'status' => 'maintenance']);
        Machine::factory()->create(['team_id' => $team->id, 'name' => 'Never reported', 'status' => 'active']);

        MachineMetric::factory()->create([
            'team_id' => $team->id, 'machine_id' => $moving->id, 'speed' => 18, 'fuel_level' => 64,
            'operating_hours' => 6240, 'recorded_at' => now()->subMinutes(2),
        ]);
        MachineMetric::factory()->create([
            'team_id' => $team->id, 'machine_id' => $silent->id, 'speed' => 0,
            'recorded_at' => now()->subHours(5), 'created_at' => now()->subHours(5),
        ]);
        $this->alert($team, 'critical', 'Engine Over Temperature', $silent);

        $html = Livewire::actingAs($member)->test(Dashboard::class)->html();

        $this->assertStringContainsString('1 travelling, 2 no telemetry', $html);
        $this->assertStringContainsString('18 km/h', $html);
        $this->assertStringContainsString('6 240 h', $html);
        $this->assertStringContainsString('2m', $html);
        $this->assertStringContainsString('5h', $html);

        // Needs attention first: the machine with the open alert leads the table.
        $this->assertLessThan(strpos($html, 'Volvo A45G #001'), strpos($html, 'Bell B40E #004'));
        // The panel for the alerted machine names its open alert.
        $this->assertStringContainsString('1, critical: Engine Over Temperature', $html);
        $this->assertStringContainsString('No reading', $html);
    }

    public function test_every_query_is_company_scoped_and_another_teams_records_never_appear(): void
    {
        [$member, $team] = $this->memberWithRole('operator');
        Machine::factory()->create(['team_id' => $team->id, 'name' => 'Ours #1']);

        $other = Team::factory()->create(['user_id' => User::factory()->create()->id]);
        $foreignMachine = Machine::factory()->create(['team_id' => $other->id, 'name' => 'Theirs #9']);
        $this->alert($other, 'critical', 'Foreign alert', $foreignMachine);
        MachineMetric::factory()->create(['team_id' => $other->id, 'machine_id' => $foreignMachine->id, 'fuel_level' => 99, 'recorded_at' => now()]);
        MaintenanceRecord::factory()->count(3)->create(['team_id' => $other->id, 'machine_id' => $foreignMachine->id, 'status' => 'in_progress']);
        ProductionRecord::create([
            'team_id' => $other->id, 'record_date' => now()->subDay(), 'shift' => 'day',
            'quantity_produced' => 5555, 'unit' => 'tonnes', 'status' => 'completed',
        ]);

        $html = Livewire::actingAs($member)->test(Dashboard::class)->html();

        $this->assertStringContainsString('Ours #1', $html);
        $this->assertStringNotContainsString('Theirs #9', $html);
        $this->assertStringNotContainsString('Foreign alert', $html);
        $this->assertStringNotContainsString('5 555', $html);
        $this->assertStringContainsString('0 in progress, 0 overdue', $html);
        $this->assertSame('0', $this->reading($html, 'active-alerts'));
        $this->assertSame('1', $this->reading($html, 'machines'));
        $this->assertStringContainsString('No production recorded in the last 7 days', $html);
    }

    public function test_production_totals_match_the_records_and_name_the_target(): void
    {
        [$member, $team] = $this->memberWithRole('viewer');

        foreach ([1 => 1000, 2 => 1250, 3 => 900] as $daysAgo => $quantity) {
            ProductionRecord::create([
                'team_id' => $team->id, 'record_date' => now()->subDays($daysAgo), 'shift' => 'day',
                'quantity_produced' => $quantity, 'target_quantity' => 1250, 'unit' => 'tonnes', 'status' => 'completed',
            ]);
        }
        // Outside the seven-day window: must not be counted.
        ProductionRecord::create([
            'team_id' => $team->id, 'record_date' => now()->subDays(20), 'shift' => 'day',
            'quantity_produced' => 7777, 'target_quantity' => 1250, 'unit' => 'tonnes', 'status' => 'completed',
        ]);

        $html = Livewire::actingAs($member)->test(Dashboard::class)->html();

        $this->assertStringContainsString('3 150 t', $html);
        $this->assertStringContainsString('of 3 750 t target, 84 %', $html);
        $this->assertSame(3, substr_count($html, 'class="dm-bar"'));
        $this->assertStringContainsString('<title id="dm-ct">', $html);
        $this->assertStringNotContainsString('7 777', $html);
    }

    public function test_maintenance_counts_in_progress_and_overdue(): void
    {
        [$member, $team] = $this->memberWithRole('viewer');
        $machine = Machine::factory()->create(['team_id' => $team->id]);

        MaintenanceRecord::factory()->create(['team_id' => $team->id, 'machine_id' => $machine->id, 'status' => 'in_progress', 'scheduled_date' => now()->addDay()]);
        MaintenanceRecord::factory()->create(['team_id' => $team->id, 'machine_id' => $machine->id, 'status' => 'scheduled', 'scheduled_date' => now()->subDays(3)]);
        MaintenanceRecord::factory()->create(['team_id' => $team->id, 'machine_id' => $machine->id, 'status' => 'scheduled', 'scheduled_date' => now()->addDays(3)]);

        $html = Livewire::actingAs($member)->test(Dashboard::class)->html();

        $this->assertStringContainsString('1 in progress, 1 overdue', $html);
    }

    public function test_an_empty_team_gets_honest_empty_states_and_no_alert_region(): void
    {
        [$member] = $this->memberWithRole('viewer');

        $html = Livewire::actingAs($member)->test(Dashboard::class)->html();

        $this->assertStringContainsString('No machines in the fleet yet.', $html);
        $this->assertStringContainsString('No production recorded in the last 7 days', $html);
        $this->assertStringContainsString('No tank or dispensing record yet', $html);
        $this->assertStringNotContainsString('data-region="open-alerts"', $html);
        $this->assertStringContainsString('No telemetry recorded yet', $html);
        $this->assertSame('0', $this->reading($html, 'machines'));
    }

    public function test_the_board_query_count_does_not_grow_with_the_fleet(): void
    {
        [$member, $team] = $this->memberWithRole('operator');
        $counts = [];

        foreach ([2, 12] as $add) {
            Machine::factory()->count($add)->create(['team_id' => $team->id])->each(function (Machine $machine) use ($team): void {
                MachineMetric::factory()->create(['team_id' => $team->id, 'machine_id' => $machine->id, 'fuel_level' => 50, 'operating_hours' => 100, 'recorded_at' => now()]);
                $this->alert($team, 'high', 'Low Fuel Level', $machine);
            });

            $total = Machine::where('team_id', $team->id)->count();
            DB::flushQueryLog();
            DB::enableQueryLog();
            Livewire::withoutLazyLoading();
            Livewire::actingAs($member)->test(Dashboard::class);
            $counts[$total] = count(DB::getQueryLog());
        }

        $this->assertGreaterThan(10, $counts[2]);
        $this->assertLessThanOrEqual($counts[2], $counts[14], 'queries must not depend on the number of machines: '.json_encode($counts));
    }

    public function test_motion_is_confined_to_the_calm_parts_and_every_animation_has_a_reduced_motion_rule(): void
    {
        $css = (string) file_get_contents(resource_path('css/app.css'));
        $start = strpos($css, 'Dashboard (R-51)');
        $this->assertNotFalse($start);
        $dash = substr($css, (int) $start);

        // Alerts only appear: no transform, no stagger, no infinite loops anywhere.
        $this->assertMatchesRegularExpression('/\.dm-alerts li \{[^}]*animation: dm-appear 120ms ease-out both;/', $dash);
        $this->assertDoesNotMatchRegularExpression('/\.dm-alerts[^{]*\{[^}]*(transform|animation-delay)/', $dash);
        $this->assertStringNotContainsString('infinite', $dash);

        preg_match('/@media \(prefers-reduced-motion: reduce\) \{(.*?)\n    \}/s', $dash, $reduce);
        $this->assertNotEmpty($reduce, 'a reduced-motion block exists');
        foreach (['.dm-lede__text', '.dm-alerts li', '.dm-row', '.dm-panel', '.dm-bar'] as $animated) {
            $this->assertStringContainsString($animated, $reduce[1], $animated.' is switched off for reduced motion');
        }
    }
}
