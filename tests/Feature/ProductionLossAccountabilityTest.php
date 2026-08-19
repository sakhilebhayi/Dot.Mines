<?php

namespace Tests\Feature;

use App\Livewire\ProductionLossPanel;
use App\Models\Machine;
use App\Models\MachineMetric;
use App\Models\ProductionLossEvent;
use App\Models\ProductionRecord;
use App\Models\Team;
use App\Models\User;
use App\Services\ProductionLossService;
use App\Services\TeamRoleProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Production Loss Accountability: telemetry-detected potential losses are
 * NEVER auto-confirmed, manual entries are validated and cannot double-count
 * overlapping windows, totals only include human-accounted events, and every
 * change is auditable and tenant-isolated.
 */
class ProductionLossAccountabilityTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{Team, Machine, User}
     */
    private function teamWithMachineAndManager(): array
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);
        $manager = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($manager->id);
        TeamRoleProvisioner::assignRole($manager, $team, 'fleet_manager');

        $machine = Machine::factory()->create(['team_id' => $team->id, 'status' => 'active']);

        return [$team, $machine, $manager];
    }

    private function addMeterReading(Machine $machine, string $at, float $operatingHours): MachineMetric
    {
        return MachineMetric::factory()->create([
            'team_id' => $machine->team_id,
            'machine_id' => $machine->id,
            'recorded_at' => $at,
            'operating_hours' => $operatingHours,
        ]);
    }

    // ── Detection ──────────────────────────────────────────────────────

    public function test_detection_creates_pending_event_when_active_machine_reports_but_meter_does_not_move(): void
    {
        [, $machine] = $this->teamWithMachineAndManager();

        // 8-hour telemetry window, engine-hours meter frozen at 1000.
        $this->addMeterReading($machine, now()->setTime(6, 0)->toDateTimeString(), 1000.0);
        $this->addMeterReading($machine, now()->setTime(10, 0)->toDateTimeString(), 1000.0);
        $this->addMeterReading($machine, now()->setTime(14, 0)->toDateTimeString(), 1000.1);

        $event = app(ProductionLossService::class)->detectForDay($machine, now());

        $this->assertNotNull($event);
        $this->assertSame(ProductionLossEvent::SOURCE_SYSTEM, $event->source);
        // The critical accountability rule: detection NEVER auto-confirms.
        $this->assertSame(ProductionLossEvent::STATUS_PENDING, $event->status);
        $this->assertNull($event->reason);
        $this->assertStringContainsString('engine-hours meter advanced only', (string) $event->detection_basis);
        $this->assertEqualsWithDelta(7.9, $event->lost_hours, 0.01);
        $this->assertNotEmpty($event->audit_log);
        $this->assertSame('detected', $event->audit_log[0]['action']);
    }

    public function test_detection_skips_machines_that_actually_worked_or_are_not_active(): void
    {
        [, $working] = $this->teamWithMachineAndManager();
        $this->addMeterReading($working, now()->setTime(6, 0)->toDateTimeString(), 1000.0);
        $this->addMeterReading($working, now()->setTime(14, 0)->toDateTimeString(), 1006.5);

        $this->assertNull(app(ProductionLossService::class)->detectForDay($working, now()));

        [, $inMaintenance] = $this->teamWithMachineAndManager();
        $inMaintenance->update(['status' => 'maintenance']);
        $this->addMeterReading($inMaintenance, now()->setTime(6, 0)->toDateTimeString(), 500.0);
        $this->addMeterReading($inMaintenance, now()->setTime(14, 0)->toDateTimeString(), 500.0);

        $this->assertNull(app(ProductionLossService::class)->detectForDay($inMaintenance, now()));
        $this->assertSame(0, ProductionLossEvent::count());
    }

    public function test_detection_requires_a_substantial_telemetry_window_and_never_duplicates(): void
    {
        [, $machine] = $this->teamWithMachineAndManager();

        // Only a 2-hour window: not enough evidence to call it a loss.
        $this->addMeterReading($machine, now()->setTime(6, 0)->toDateTimeString(), 1000.0);
        $this->addMeterReading($machine, now()->setTime(8, 0)->toDateTimeString(), 1000.0);
        $this->assertNull(app(ProductionLossService::class)->detectForDay($machine, now()));

        // Extend to a full window: detects once, then stays idempotent.
        $this->addMeterReading($machine, now()->setTime(14, 0)->toDateTimeString(), 1000.0);
        $this->assertNotNull(app(ProductionLossService::class)->detectForDay($machine, now()));
        $this->assertNull(app(ProductionLossService::class)->detectForDay($machine, now()));
        $this->assertSame(1, ProductionLossEvent::count());
    }

    public function test_scheduled_command_scans_yesterday_and_raises_pending_events_only(): void
    {
        [, $stalled] = $this->teamWithMachineAndManager();
        $yesterday = now()->subDay();
        $this->addMeterReading($stalled, $yesterday->copy()->setTime(6, 0)->toDateTimeString(), 2000.0);
        $this->addMeterReading($stalled, $yesterday->copy()->setTime(14, 0)->toDateTimeString(), 2000.0);

        [, $working] = $this->teamWithMachineAndManager();
        $this->addMeterReading($working, $yesterday->copy()->setTime(6, 0)->toDateTimeString(), 3000.0);
        $this->addMeterReading($working, $yesterday->copy()->setTime(14, 0)->toDateTimeString(), 3007.0);

        $this->artisan('production:detect-losses')
            ->expectsOutputToContain('1 potential production-loss event(s) raised for review')
            ->assertExitCode(0);

        $event = ProductionLossEvent::sole();
        $this->assertSame($stalled->id, $event->machine_id);
        $this->assertSame(ProductionLossEvent::STATUS_PENDING, $event->status);
    }

    // ── Manual entry ───────────────────────────────────────────────────

    public function test_fleet_manager_can_record_a_manual_loss_with_audit_trail(): void
    {
        [$team, $machine, $manager] = $this->teamWithMachineAndManager();

        Livewire::actingAs($manager)
            ->test(ProductionLossPanel::class, ['machine' => $machine])
            ->call('openRecordModal')
            ->set('lossDate', now()->toDateString())
            ->set('startTime', '08:00')
            ->set('endTime', '11:30')
            ->set('category', 'mechanical')
            ->set('reason', 'hydraulic')
            ->set('notes', 'Burst hose on the boom cylinder.')
            ->call('recordLoss')
            ->assertSet('showRecordModal', false);

        $event = ProductionLossEvent::sole();
        $this->assertSame($team->id, $event->team_id);
        $this->assertSame(ProductionLossEvent::SOURCE_USER, $event->source);
        $this->assertSame(ProductionLossEvent::STATUS_CONFIRMED, $event->status);
        $this->assertEqualsWithDelta(3.5, $event->lost_hours, 0.01);
        $this->assertSame('hydraulic', $event->reason);
        $this->assertSame($manager->id, $event->created_by);
        $this->assertSame('recorded', $event->audit_log[0]['action']);
        $this->assertDatabaseHas('activity_logs', ['team_id' => $team->id, 'action' => 'production_loss_recorded']);
    }

    public function test_manual_entry_rejects_end_before_start_and_invalid_reasons(): void
    {
        [, $machine, $manager] = $this->teamWithMachineAndManager();
        $service = app(ProductionLossService::class);

        try {
            $service->recordManualLoss($machine, $manager, [
                'started_at' => now()->setTime(10, 0)->toDateTimeString(),
                'ended_at' => now()->setTime(9, 0)->toDateTimeString(),
                'category' => 'mechanical',
                'reason' => 'breakdown',
            ]);
            $this->fail('End-before-start window was accepted.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('ended_at', $e->errors());
        }

        try {
            $service->recordManualLoss($machine, $manager, [
                'started_at' => now()->setTime(8, 0)->toDateTimeString(),
                'ended_at' => now()->setTime(9, 0)->toDateTimeString(),
                'category' => 'mechanical',
                'reason' => 'weather', // valid reason, wrong category
            ]);
            $this->fail('Cross-category reason was accepted.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('reason', $e->errors());
        }

        $this->assertSame(0, ProductionLossEvent::count());
    }

    // ── Double-count prevention ────────────────────────────────────────

    public function test_manual_entry_overlapping_a_pending_detection_classifies_it_instead_of_duplicating(): void
    {
        [$team, $machine, $manager] = $this->teamWithMachineAndManager();

        $detected = ProductionLossEvent::factory()->detected()->create([
            'team_id' => $team->id,
            'machine_id' => $machine->id,
            'started_at' => now()->setTime(6, 0),
            'ended_at' => now()->setTime(14, 0),
            'lost_hours' => 8.0,
        ]);

        $result = app(ProductionLossService::class)->recordManualLoss($machine, $manager, [
            'started_at' => now()->setTime(8, 0)->toDateTimeString(),
            'ended_at' => now()->setTime(12, 0)->toDateTimeString(),
            'category' => 'planned',
            'reason' => 'scheduled_maintenance',
            'notes' => 'Planned 250-hour service.',
        ]);

        // The detection was classified -- no second row, hours counted once.
        $this->assertSame(1, ProductionLossEvent::count());
        $this->assertSame($detected->id, $result->id);
        $this->assertSame(ProductionLossEvent::STATUS_CONFIRMED, $result->status);
        $this->assertSame('scheduled_maintenance', $result->reason);
        $this->assertSame($manager->id, $result->classified_by);
        $this->assertSame('classified', collect($result->audit_log)->last()['action']);
    }

    public function test_manual_entry_overlapping_a_confirmed_event_is_rejected(): void
    {
        [$team, $machine, $manager] = $this->teamWithMachineAndManager();

        ProductionLossEvent::factory()->create([
            'team_id' => $team->id,
            'machine_id' => $machine->id,
            'started_at' => now()->setTime(6, 0),
            'ended_at' => now()->setTime(10, 0),
            'lost_hours' => 4.0,
        ]);

        try {
            app(ProductionLossService::class)->recordManualLoss($machine, $manager, [
                'started_at' => now()->setTime(9, 0)->toDateTimeString(),
                'ended_at' => now()->setTime(11, 0)->toDateTimeString(),
                'category' => 'mechanical',
                'reason' => 'breakdown',
            ]);
            $this->fail('Overlapping window was double-recorded.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('started_at', $e->errors());
        }

        $this->assertSame(1, ProductionLossEvent::count());
    }

    // ── Classification flow ────────────────────────────────────────────

    public function test_manager_can_classify_a_detected_event_from_the_panel(): void
    {
        [$team, $machine, $manager] = $this->teamWithMachineAndManager();

        $detected = ProductionLossEvent::factory()->detected()->create([
            'team_id' => $team->id,
            'machine_id' => $machine->id,
        ]);

        Livewire::actingAs($manager)
            ->test(ProductionLossPanel::class, ['machine' => $machine])
            ->call('openClassify', $detected->id)
            ->set('category', 'environmental')
            ->set('reason', 'weather')
            ->set('notes', 'Site rained out after midday.')
            ->call('classifyEvent')
            ->assertSet('classifyingEventId', null);

        $detected->refresh();
        $this->assertSame(ProductionLossEvent::STATUS_CONFIRMED, $detected->status);
        $this->assertSame('weather', $detected->reason);
        $this->assertSame($manager->id, $detected->classified_by);
        $this->assertNotNull($detected->classified_at);
    }

    // ── Permissions & tenant isolation ─────────────────────────────────

    public function test_viewer_cannot_record_or_classify_losses(): void
    {
        [$team, $machine] = $this->teamWithMachineAndManager();
        $viewer = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($viewer->id);
        TeamRoleProvisioner::assignRole($viewer, $team, 'viewer');

        Livewire::actingAs($viewer)
            ->test(ProductionLossPanel::class, ['machine' => $machine])
            ->call('openRecordModal')
            ->assertStatus(403);

        $this->assertSame(0, ProductionLossEvent::count());
    }

    public function test_panel_is_isolated_to_the_machines_team(): void
    {
        [, $machine] = $this->teamWithMachineAndManager();

        $outsider = User::factory()->create();
        $otherTeam = Team::factory()->create(['user_id' => $outsider->id]);
        $outsider->update(['current_team_id' => $otherTeam->id]);

        Livewire::actingAs($outsider)
            ->test(ProductionLossPanel::class, ['machine' => $machine])
            ->assertStatus(403);
    }

    // ── Summary & impact ───────────────────────────────────────────────

    public function test_totals_exclude_unclassified_detections_and_pending_count_is_reported_separately(): void
    {
        [$team, $machine] = $this->teamWithMachineAndManager();

        ProductionLossEvent::factory()->create([
            'team_id' => $team->id,
            'machine_id' => $machine->id,
            'started_at' => now()->subHours(5),
            'ended_at' => now()->subHours(2),
            'lost_hours' => 3.0,
        ]);
        ProductionLossEvent::factory()->detected()->create([
            'team_id' => $team->id,
            'machine_id' => $machine->id,
            'started_at' => now()->subHours(10),
            'ended_at' => now()->subHours(6),
            'lost_hours' => 4.0,
        ]);

        $summary = app(ProductionLossService::class)->summaryForMachine($machine);

        $this->assertSame(3.0, $summary['total_hours']);
        $this->assertSame(1, $summary['event_count']);
        $this->assertSame(1, $summary['pending_review']);
        $this->assertSame('Breakdown', $summary['primary_reason']);
    }

    public function test_impact_estimate_uses_the_machines_own_recent_rate_or_is_absent(): void
    {
        [$team, $machine] = $this->teamWithMachineAndManager();
        $service = app(ProductionLossService::class);

        // No production history: no estimate is fabricated.
        $this->assertNull($service->estimateImpact($machine, 5.0));

        // 800 tonnes over an 8-hour engine-hour delta => 100 t/h.
        $this->addMeterReading($machine, now()->subDays(2)->toDateTimeString(), 1000.0);
        $this->addMeterReading($machine, now()->subDay()->toDateTimeString(), 1008.0);
        ProductionRecord::create([
            'team_id' => $team->id,
            'machine_id' => $machine->id,
            'record_date' => now()->subDay()->toDateString(),
            'shift' => 'continuous',
            'quantity_produced' => 800.0,
            'unit' => 'tonnes',
            'status' => 'completed',
        ]);

        $impact = $service->estimateImpact($machine, 5.0);

        $this->assertNotNull($impact);
        $this->assertSame(100.0, $impact['rate_per_hour']);
        $this->assertSame(500.0, $impact['estimated_loss']);
        $this->assertSame('tonnes', $impact['unit']);
    }

    // ── UX states ──────────────────────────────────────────────────────

    public function test_panel_shows_empty_state_and_pending_review_banner(): void
    {
        [$team, $machine, $manager] = $this->teamWithMachineAndManager();

        Livewire::actingAs($manager)
            ->test(ProductionLossPanel::class, ['machine' => $machine])
            ->assertSee('No production losses recorded');

        ProductionLossEvent::factory()->detected()->create([
            'team_id' => $team->id,
            'machine_id' => $machine->id,
        ]);

        Livewire::actingAs($manager)
            ->test(ProductionLossPanel::class, ['machine' => $machine])
            ->assertSee('1 potential production-loss event requires review')
            ->assertSee('Pending review');
    }
}
