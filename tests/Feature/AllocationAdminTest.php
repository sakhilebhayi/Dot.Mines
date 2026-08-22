<?php

namespace Tests\Feature;

use App\Models\MachineAllocation;
use App\Models\User;
use App\Services\Billing\MachineEntitlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Allocation Slice 4: audited admin adjustments (brief §17) and the
 * ledger-backed history surface (§18).
 */
class AllocationAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_adjustment_writes_a_reason_carrying_ledger_row(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam;

        $this->artisan('billing:adjust-allocation', [
            'team' => $team->id,
            'class' => 'adt',
            'delta' => 2,
            '--reason' => 'Contract adjustment',
            '--by' => $user->id,
        ])->assertSuccessful();

        $row = MachineAllocation::withoutGlobalScopes()->where('team_id', $team->id)->firstOrFail();
        $this->assertSame('admin', $row->source);
        $this->assertSame(2, $row->delta);
        $this->assertSame('Contract adjustment', $row->reason);
        $this->assertSame($user->id, $row->created_by);
    }

    public function test_admin_adjustment_without_a_reason_is_refused(): void
    {
        $user = User::factory()->withPersonalTeam()->create();

        $this->artisan('billing:adjust-allocation', [
            'team' => $user->currentTeam->id,
            'class' => 'adt',
            'delta' => 2,
        ])->assertFailed();

        $this->assertSame(0, MachineAllocation::withoutGlobalScopes()->count());
    }

    public function test_negative_adjustments_revoke_capacity(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam;

        $this->artisan('billing:adjust-allocation', ['team' => $team->id, 'class' => 'heavy', 'delta' => 3, '--reason' => 'Setup'])->assertSuccessful();
        $this->artisan('billing:adjust-allocation', ['team' => $team->id, 'class' => 'heavy', 'delta' => -1, '--reason' => 'Correction'])->assertSuccessful();

        $summary = app(MachineEntitlementService::class)->summary($team);
        $this->assertSame(2, $summary['purchased']['heavy']);
        // Both rows survive: history is append-only, never edited.
        $this->assertSame(2, MachineAllocation::withoutGlobalScopes()->where('team_id', $team->id)->count());
    }

    public function test_billing_page_shows_the_allocation_history(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        MachineAllocation::create([
            'team_id' => $user->currentTeam->id,
            'class' => 'adt',
            'delta' => 2,
            'source' => 'admin',
            'reason' => 'Contract adjustment',
        ]);

        $response = $this->actingAs($user)->get('/billing');

        $response->assertOk();
        $response->assertSee('Allocation History');
        $response->assertSee('+2 ADT');
        $response->assertSee('Contract adjustment');
    }
}
