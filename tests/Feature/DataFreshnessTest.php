<?php

namespace Tests\Feature;

use App\Models\Machine;
use App\Models\MachineMetric;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Slice 3 of the live-operations UX program: freshness badges show the
 * DATA's own timestamp (Bell telemetry recorded_at), never the render or
 * sync moment, and go amber-stale past the per-data-type threshold
 * (telemetry: 1800s = 2x Bell's 15-minute polling cadence).
 */
class DataFreshnessTest extends TestCase
{
    use RefreshDatabase;

    private function userWithMachine(\DateTimeInterface $recordedAt): array
    {
        $user = User::factory()->withPersonalTeam()->create();
        $machine = Machine::factory()->create(['team_id' => $user->currentTeam->id]);
        // team_id must match the viewer's team: MachineMetric carries the
        // HasTeamFilters global scope, so a metric on the factory's default
        // (different) team is correctly invisible to this user.
        MachineMetric::factory()->create([
            'machine_id' => $machine->id,
            'team_id' => $user->currentTeam->id,
            'recorded_at' => $recordedAt,
        ]);

        return [$user, $machine];
    }

    public function test_machine_detail_shows_telemetry_age_from_recorded_at(): void
    {
        [$user, $machine] = $this->userWithMachine(now()->subSeconds(45));

        $response = $this->actingAs($user)->get("/fleet/{$machine->id}");

        $response->assertOk();
        $response->assertSee('Telemetry');
        $response->assertSee('<time', false);
    }

    public function test_stale_telemetry_is_marked_amber_not_presented_as_current(): void
    {
        [$user, $machine] = $this->userWithMachine(now()->subHours(3));

        $response = $this->actingAs($user)->get("/fleet/{$machine->id}");

        $response->assertOk();
        $response->assertSee('text-amber-400');
    }

    public function test_fleet_cards_carry_per_machine_freshness(): void
    {
        [$user] = $this->userWithMachine(now()->subMinutes(2));

        $response = $this->actingAs($user)->get('/fleet');

        $response->assertOk();
        $response->assertSee('<time', false);
    }

    public function test_machine_without_telemetry_says_no_data_yet_not_a_fake_time(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        Machine::factory()->create(['team_id' => $user->currentTeam->id]);

        $response = $this->actingAs($user)->get('/fleet');

        $response->assertOk();
        $response->assertSee('No data yet');
    }

    public function test_dashboard_dispatch_header_shows_honest_telemetry_age_not_compute_time(): void
    {
        [$user] = $this->userWithMachine(now()->subMinutes(5));

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        $response->assertSee('Telemetry');
        // The old header stamped the snapshot COMPUTE time and labeled it
        // "live" -- that exact string must never come back.
        $this->assertStringNotContainsString('live · updated', $response->getContent());
    }

    public function test_layout_carries_the_offline_banner(): void
    {
        [$user] = $this->userWithMachine(now());

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertSee('No connection', false);
    }
}
