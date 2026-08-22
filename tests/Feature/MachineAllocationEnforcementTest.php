<?php

namespace Tests\Feature;

use App\Exceptions\InsufficientAllocationException;
use App\Livewire\Fleet;
use App\Models\Machine;
use App\Models\MachineAllocation;
use App\Models\Team;
use App\Models\User;
use App\Services\Billing\MachineEntitlementService;
use App\Services\Billing\MachineProvisioningService;
use App\Services\TeamRoleProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Allocation Slice 1: the core invariant -- no machine can come into
 * existence on ANY path (Fleet page, REST API, OEM integration) without
 * an available allocation. Balances come from the append-only
 * machine_allocations ledger; unsubscribed teams get the configured
 * trial allowance; existing over-allocated accounts keep their machines
 * but cannot add more.
 */
class MachineAllocationEnforcementTest extends TestCase
{
    use RefreshDatabase;

    private function teamWithOwner(): array
    {
        $user = User::factory()->withPersonalTeam()->create();
        // Machine create/delete authorization is role-based; the personal
        // team owner needs an explicit admin role like every other test
        // that exercises Fleet actions.
        TeamRoleProvisioner::assignRole($user, $user->currentTeam, 'admin');

        return [$user, $user->currentTeam];
    }

    private function grant(Team $team, string $class, int $qty): void
    {
        MachineAllocation::create([
            'team_id' => $team->id,
            'class' => $class,
            'delta' => $qty,
            'source' => 'purchase',
        ]);
    }

    private function provision(Team $team, string $type = 'adt'): Machine
    {
        return app(MachineProvisioningService::class)->provision(
            $team,
            $type,
            fn (): Machine => Machine::factory()->create(['team_id' => $team->id, 'machine_type' => $type]),
        );
    }

    public function test_trial_team_can_create_up_to_the_allowance_and_no_further(): void
    {
        config(['billing.trial_machine_allowance' => 2]);
        [, $team] = $this->teamWithOwner();

        $this->provision($team);
        $this->provision($team);

        $this->expectException(InsufficientAllocationException::class);
        $this->provision($team);
    }

    public function test_purchased_allocations_are_enforced_per_class(): void
    {
        [, $team] = $this->teamWithOwner();
        $this->grant($team, 'adt', 2);
        $this->grant($team, 'heavy', 1);

        $this->provision($team, 'adt');
        $this->provision($team, 'adt');
        $this->provision($team, 'excavator'); // heavy

        // ADT pool exhausted; heavy pool exhausted -- both classes reject.
        try {
            $this->provision($team, 'adt');
            $this->fail('Third ADT should have been rejected.');
        } catch (InsufficientAllocationException) {
        }

        $this->expectException(InsufficientAllocationException::class);
        $this->provision($team, 'dozer');
    }

    public function test_deleting_a_machine_releases_its_allocation(): void
    {
        [, $team] = $this->teamWithOwner();
        $this->grant($team, 'adt', 1);

        $machine = $this->provision($team, 'adt');

        try {
            $this->provision($team, 'adt');
            $this->fail('Second ADT should have been rejected.');
        } catch (InsufficientAllocationException) {
        }

        $machine->delete();

        $replacement = $this->provision($team, 'adt');
        $this->assertSame('occupying', $replacement->allocation_state);
    }

    public function test_cross_tenant_ledgers_are_isolated(): void
    {
        [, $teamA] = $this->teamWithOwner();
        [, $teamB] = $this->teamWithOwner();
        config(['billing.trial_machine_allowance' => 0]);

        $this->grant($teamA, 'adt', 5);

        $this->expectException(InsufficientAllocationException::class);
        $this->provision($teamB, 'adt');
    }

    public function test_over_allocated_legacy_team_keeps_machines_but_cannot_add(): void
    {
        config(['billing.trial_machine_allowance' => 2]);
        [, $team] = $this->teamWithOwner();

        // Legacy data: machines that predate enforcement (migration marks
        // them 'occupying' by default).
        Machine::factory()->count(5)->create(['team_id' => $team->id, 'machine_type' => 'adt']);

        $summary = app(MachineEntitlementService::class)->summary($team);
        $this->assertTrue($summary['over_allocated']);
        $this->assertSame(5, $summary['occupied']['adt']);

        $this->expectException(InsufficientAllocationException::class);
        $this->provision($team);
    }

    public function test_fleet_page_rejects_creation_at_capacity_with_honest_message(): void
    {
        config(['billing.trial_machine_allowance' => 0]);
        [$user, $team] = $this->teamWithOwner();

        Livewire::actingAs($user)
            ->test(Fleet::class)
            ->set('name', 'ADT-99')
            ->set('model', 'B50E')
            ->set('machineType', 'adt')
            ->set('status', 'active')
            ->call('saveMachine')
            ->assertHasErrors('allocation');

        $this->assertSame(0, Machine::withoutGlobalScopes()->where('team_id', $team->id)->count());
    }

    public function test_api_store_returns_422_at_capacity(): void
    {
        config(['billing.trial_machine_allowance' => 0]);
        [$user] = $this->teamWithOwner();

        $response = $this->actingAs($user)->postJson('/api/machines', [
            'name' => 'ADT-API',
            'machine_type' => 'bell',
            'registration_number' => 'REG-1',
            'serial_number' => 'SER-1',
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('errors.allocation.0', fn (string $msg) => str_contains($msg, 'No machine allocations available'));
    }

    public function test_pending_machines_do_not_consume_capacity_and_can_activate_later(): void
    {
        config(['billing.trial_machine_allowance' => 0]);
        [, $team] = $this->teamWithOwner();

        $service = app(MachineProvisioningService::class);

        // Discovery beyond capacity: recorded as pending, never refused.
        $pending = $service->provisionOrPend(
            $team,
            'adt',
            fn (): Machine => Machine::factory()->create(['team_id' => $team->id, 'machine_type' => 'adt']),
        );

        $this->assertSame('pending_activation', $pending->allocation_state);
        $this->assertSame(0, app(MachineEntitlementService::class)->summary($team)['occupied']['adt']);

        // Capacity arrives; activation flips the machine to occupying.
        $this->grant($team, 'adt', 1);
        $service->activate($pending);

        $this->assertSame('occupying', $pending->fresh()->allocation_state);
        $this->assertSame(1, app(MachineEntitlementService::class)->summary($team)['occupied']['adt']);
    }

    public function test_activation_without_capacity_is_rejected(): void
    {
        config(['billing.trial_machine_allowance' => 0]);
        [, $team] = $this->teamWithOwner();

        $pending = app(MachineProvisioningService::class)->provisionOrPend(
            $team,
            'adt',
            fn (): Machine => Machine::factory()->create(['team_id' => $team->id, 'machine_type' => 'adt']),
        );

        $this->expectException(InsufficientAllocationException::class);
        app(MachineProvisioningService::class)->activate($pending);
    }

    public function test_unmapped_machine_types_bill_as_the_cheaper_adt_class(): void
    {
        $service = app(MachineEntitlementService::class);

        $this->assertSame('adt', $service->classFor('other'));
        $this->assertSame('adt', $service->classFor('ldv'));
        $this->assertSame('heavy', $service->classFor('excavator'));
    }
}
