<?php

namespace Tests\Feature;

use App\Models\Integration;
use App\Models\Machine;
use App\Models\MachineMetric;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurgePhantomMachinesTest extends TestCase
{
    use RefreshDatabase;

    private function phantom(): Machine
    {
        return Machine::factory()->create([
            'manufacturer_id' => null,
            'integration_id' => null,
            'last_location_update' => null,
            'status' => 'active',
        ]);
    }

    public function test_audit_lists_the_fleet_without_changing_anything(): void
    {
        $phantom = $this->phantom();
        $real = Machine::factory()->create(['manufacturer_id' => 'BELL-0001']);

        $this->artisan('machines:purge-phantom --audit')
            ->expectsOutputToContain('Read-only; nothing changed')
            ->assertSuccessful();

        $this->assertDatabaseHas('machines', ['id' => $phantom->id]);
        $this->assertDatabaseHas('machines', ['id' => $real->id]);
    }

    public function test_it_reports_without_deleting_by_default(): void
    {
        $phantom = $this->phantom();

        $this->artisan('machines:purge-phantom')
            ->expectsOutputToContain('Inspection only')
            ->assertSuccessful();

        $this->assertDatabaseHas('machines', ['id' => $phantom->id]);
    }

    public function test_confirm_without_an_id_refuses(): void
    {
        $phantom = $this->phantom();

        $this->artisan('machines:purge-phantom --confirm')
            ->expectsOutputToContain('Refusing to delete a set the operator has not named')
            ->assertFailed();

        $this->assertDatabaseHas('machines', ['id' => $phantom->id]);
    }

    public function test_it_deletes_only_the_named_phantom(): void
    {
        $phantom = $this->phantom();
        $other = $this->phantom();

        $this->artisan("machines:purge-phantom --confirm --id={$phantom->id}")
            ->assertSuccessful();

        $this->assertDatabaseMissing('machines', ['id' => $phantom->id]);
        $this->assertDatabaseHas('machines', ['id' => $other->id]);
    }

    public function test_it_refuses_a_machine_that_is_not_a_candidate(): void
    {
        // A real machine: claimed by a manufacturer and reporting position.
        $real = Machine::factory()->create([
            'manufacturer_id' => 'BELL-0001',
            'last_location_update' => now(),
        ]);

        $this->artisan("machines:purge-phantom --confirm --id={$real->id}")
            ->expectsOutputToContain('Not a phantom candidate')
            ->assertFailed();

        $this->assertDatabaseHas('machines', ['id' => $real->id]);
    }

    public function test_it_refuses_when_dependent_rows_exist(): void
    {
        // The load-bearing rail: deleting a machine cascades to ~15 tables,
        // so anything with history must survive even if it looks phantom.
        $phantom = $this->phantom();
        MachineMetric::factory()->create(['machine_id' => $phantom->id]);

        $this->artisan("machines:purge-phantom --confirm --id={$phantom->id}")
            ->expectsOutputToContain('refusing')
            ->assertFailed();

        $this->assertDatabaseHas('machines', ['id' => $phantom->id]);
    }

    public function test_it_repairs_the_stored_machines_count(): void
    {
        $team = Team::factory()->create();
        $integration = Integration::factory()->create(['team_id' => $team->id, 'machines_count' => 99]);
        $phantom = Machine::factory()->create([
            'team_id' => $team->id,
            'manufacturer_id' => null,
            'integration_id' => null,
            'last_location_update' => null,
        ]);
        Machine::factory()->count(2)->create([
            'team_id' => $team->id,
            'integration_id' => $integration->id,
        ]);

        $this->artisan("machines:purge-phantom --confirm --id={$phantom->id}")->assertSuccessful();

        // The phantom had no integration, so no count should have moved.
        $this->assertSame(99, $integration->fresh()->machines_count);
    }
}
