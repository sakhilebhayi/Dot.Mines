<?php

namespace Tests\Feature\Api;

use App\Http\Middleware\NormalizeApiParameters;
use App\Models\Geofence;
use App\Models\Machine;
use App\Models\MineArea;
use App\Models\Team;
use App\Models\User;
use App\Services\OpenApiGenerator;
use App\Services\TeamRoleProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The API speaks one parameter vocabulary, and still understands the old one.
 *
 * A date range used to be `start_date`/`end_date` on fuel and maintenance but
 * `date_from`/`date_to` on geofences; a filter was `status`/`type` everywhere
 * except machines, where it was `filter_status`/`filter_type`. Every endpoint
 * now uses the majority spelling, and NormalizeApiParameters translates the
 * old names so existing integrations keep working.
 */
class ParameterNamingTest extends TestCase
{
    use RefreshDatabase;

    private function actingUser(): User
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->update(['current_team_id' => $team->id]);
        TeamRoleProvisioner::assignRole($user, $team, 'admin');
        Sanctum::actingAs($user->fresh(), ['*']);

        return $user->fresh();
    }

    private function seedMachines(User $user): void
    {
        $area = MineArea::factory()->create(['team_id' => $user->current_team_id]);

        Machine::factory()->create([
            'team_id' => $user->current_team_id,
            'mine_area_id' => $area->id,
            'model' => 'B45E',
            'name' => 'ACTIVE-DIGGER',
            'status' => 'active',
            'machine_type' => 'excavator',
        ]);

        Machine::factory()->create([
            'team_id' => $user->current_team_id,
            'mine_area_id' => $area->id,
            'model' => 'B45E',
            'name' => 'IDLE-HAULER',
            'status' => 'idle',
            'machine_type' => 'haul_truck',
        ]);
    }

    public function test_machines_filter_by_the_canonical_status_parameter(): void
    {
        $user = $this->actingUser();
        $this->seedMachines($user);

        $names = collect($this->getJson('/api/machines?status=active')->assertOk()->json('data'))->pluck('name');

        $this->assertTrue($names->contains('ACTIVE-DIGGER'));
        $this->assertFalse($names->contains('IDLE-HAULER'));
    }

    public function test_the_legacy_filter_status_name_still_works(): void
    {
        $user = $this->actingUser();
        $this->seedMachines($user);

        $names = collect($this->getJson('/api/machines?filter_status=active')->assertOk()->json('data'))->pluck('name');

        $this->assertTrue($names->contains('ACTIVE-DIGGER'), 'An existing integration sending filter_status must keep working.');
        $this->assertFalse($names->contains('IDLE-HAULER'));
    }

    public function test_the_legacy_filter_type_name_still_works(): void
    {
        $user = $this->actingUser();
        $this->seedMachines($user);

        $names = collect($this->getJson('/api/machines?filter_type=haul_truck')->assertOk()->json('data'))->pluck('name');

        $this->assertTrue($names->contains('IDLE-HAULER'));
        $this->assertFalse($names->contains('ACTIVE-DIGGER'));
    }

    public function test_the_canonical_name_wins_when_a_client_sends_both(): void
    {
        $user = $this->actingUser();
        $this->seedMachines($user);

        // Mid-migration a client may briefly send both. The new name decides,
        // so the outcome is never ambiguous.
        $names = collect(
            $this->getJson('/api/machines?status=active&filter_status=idle')->assertOk()->json('data')
        )->pluck('name');

        $this->assertTrue($names->contains('ACTIVE-DIGGER'));
        $this->assertFalse($names->contains('IDLE-HAULER'));
    }

    public function test_geofence_entries_accept_both_range_spellings(): void
    {
        $user = $this->actingUser();
        $geofence = Geofence::factory()->create(['team_id' => $user->current_team_id]);

        $this->getJson("/api/geofences/{$geofence->id}/entries?start_date=2026-01-01&end_date=2026-12-31")
            ->assertOk();

        // The old spelling is translated rather than rejected as unknown.
        $this->getJson("/api/geofences/{$geofence->id}/entries?date_from=2026-01-01&date_to=2026-12-31")
            ->assertOk();
    }

    public function test_a_legacy_range_still_satisfies_a_required_rule(): void
    {
        $user = $this->actingUser();
        $geofence = Geofence::factory()->create(['team_id' => $user->current_team_id]);

        // tonnage-stats requires the range. Sending only the legacy names must
        // pass validation -- normalization happens before the controller runs.
        $this->getJson("/api/geofences/{$geofence->id}/tonnage-stats?date_from=2026-01-01&date_to=2026-12-31")
            ->assertOk();

        $this->getJson("/api/geofences/{$geofence->id}/tonnage-stats")
            ->assertStatus(422);
    }

    public function test_every_time_bounded_endpoint_accepts_the_standard_range(): void
    {
        $user = $this->actingUser();
        $this->seedMachines($user);
        $machine = Machine::where('team_id', $user->current_team_id)->firstOrFail();

        // These two used to offer only a relative window (hours_back / days),
        // so a client had to learn a different spelling per endpoint.
        $this->getJson("/api/machines/{$machine->id}/metrics?start_date=2026-01-01&end_date=2026-12-31")->assertOk();
        $this->getJson('/api/notifications/stats?start_date=2026-01-01&end_date=2026-12-31')->assertOk();

        // The relative shorthands still work.
        $this->getJson("/api/machines/{$machine->id}/metrics?hours_back=24")->assertOk();
        $this->getJson('/api/notifications/stats?days=7')->assertOk();
    }

    public function test_the_generated_docs_advertise_the_canonical_names(): void
    {
        $spec = app(OpenApiGenerator::class)->generate();

        $machineParams = collect($spec['paths']['/api/v1/machines']['get']['parameters'])->pluck('name');
        $this->assertTrue($machineParams->contains('status'), 'Docs must advertise the canonical filter name.');
        $this->assertFalse($machineParams->contains('filter_status'), 'Docs must not advertise a deprecated alias as current.');

        $entryParams = collect($spec['paths']['/api/v1/geofences/{geofence}/entries']['get']['parameters'])->pluck('name');
        $this->assertTrue($entryParams->contains('start_date'));
        $this->assertFalse($entryParams->contains('date_from'));
    }

    public function test_no_alias_shadows_a_real_parameter(): void
    {
        // An alias mapping onto a name some endpoint already uses for
        // something else would silently corrupt that endpoint's input.
        foreach (NormalizeApiParameters::ALIASES as $legacy => $canonical) {
            $this->assertNotSame($legacy, $canonical);
            $this->assertArrayNotHasKey(
                $canonical,
                NormalizeApiParameters::ALIASES,
                "An alias target must not itself be an alias ({$canonical})."
            );
        }
    }
}
