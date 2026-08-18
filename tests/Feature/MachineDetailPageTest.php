<?php

namespace Tests\Feature;

use App\Models\Machine;
use App\Models\MachineMetric;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The /fleet/{machine} page had no feature test coverage before this file.
 * Added while re-theming resources/views/livewire/machine-detail.blade.php.
 */
class MachineDetailPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_away_from_machine_detail(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);
        $machine = Machine::factory()->create(['team_id' => $team->id]);

        $response = $this->get("/fleet/{$machine->id}");

        $response->assertRedirect('/login');
    }

    public function test_team_owner_can_view_machine_detail(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);
        $owner->update(['current_team_id' => $team->id]);
        $machine = Machine::factory()->create(['team_id' => $team->id]);

        $response = $this->actingAs($owner)->get("/fleet/{$machine->id}");

        $response->assertOk();
        $response->assertSee($machine->name);
    }

    /**
     * The sensor table used to read $metric->rpm and $metric->payload_weight
     * -- columns that do not exist (the real ones are engine_rpm and
     * load_weight) -- so every reading rendered N/A even when the telemetry
     * carried real values, and Time showed the sync insert time instead of
     * the provider's own reading timestamp (recorded_at).
     */
    public function test_sensor_table_maps_real_telemetry_columns_not_nonexistent_ones(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);
        $owner->update(['current_team_id' => $team->id]);
        $machine = Machine::factory()->create(['team_id' => $team->id]);

        MachineMetric::create([
            'team_id' => $team->id,
            'machine_id' => $machine->id,
            'recorded_at' => '2026-08-18 11:14:14',
            'engine_rpm' => 1850,
            'engine_temperature' => 82.4,
            'fuel_level' => 62,
            'load_weight' => 28.5,
        ]);

        $response = $this->actingAs($owner)->get("/fleet/{$machine->id}");

        $response->assertOk();
        // The provider's reading timestamp, not the row's created_at.
        $response->assertSee('11:14:14');
        $response->assertSee('1,850');
        $response->assertSee('82.4');
        $response->assertSee('28.5');
    }

    /**
     * Bell's ISO 15143-3 feed genuinely does not report RPM, engine
     * temperature or instantaneous load -- those cells must show a clear
     * "not reported" dash with an explanation, while the values Bell DOES
     * send per reading (fuel %, engine/idle hours, DEF %, odometer, engine
     * state) must all be visible instead of being hidden in raw_data.
     */
    public function test_sensor_table_shows_bell_fields_and_marks_unreported_ones(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);
        $owner->update(['current_team_id' => $team->id]);
        $machine = Machine::factory()->create([
            'team_id' => $team->id,
            'manufacturer' => 'bell',
        ]);

        // Exactly the shape BellService::buildCurrentMetric() persists.
        MachineMetric::create([
            'team_id' => $team->id,
            'machine_id' => $machine->id,
            'recorded_at' => '2026-08-18 11:14:14',
            'fuel_level' => 62.0,
            'operating_hours' => 8376.2,
            'idle_hours' => 3808.96,
            'load_weight' => null,
            'raw_data' => [
                'load_count' => 13230.0,
                'cumulative_payload' => 541150000.0,
                'payload_units' => 'kilogram',
                'def_percent' => 64.0,
                'odometer' => 94114.0,
                'odometer_units' => 'kilometre',
                'fuel_consumed_cumulative' => 170285.0,
                'fuel_units' => 'litre',
                'engine_running' => true,
            ],
        ]);

        $response = $this->actingAs($owner)->get("/fleet/{$machine->id}");

        $response->assertOk();
        // Engine hours on the summary card, formatted per app convention.
        $response->assertSee('Engine Hours');
        $response->assertSee('8,376.2 hrs');
        // Per-reading Bell values now surfaced in the table.
        $response->assertSee('3,809.0');
        $response->assertSee('64');
        $response->assertSee('94,114');
        // Unreported sensors show an explained dash, not a bare N/A.
        $response->assertSee('—');
        $response->assertSee('not reported by this machine');
    }

    public function test_machine_without_any_telemetry_still_renders(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);
        $owner->update(['current_team_id' => $team->id]);
        $machine = Machine::factory()->create(['team_id' => $team->id]);

        $response = $this->actingAs($owner)->get("/fleet/{$machine->id}");

        $response->assertOk();
        $response->assertSee($machine->name);
    }

    /**
     * The Current Location card read $machine->latitude / ->longitude --
     * columns that do not exist (the real ones are last_location_latitude /
     * last_location_longitude) -- so the section never rendered even for
     * machines with live GPS.
     */
    public function test_current_location_renders_from_the_real_location_columns(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);
        $owner->update(['current_team_id' => $team->id]);
        $machine = Machine::factory()->create([
            'team_id' => $team->id,
            'last_location_latitude' => -26.0231,
            'last_location_longitude' => 28.9387,
        ]);

        $response = $this->actingAs($owner)->get("/fleet/{$machine->id}");

        $response->assertOk();
        $response->assertSee('Current Location');
        $response->assertSee('-26.0231');
        $response->assertSee('28.9387');
    }

    public function test_a_user_from_another_team_cannot_view_the_machine(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);
        $machine = Machine::factory()->create(['team_id' => $team->id]);

        $otherUser = User::factory()->create();
        $otherTeam = Team::factory()->create(['user_id' => $otherUser->id]);
        $otherUser->update(['current_team_id' => $otherTeam->id]);

        // Machine uses HasTeamFilters (a global query scope), so the route's
        // implicit model binding never finds a cross-team machine in the
        // first place -- the request 404s before MachineDetail::mount()'s
        // own explicit 403 check would even run.
        $response = $this->actingAs($otherUser)->get("/fleet/{$machine->id}");

        $response->assertNotFound();
    }
}
