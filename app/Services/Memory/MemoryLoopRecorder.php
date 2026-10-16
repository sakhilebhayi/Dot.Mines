<?php

namespace App\Services\Memory;

use App\Models\AIRecommendation;
use App\Models\AiRecommendationAction;
use App\Models\Alert;
use App\Models\Machine;
use App\Models\MaintenanceRecord;
use App\Models\MemoryOutboxRow;
use App\Models\OperatorMachineAssignment;
use App\Support\ApiPayload;
use App\Support\CurrentUser;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Builds intelligence-loop records from what Dot.Mines already did and
 * writes them to the outbox (spec §3). Subjects share Dot.Central's
 * vocabulary: a machine is `equipment` with the machine id. Inert without
 * a configured token; never throws towards the caller.
 */
class MemoryLoopRecorder
{
    private const RISK = ['critical' => 0.9, 'high' => 0.75, 'medium' => 0.5, 'low' => 0.25];

    private const CONFIDENCE = ['critical' => 0.9, 'high' => 0.75, 'medium' => 0.6, 'low' => 0.4];

    /** @param array<string, mixed> $payload */
    public function queue(int $teamId, string $stage, array $payload): ?MemoryOutboxRow
    {
        if (! MemoryClientFactory::configured()) {
            return null;
        }

        $eventId = $this->str($payload['event_id'] ?? null);

        if ($eventId === '') {
            return null;
        }

        try {
            $existing = MemoryOutboxRow::query()->where('event_id', $eventId)->first();

            return $existing ?? MemoryOutboxRow::query()->create(['team_id' => $teamId, 'stage' => $stage, 'event_id' => $eventId, 'payload' => $payload]);
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    public function alertTriggered(Alert $alert): void
    {
        $this->queue($alert->team_id, 'event', $this->base('alert:'.$alert->id, 'triggered', $this->alertSubject($alert), 'dot-mines:alert:'.$alert->priority, $alert->source_triggered_at ?? $alert->triggered_at, $alert->team_id, null, [
            'title' => $alert->title, 'type' => $alert->type, 'priority' => $alert->priority, 'description' => $alert->description,
        ]));
    }

    public function alertAcknowledged(Alert $alert): void
    {
        $this->queue($alert->team_id, 'action', $this->base('alert:'.$alert->id, 'acknowledge', $this->alertSubject($alert), 'dot-mines:alert:'.$alert->priority, $alert->acknowledged_at ?? now(), $alert->team_id, $alert->acknowledged_by, ['title' => $alert->title]) + [
            'action_kind' => 'alert.acknowledge', 'executor_platform' => 'dot-mines', 'approval_status' => 'not_required', 'execution_status' => 'succeeded',
        ]);
    }

    public function alertResolved(Alert $alert): void
    {
        $resolvedAt = $alert->resolved_at ?? now();
        $started = $alert->source_triggered_at ?? $alert->triggered_at;
        $minutes = max(0, (int) round($started->diffInMinutes($resolvedAt)));
        $subject = $this->alertSubject($alert);
        $signature = 'dot-mines:alert:'.$alert->priority;

        $this->queue($alert->team_id, 'action', $this->base('alert:'.$alert->id, 'resolve', $subject, $signature, $resolvedAt, $alert->team_id, $alert->resolved_by, ['title' => $alert->title]) + [
            'action_kind' => 'alert.resolve', 'executor_platform' => 'dot-mines', 'approval_status' => 'not_required', 'execution_status' => 'succeeded',
        ]);
        $this->queue($alert->team_id, 'outcome', $this->base('alert:'.$alert->id, 'cleared', $subject, $signature, $resolvedAt, $alert->team_id, $alert->resolved_by, ['title' => $alert->title]) + [
            'verdict' => 'improved', 'measure' => 'alert.time_to_clear_minutes', 'value_before' => null, 'value_after' => $minutes,
        ]);
    }

    public function machineOffline(Machine $machine, ?string $reason): void
    {
        $this->queue($machine->team_id, 'event', $this->base('machine:'.$machine->id, 'offline:'.now()->getTimestamp(), $this->machineSubject($machine), 'dot-mines:machine:offline', now(), $machine->team_id, null, [
            'reason' => $reason, 'machine_type' => $machine->machine_type,
        ]));
    }

    public function machineOnline(Machine $machine, string $newStatus): void
    {
        $last = MemoryOutboxRow::query()->where('team_id', $machine->team_id)->where('event_id', 'like', 'dot-mines:machine:'.$machine->id.':offline:%')->orderByDesc('id')->first();
        $occurred = $last === null ? null : ApiPayload::iso($last->payload['occurred_at'] ?? null);
        $offlineAt = $occurred === null ? null : Carbon::parse($occurred);
        $minutes = $offlineAt === null ? null : max(0, (int) round($offlineAt->diffInMinutes(now())));

        $this->queue($machine->team_id, 'outcome', $this->base('machine:'.$machine->id, 'online:'.now()->getTimestamp(), $this->machineSubject($machine), 'dot-mines:machine:offline', now(), $machine->team_id, null, [
            'new_status' => $newStatus,
        ]) + ['verdict' => 'improved', 'measure' => 'machine.minutes_offline', 'value_before' => null, 'value_after' => $minutes]);
    }

    public function operatorAssigned(OperatorMachineAssignment $assignment): void
    {
        $machine = $assignment->machine;
        if (! $machine instanceof Machine) {
            return;
        }
        $operator = $assignment->operator;
        $failures = $assignment->overridden_failures;

        $this->queue($assignment->team_id, 'action', $this->base('assignment:'.$assignment->id, 'assigned', $this->machineSubject($machine), 'dot-mines:operator:assign', $assignment->assigned_at, $assignment->team_id, $assignment->assigned_by, [
            'operator_id' => $assignment->operator_id, 'operator' => trim(($operator?->first_name ?? '').' '.($operator?->last_name ?? '')), 'shift' => $assignment->shift, 'override_reason' => $assignment->override_reason, 'overridden_failures' => is_array($failures) ? array_values($failures) : [],
        ]) + [
            'action_kind' => 'operator.assign', 'executor_platform' => 'dot-mines', 'approval_status' => $assignment->was_override ? 'approved' : 'not_required', 'execution_status' => 'succeeded',
        ]);
    }

    public function operatorUnassigned(OperatorMachineAssignment $assignment): void
    {
        $machine = $assignment->machine;
        if (! $machine instanceof Machine) {
            return;
        }

        $this->queue($assignment->team_id, 'action', $this->base('assignment:'.$assignment->id, 'unassigned', $this->machineSubject($machine), 'dot-mines:operator:assign', $assignment->unassigned_at ?? now(), $assignment->team_id, $assignment->unassigned_by, [
            'operator_id' => $assignment->operator_id, 'reason' => $assignment->reason,
        ]) + [
            'action_kind' => 'operator.unassign', 'executor_platform' => 'dot-mines', 'approval_status' => 'not_required', 'execution_status' => 'succeeded',
        ]);
    }

    public function maintenanceBooked(MaintenanceRecord $record): void
    {
        $subject = $this->maintenanceSubject($record);
        $signature = 'dot-mines:maintenance:'.$record->maintenance_type;
        $detail = ['title' => $record->title, 'maintenance_type' => $record->maintenance_type, 'priority' => $record->priority, 'scheduled_date' => $record->scheduled_date->toIso8601String(), 'work_order_number' => $record->work_order_number];
        $actor = CurrentUser::get()?->id;

        $this->queue($record->team_id, 'decision', $this->base('maintenance:'.$record->id, 'decision', $subject, $signature, $record->created_at, $record->team_id, $actor, $detail) + [
            'recommendation' => $record->title, 'confidence' => 1.0, 'risk' => self::RISK[$record->priority] ?? 0.5, 'autonomy_level' => 'execute', 'requires_approval' => false,
        ]);
        $this->queue($record->team_id, 'action', $this->base('maintenance:'.$record->id, 'book', $subject, $signature, $record->created_at, $record->team_id, $actor, $detail) + [
            'action_kind' => 'maintenance.book', 'executor_platform' => 'dot-mines', 'approval_status' => 'not_required', 'execution_status' => 'succeeded',
        ]);
    }

    public function maintenanceCompleted(MaintenanceRecord $record): void
    {
        $hours = $record->labor_hours === null ? null : (float) $record->labor_hours;

        $this->queue($record->team_id, 'outcome', $this->base('maintenance:'.$record->id, 'completed', $this->maintenanceSubject($record), 'dot-mines:maintenance:'.$record->maintenance_type, $record->completed_at ?? now(), $record->team_id, $record->completed_by, [
            'title' => $record->title, 'machine_operational' => $record->machine_operational, 'work_performed' => $record->work_performed, 'total_cost' => $record->total_cost === null ? null : (float) $record->total_cost,
        ]) + [
            'verdict' => $record->machine_operational ? 'improved' : 'inconclusive', 'measure' => 'maintenance.labor_hours', 'value_before' => null, 'value_after' => $hours,
        ]);
    }

    public function recommendationRaised(AIRecommendation $recommendation): void
    {
        $this->queue($recommendation->team_id, 'decision', $this->base('recommendation:'.$recommendation->id, 'raised', $this->recommendationSubject($recommendation), 'dot-mines:recommendation:'.$recommendation->category, $recommendation->created_at ?? now(), $recommendation->team_id, null, [
            'title' => $recommendation->title, 'description' => $recommendation->description, 'category' => $recommendation->category, 'priority' => $recommendation->priority,
        ]) + [
            'recommendation' => $recommendation->title, 'confidence' => self::CONFIDENCE[$recommendation->priority] ?? 0.5, 'risk' => self::RISK[$recommendation->priority] ?? 0.5, 'autonomy_level' => 'recommend', 'requires_approval' => true,
        ]);
    }

    public function recommendationActioned(AiRecommendationAction $action): void
    {
        $status = $this->str($action->getAttribute('status'));
        if (! in_array($status, ['implemented', 'rejected'], true)) {
            return;
        }

        $recommendationId = $this->int($action->getAttribute('ai_recommendation_id'));
        $recommendation = $recommendationId === null ? null : AIRecommendation::query()->withoutGlobalScopes()->find($recommendationId);
        $teamId = $this->int($action->getAttribute('team_id'));
        if ($teamId === null) {
            return;
        }
        $subject = $recommendation instanceof AIRecommendation ? $this->recommendationSubject($recommendation) : ['type' => 'mine', 'id' => (string) $teamId, 'label' => null];
        $occurredAt = $this->at($action->getAttribute('actioned_at'));
        $implemented = $status === 'implemented';

        $this->queue($teamId, 'action', $this->base('recommendation:'.($recommendationId ?? $this->int($action->getAttribute('id')) ?? 0), $status, $subject, 'dot-mines:recommendation:'.($recommendation?->category ?? 'unknown'), $occurredAt, $teamId, $this->int($action->getAttribute('actioned_by')), [
            'title' => $recommendation?->title, 'reject_reason' => $this->str($action->getAttribute('reject_reason')) ?: null,
        ]) + [
            'action_kind' => $implemented ? 'recommendation.implement' : 'recommendation.reject', 'executor_platform' => 'dot-mines', 'approval_status' => $implemented ? 'approved' : 'rejected', 'execution_status' => 'succeeded',
        ]);
    }

    /**
     * @param  array{type:string,id:string,label:?string}  $subject
     * @param  array<string, mixed>  $detail
     * @return array<string, mixed>
     */
    private function base(string $loopKey, string $eventKey, array $subject, string $signature, CarbonInterface $occurredAt, int $teamId, ?int $userId, array $detail): array
    {
        return [
            'loop_id' => 'dot-mines:'.$loopKey,
            'event_id' => 'dot-mines:'.$loopKey.':'.$eventKey,
            'source' => 'dot-mines',
            'subject_type' => $subject['type'],
            'subject_id' => $subject['id'],
            'subject_label' => $subject['label'],
            'team_id' => $teamId,
            'user_id' => $userId,
            'occurred_at' => $occurredAt->toIso8601String(),
            'signature' => $signature,
            'detail' => $detail,
        ];
    }

    /** @return array{type:string,id:string,label:?string} */
    private function machineSubject(Machine $machine): array
    {
        return ['type' => 'equipment', 'id' => (string) $machine->id, 'label' => $machine->name];
    }

    /** @return array{type:string,id:string,label:?string} */
    private function alertSubject(Alert $alert): array
    {
        $machine = $alert->machine;

        return $machine instanceof Machine ? $this->machineSubject($machine) : ['type' => 'mine', 'id' => (string) $alert->team_id, 'label' => null];
    }

    /** @return array{type:string,id:string,label:?string} */
    private function maintenanceSubject(MaintenanceRecord $record): array
    {
        $machine = $record->machine;

        return $machine instanceof Machine ? $this->machineSubject($machine) : ['type' => 'mine', 'id' => (string) $record->team_id, 'label' => null];
    }

    /** @return array{type:string,id:string,label:?string} */
    private function recommendationSubject(AIRecommendation $recommendation): array
    {
        $machine = $recommendation->machine;

        return $machine instanceof Machine ? $this->machineSubject($machine) : ['type' => 'mine', 'id' => (string) $recommendation->team_id, 'label' => null];
    }

    private function at(mixed $value): CarbonInterface
    {
        return $value instanceof CarbonInterface ? $value : now();
    }

    private function str(mixed $value): string
    {
        return is_string($value) ? $value : (is_int($value) ? (string) $value : '');
    }

    private function int(mixed $value): ?int
    {
        return is_int($value) ? $value : (is_numeric($value) ? (int) $value : null);
    }
}
