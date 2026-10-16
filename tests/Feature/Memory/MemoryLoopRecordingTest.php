<?php

namespace Tests\Feature\Memory;

use App\Events\AlertTriggered;
use App\Events\MachineOffline;
use App\Events\OperatorAssigned;
use App\Events\OperatorUnassigned;
use App\Models\AIRecommendation;
use App\Models\AiRecommendationAction;
use App\Models\Alert;
use App\Models\Machine;
use App\Models\MaintenanceRecord;
use App\Models\MemoryOutboxRow;
use App\Models\Operator;
use App\Models\OperatorMachineAssignment;
use App\Models\Team;
use App\Models\User;
use App\Services\Memory\MemoryLoopRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * One test per row of the mapping table in the design: the mine acts
 * through its models and events, and the outbox holds the loop record.
 */
class MemoryLoopRecordingTest extends TestCase
{
    use RefreshDatabase;

    private Team $team;

    private Machine $machine;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-16 12:00:00');
        config()->set('services.memory.url', 'http://memory.test');
        config()->set('services.memory.token', 'memory-token-value');
        $this->user = User::factory()->create();
        $this->team = Team::factory()->create(['user_id' => $this->user->id]);
        $this->machine = Machine::factory()->create(['team_id' => $this->team->id, 'name' => 'ADT-07', 'machine_type' => 'adt', 'status' => 'active']);
        MemoryOutboxRow::query()->delete();
    }

    /** @return array<string, mixed> */
    private function payload(string $stage, int $index = 0): array
    {
        $rows = MemoryOutboxRow::query()->where('stage', $stage)->orderBy('id')->get();
        $this->assertGreaterThan($index, $rows->count(), "expected a '{$stage}' row");

        return $rows[$index]->payload;
    }

    public function test_alert_triggered_acknowledged_and_resolved(): void
    {
        $alert = Alert::factory()->create(['team_id' => $this->team->id, 'machine_id' => $this->machine->id, 'priority' => 'critical', 'status' => 'active', 'title' => 'Engine temperature high', 'triggered_at' => now()->subMinutes(30), 'source_triggered_at' => now()->subMinutes(45)]);

        event(new AlertTriggered($alert));
        $event = $this->payload('event');
        $this->assertSame(['equipment', (string) $this->machine->id, 'ADT-07', 'dot-mines:alert:critical', 'dot-mines:alert:'.$alert->id], [$event['subject_type'], $event['subject_id'], $event['subject_label'], $event['signature'], $event['loop_id']]);
        $this->assertSame('2026-10-16T11:15:00+00:00', $event['occurred_at']);

        $alert->acknowledge($this->user->id);
        $ack = $this->payload('action');
        $this->assertSame(['alert.acknowledge', 'dot-mines', 'succeeded', $this->user->id], [$ack['action_kind'], $ack['executor_platform'], $ack['execution_status'], $ack['user_id']]);

        $alert->resolve($this->user->id);
        $this->assertSame('alert.resolve', $this->payload('action', 1)['action_kind']);
        $outcome = $this->payload('outcome');
        $this->assertSame(['improved', 'alert.time_to_clear_minutes', 45], [$outcome['verdict'], $outcome['measure'], $outcome['value_after']]);

        $alert->resolve($this->user->id);
        $this->assertSame(1, MemoryOutboxRow::query()->where('stage', 'outcome')->count(), 'a repeated save never duplicates');
    }

    public function test_machine_offline_and_back_online(): void
    {
        event(new MachineOffline($this->machine, 'No connectivity'));
        $event = $this->payload('event');
        $this->assertSame(['dot-mines:machine:offline', 'No connectivity'], [$event['signature'], $event['detail']['reason']]);

        Carbon::setTestNow('2026-10-16 12:25:00');
        app(MemoryLoopRecorder::class)->machineOnline($this->machine, 'active');
        $outcome = $this->payload('outcome');
        $this->assertSame(['improved', 'machine.minutes_offline', 25, 'dot-mines:machine:'.$this->machine->id], [$outcome['verdict'], $outcome['measure'], $outcome['value_after'], $outcome['loop_id']]);
    }

    public function test_operator_assigned_with_override_and_unassigned(): void
    {
        $operator = Operator::factory()->create(['team_id' => $this->team->id, 'first_name' => 'Operator', 'last_name' => 'One']);
        $assignment = OperatorMachineAssignment::create(['team_id' => $this->team->id, 'operator_id' => $operator->id, 'machine_id' => $this->machine->id, 'shift' => 'day', 'assigned_at' => now(), 'assigned_by' => $this->user->id, 'was_override' => true, 'override_reason' => 'Medical renewal booked', 'overridden_failures' => ['Medical expired']]);

        event(new OperatorAssigned($assignment));
        $action = $this->payload('action');
        $this->assertSame(['operator.assign', 'approved', 'succeeded', 'Operator One', ['Medical expired']], [$action['action_kind'], $action['approval_status'], $action['execution_status'], $action['detail']['operator'], $action['detail']['overridden_failures']]);

        $assignment->forceFill(['unassigned_at' => now()->addHour(), 'unassigned_by' => $this->user->id, 'reason' => 'Shift ended'])->save();
        event(new OperatorUnassigned($assignment));
        $this->assertSame(['operator.unassign', 'Shift ended'], [$this->payload('action', 1)['action_kind'], $this->payload('action', 1)['detail']['reason']]);
    }

    public function test_maintenance_booked_and_completed(): void
    {
        $record = MaintenanceRecord::factory()->create(['team_id' => $this->team->id, 'machine_id' => $this->machine->id, 'maintenance_type' => 'corrective', 'priority' => 'high', 'title' => 'Replace hydraulic hose', 'status' => 'scheduled']);
        $decision = $this->payload('decision');
        $this->assertSame(['Replace hydraulic hose', 'execute', 0.75, 'dot-mines:maintenance:corrective', (string) $this->machine->id], [$decision['recommendation'], $decision['autonomy_level'], $decision['risk'], $decision['signature'], $decision['subject_id']]);
        $this->assertSame('maintenance.book', $this->payload('action')['action_kind']);

        $record->forceFill(['status' => 'completed', 'completed_at' => now(), 'completed_by' => $this->user->id, 'labor_hours' => 3.5, 'machine_operational' => true])->save();
        $outcome = $this->payload('outcome');
        $this->assertSame(['improved', 'maintenance.labor_hours', 3.5, $this->user->id], [$outcome['verdict'], $outcome['measure'], $outcome['value_after'], $outcome['user_id']]);
    }

    public function test_recommendation_raised_implemented_and_rejected(): void
    {
        $recommendation = AIRecommendation::factory()->create(['team_id' => $this->team->id, 'related_machine_id' => $this->machine->id, 'category' => 'maintenance', 'priority' => 'high', 'title' => 'Service ADT-07 early', 'status' => 'pending']);
        $decision = $this->payload('decision');
        $this->assertSame(['recommend', true, 0.75, 'dot-mines:recommendation:maintenance', 'equipment'], [$decision['autonomy_level'], $decision['requires_approval'], $decision['confidence'], $decision['signature'], $decision['subject_type']]);

        AiRecommendationAction::create(['team_id' => $this->team->id, 'ai_recommendation_id' => $recommendation->id, 'recommendation_hash' => 'h', 'recommendation' => ['title' => $recommendation->title], 'status' => 'implemented', 'actioned_by' => $this->user->id, 'actioned_at' => now()]);
        $implement = $this->payload('action');
        $this->assertSame(['recommendation.implement', 'approved', $this->user->id], [$implement['action_kind'], $implement['approval_status'], $implement['user_id']]);

        AiRecommendationAction::create(['team_id' => $this->team->id, 'ai_recommendation_id' => $recommendation->id, 'recommendation_hash' => 'h2', 'recommendation' => ['title' => $recommendation->title], 'status' => 'rejected', 'actioned_by' => $this->user->id, 'actioned_at' => now(), 'reject_reason' => 'Already serviced']);
        $reject = $this->payload('action', 1);
        $this->assertSame(['recommendation.reject', 'rejected', 'Already serviced'], [$reject['action_kind'], $reject['approval_status'], $reject['detail']['reject_reason']]);
    }

    public function test_nothing_is_recorded_without_configuration(): void
    {
        config()->set('services.memory.token', null);
        $alert = Alert::factory()->create(['team_id' => $this->team->id, 'machine_id' => $this->machine->id, 'status' => 'active']);
        event(new AlertTriggered($alert));
        $alert->resolve($this->user->id);

        $this->assertSame(0, MemoryOutboxRow::query()->count());
    }
}
