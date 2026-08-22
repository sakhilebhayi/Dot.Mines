<?php

namespace Tests\Feature;

use App\Livewire\AINotifications;
use App\Livewire\Dashboard;
use App\Livewire\MaintenanceDashboard;
use App\Livewire\RoutePlanning;
use App\Models\Alert;
use App\Models\MaintenanceRecord;
use App\Models\Route;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Refactor program R1 (brief §11): cross-tenant mutation attempts against
 * every Livewire component the authz audit flagged as carrying no explicit
 * authorize() call. The audit verdict was that each mutation is already
 * team-scoped by explicit where('team_id') plus the HasTeamFilters global
 * scope -- these tests freeze that property so a future refactor that drops
 * either layer fails CI instead of leaking across tenants.
 */
class LivewireTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private User $attacker;

    private User $victim;

    protected function setUp(): void
    {
        parent::setUp();

        $this->attacker = User::factory()->withPersonalTeam()->create();
        $this->victim = User::factory()->withPersonalTeam()->create();
    }

    public function test_dashboard_cannot_acknowledge_a_foreign_alert(): void
    {
        $alert = Alert::factory()->create([
            'team_id' => $this->victim->currentTeam->id,
            'status' => 'active',
        ]);

        try {
            Livewire::actingAs($this->attacker)
                ->test(Dashboard::class)
                ->call('acknowledgeAlert', $alert->id);
            $this->fail('Expected the foreign alert to be unreachable.');
        } catch (ModelNotFoundException) {
            // findOrFail under the team scope: exactly the wanted refusal.
        }

        $this->assertSame('active', $alert->fresh()->status);
    }

    public function test_maintenance_actions_cannot_touch_foreign_records(): void
    {
        $record = MaintenanceRecord::factory()->create([
            'team_id' => $this->victim->currentTeam->id,
            'status' => 'scheduled',
        ]);

        $component = Livewire::actingAs($this->attacker)->test(MaintenanceDashboard::class);
        $component->call('completeScheduledMaintenance', $record->id);
        $component->call('cancelScheduledMaintenance', $record->id);

        $this->assertSame('scheduled', $record->fresh()->status);
    }

    public function test_route_planning_cannot_delete_a_foreign_route(): void
    {
        $route = Route::factory()->create([
            'team_id' => $this->victim->currentTeam->id,
        ]);

        Livewire::actingAs($this->attacker)
            ->test(RoutePlanning::class)
            ->call('deleteRoute', $route->id);

        $this->assertNotNull(
            Route::withoutGlobalScopes()->find($route->id),
            'A foreign route must survive a cross-tenant delete attempt.',
        );
    }

    public function test_ai_notifications_cannot_acknowledge_foreign_alerts(): void
    {
        $alert = Alert::factory()->create([
            'team_id' => $this->victim->currentTeam->id,
            'status' => 'active',
        ]);

        Livewire::actingAs($this->attacker)
            ->test(AINotifications::class)
            ->call('acknowledge', $alert->id, 'alert');

        $this->assertSame('active', $alert->fresh()->status);
    }
}
