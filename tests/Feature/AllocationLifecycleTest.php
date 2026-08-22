<?php

namespace Tests\Feature;

use App\Exceptions\InsufficientAllocationException;
use App\Livewire\Fleet;
use App\Models\Machine;
use App\Models\MachineAllocation;
use App\Models\Notification;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\Team;
use App\Models\User;
use App\Services\Billing\MachineEntitlementService;
use App\Services\Billing\MachineProvisioningService;
use App\Services\TeamRoleProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Allocation Slice 3: the subscription lifecycle (brief §11-§13, §22).
 * A lapsed subscription makes purchased allocations UNAVAILABLE -- no
 * machine is ever deleted, the ledger is never rewritten, and renewal
 * restores capacity with zero data movement. Decommissioning releases a
 * slot without erasing history, and every capacity event notifies.
 */
class AllocationLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): array
    {
        $user = User::factory()->withPersonalTeam()->create();
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

    private function subscription(Team $team, string $status, ?\DateTimeInterface $periodEnd = null): Subscription
    {
        $plan = SubscriptionPlan::query()->where('slug', 'adt-allocation')->firstOrFail();

        return Subscription::create([
            'team_id' => $team->id,
            'subscription_plan_id' => $plan->id,
            'status' => $status,
            'billing_cycle' => 'monthly',
            'current_period_start' => now()->subMonth(),
            'current_period_end' => $periodEnd ?? now()->subDay(),
        ]);
    }

    public function test_lapsed_subscription_suspends_purchased_allocations_without_touching_machines(): void
    {
        [, $team] = $this->owner();
        $this->grant($team, 'adt', 2);
        $machine = Machine::factory()->create(['team_id' => $team->id, 'machine_type' => 'adt']);
        $this->subscription($team, 'expired');

        $summary = app(MachineEntitlementService::class)->summary($team);

        $this->assertTrue($summary['suspended']);
        $this->assertSame(0, $summary['available']['adt']);
        // The machine keeps running -- only capacity is gated.
        $this->assertSame('occupying', $machine->fresh()->allocation_state);
        $this->assertSame(2, $summary['purchased']['adt'], 'The ledger is never rewritten on lapse.');

        $this->expectException(InsufficientAllocationException::class);
        app(MachineProvisioningService::class)->provision(
            $team,
            'adt',
            fn (): Machine => Machine::factory()->create(['team_id' => $team->id, 'machine_type' => 'adt']),
        );
    }

    public function test_renewal_restores_capacity_with_no_data_movement(): void
    {
        [, $team] = $this->owner();
        $this->grant($team, 'adt', 2);
        $subscription = $this->subscription($team, 'expired');

        $this->assertTrue(app(MachineEntitlementService::class)->summary($team)['suspended']);

        $subscription->update(['status' => 'active']);

        $summary = app(MachineEntitlementService::class)->summary($team);
        $this->assertFalse($summary['suspended']);
        $this->assertSame(2, $summary['available']['adt']);
    }

    public function test_canceled_subscription_stays_entitled_until_the_paid_period_ends(): void
    {
        [, $team] = $this->owner();
        $this->grant($team, 'adt', 1);
        $this->subscription($team, 'canceled', now()->addDays(10));

        $summary = app(MachineEntitlementService::class)->summary($team);

        $this->assertFalse($summary['suspended'], 'Brief §11: allocations remain active until the billing period ends.');
        $this->assertSame(1, $summary['available']['adt']);
    }

    public function test_admin_granted_ledgers_need_no_subscription(): void
    {
        [, $team] = $this->owner();
        MachineAllocation::create([
            'team_id' => $team->id,
            'class' => 'adt',
            'delta' => 3,
            'source' => 'admin',
            'reason' => 'Contract adjustment',
        ]);

        $summary = app(MachineEntitlementService::class)->summary($team);

        $this->assertFalse($summary['suspended']);
        $this->assertSame(3, $summary['available']['adt']);
    }

    public function test_decommissioning_releases_the_allocation_but_keeps_the_machine(): void
    {
        [$user, $team] = $this->owner();
        $this->grant($team, 'adt', 1);

        $machine = app(MachineProvisioningService::class)->provision(
            $team,
            'adt',
            fn (): Machine => Machine::factory()->create(['team_id' => $team->id, 'machine_type' => 'adt']),
        );

        Livewire::actingAs($user)
            ->test(Fleet::class)
            ->call('decommissionMachine', $machine->id);

        $this->assertSame('released', $machine->fresh()->allocation_state);
        $this->assertNotNull($machine->fresh(), 'History is preserved -- decommission is not delete.');

        // Replacement fits in the freed slot without another purchase (§13).
        $replacement = app(MachineProvisioningService::class)->provision(
            $team,
            'adt',
            fn (): Machine => Machine::factory()->create(['team_id' => $team->id, 'machine_type' => 'adt']),
        );
        $this->assertSame('occupying', $replacement->allocation_state);
    }

    public function test_activate_button_flips_a_pending_machine_when_capacity_exists(): void
    {
        config(['billing.trial_machine_allowance' => 0]);
        [$user, $team] = $this->owner();

        $pending = app(MachineProvisioningService::class)->provisionOrPend(
            $team,
            'adt',
            fn (): Machine => Machine::factory()->create(['team_id' => $team->id, 'machine_type' => 'adt']),
        );

        $this->grant($team, 'adt', 1);

        Livewire::actingAs($user)
            ->test(Fleet::class)
            ->call('activateMachine', $pending->id)
            ->assertHasNoErrors();

        $this->assertSame('occupying', $pending->fresh()->allocation_state);
    }

    public function test_capacity_notifications_fire_on_the_last_slots(): void
    {
        [, $team] = $this->owner();
        $this->grant($team, 'adt', 2);

        $provision = fn () => app(MachineProvisioningService::class)->provision(
            $team,
            'adt',
            fn (): Machine => Machine::factory()->create(['team_id' => $team->id, 'machine_type' => 'adt']),
        );

        $provision(); // 1 remaining -> "almost full"
        $this->assertSame(1, Notification::withoutGlobalScopes()->where('team_id', $team->id)->where('type', 'billing.capacity_low')->count());

        $provision(); // 0 remaining -> "full"
        $this->assertSame(1, Notification::withoutGlobalScopes()->where('team_id', $team->id)->where('type', 'billing.capacity_full')->count());
    }

    public function test_pending_discovery_notifies_the_team(): void
    {
        config(['billing.trial_machine_allowance' => 0]);
        [, $team] = $this->owner();

        app(MachineProvisioningService::class)->provisionOrPend(
            $team,
            'adt',
            fn (): Machine => Machine::factory()->create(['team_id' => $team->id, 'machine_type' => 'adt', 'name' => 'B50E-NEW']),
        );

        $notification = Notification::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->where('type', 'billing.machine_pending_allocation')
            ->first();

        $this->assertNotNull($notification);
        $this->assertStringContainsString('B50E-NEW', $notification->message);
    }
}
