<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Machine;
use App\Models\MachineMetric;
use App\Models\ProductionLossEvent;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Production Loss Accountability: turns lost machine time into classified,
 * auditable operational information.
 *
 * Telemetry -> machine state -> production activity -> loss detection ->
 * potential event -> human classification -> confirmed loss -> impact.
 *
 * Detection NEVER auto-confirms a loss: a detected event starts as
 * pending_classification ("potential loss requires review") because the
 * available data cannot distinguish a breakdown from a planned shutdown.
 */
class ProductionLossService
{
    /**
     * Minimum telemetry window (hours) before silence is considered a
     * detectable loss candidate, and the operating-delta ceiling below
     * which the machine is judged "available but not working".
     */
    private const MIN_WINDOW_HOURS = 4.0;

    private const MAX_OPERATING_DELTA = 0.5;

    /**
     * Scan one day's telemetry for a machine and create a potential loss
     * event when the machine was reporting (connected, status active) but
     * its engine-hours meter barely moved. Idempotent: never duplicates an
     * event overlapping the same window.
     */
    public function detectForDay(Machine $machine, CarbonInterface $day): ?ProductionLossEvent
    {
        if ($machine->status !== 'active') {
            return null; // Idle/maintenance machines are not "unexpectedly" unproductive.
        }

        $readings = MachineMetric::query()
            ->where('machine_id', $machine->id)
            ->whereBetween('recorded_at', [$day->copy()->startOfDay(), $day->copy()->endOfDay()])
            ->orderBy('recorded_at')
            ->get();

        $first = $readings->first();
        $last = $readings->last();

        if ($readings->count() < 2 || $first === null || $last === null) {
            return null; // No telemetry window -- nothing reliable to detect.
        }

        $windowStart = $first->recorded_at ?? $first->created_at;
        $windowEnd = $last->recorded_at ?? $last->created_at;
        $windowHours = $windowStart->diffInMinutes($windowEnd) / 60.0;

        if ($windowHours < self::MIN_WINDOW_HOURS) {
            return null;
        }

        $meter = $readings->pluck('operating_hours')
            ->filter(fn ($value) => $value !== null)
            ->map(fn ($value) => (float) $value);

        if ($meter->count() < 2) {
            return null;
        }

        $operatingDelta = max(0.0, $meter->max() - $meter->min());

        if ($operatingDelta > self::MAX_OPERATING_DELTA) {
            return null; // The machine genuinely worked.
        }

        $overlapping = ProductionLossEvent::query()->where('machine_id', $machine->id)
            ->where('started_at', '<', $windowEnd)
            ->where('ended_at', '>', $windowStart)
            ->exists();

        if ($overlapping) {
            return null;
        }

        $lostHours = round($windowHours - $operatingDelta, 2);

        $event = new ProductionLossEvent([
            'team_id' => $machine->team_id,
            'machine_id' => $machine->id,
            'started_at' => $windowStart,
            'ended_at' => $windowEnd,
            'lost_hours' => $lostHours,
            'source' => ProductionLossEvent::SOURCE_SYSTEM,
            'status' => ProductionLossEvent::STATUS_PENDING,
            'detection_basis' => sprintf(
                'Machine reported telemetry for %.1f h while active, but the engine-hours meter advanced only %.2f h — available but not producing.',
                $windowHours,
                $operatingDelta
            ),
        ]);
        $event->recordAudit('detected', null, ['basis' => 'telemetry']);
        $event->save();

        return $event;
    }

    /**
     * Record a user-entered loss. If the window overlaps an unclassified
     * system detection, that event is CLASSIFIED with the user's reason
     * instead of creating a duplicate -- lost hours are never counted twice.
     *
     * @param  array{started_at: string, ended_at: string, category: string, reason: string, notes?: string|null}  $data
     */
    public function recordManualLoss(Machine $machine, User $user, array $data): ProductionLossEvent
    {
        $start = Carbon::parse($data['started_at']);
        $end = Carbon::parse($data['ended_at']);

        if ($end->lessThanOrEqualTo($start)) {
            throw ValidationException::withMessages(['ended_at' => 'End time must be after the start time.']);
        }

        if (! array_key_exists($data['category'], ProductionLossEvent::REASONS)
            || ! in_array($data['reason'], ProductionLossEvent::REASONS[$data['category']], true)) {
            throw ValidationException::withMessages(['reason' => 'Select a valid loss reason.']);
        }

        $overlapping = ProductionLossEvent::query()->where('machine_id', $machine->id)
            ->where('started_at', '<', $end)
            ->where('ended_at', '>', $start)
            ->get();

        // An overlapping unclassified system detection is the SAME loss the
        // user is describing: classify it rather than double-count.
        $pendingSystem = $overlapping->first(
            fn (ProductionLossEvent $event): bool => $event->source === ProductionLossEvent::SOURCE_SYSTEM
                && $event->status === ProductionLossEvent::STATUS_PENDING
        );

        if ($pendingSystem) {
            return $this->classify($pendingSystem, $user, $data['category'], $data['reason'], $data['notes'] ?? null);
        }

        if ($overlapping->isNotEmpty()) {
            throw ValidationException::withMessages([
                'started_at' => 'This window overlaps an already-recorded loss event. Edit or dispute the existing event instead of double-recording the hours.',
            ]);
        }

        $event = new ProductionLossEvent([
            'team_id' => $machine->team_id,
            'machine_id' => $machine->id,
            'started_at' => $start,
            'ended_at' => $end,
            'lost_hours' => round($start->diffInMinutes($end) / 60.0, 2),
            'source' => ProductionLossEvent::SOURCE_USER,
            'status' => ProductionLossEvent::STATUS_CONFIRMED,
            'category' => $data['category'],
            'reason' => $data['reason'],
            'notes' => $data['notes'] ?? null,
            'created_by' => $user->id,
            'classified_by' => $user->id,
            'classified_at' => now(),
        ]);
        $event->recordAudit('recorded', $user->id, [
            'category' => $data['category'],
            'reason' => $data['reason'],
        ]);
        $event->save();

        $this->logActivity($machine, $user, 'production_loss_recorded',
            "Recorded {$event->lost_hours}h production loss on {$machine->name}: {$event->reasonLabel()}");

        return $event;
    }

    /**
     * Classify a detected event with a human verdict -- the transition from
     * "potential loss" to accounted operational information.
     */
    public function classify(ProductionLossEvent $event, User $user, string $category, string $reason, ?string $notes = null): ProductionLossEvent
    {
        if (! array_key_exists($category, ProductionLossEvent::REASONS)
            || ! in_array($reason, ProductionLossEvent::REASONS[$category], true)) {
            throw ValidationException::withMessages(['reason' => 'Select a valid loss reason.']);
        }

        $event->recordAudit('classified', $user->id, [
            'from_status' => $event->status,
            'category' => $category,
            'reason' => $reason,
        ]);

        $event->fill([
            'status' => ProductionLossEvent::STATUS_CONFIRMED,
            'category' => $category,
            'reason' => $reason,
            'notes' => $notes !== null && $notes !== '' ? $notes : $event->notes,
            'classified_by' => $user->id,
            'classified_at' => now(),
        ]);
        $event->save();

        $this->logActivity($event->machine, $user, 'production_loss_classified',
            "Classified {$event->lost_hours}h detected loss on {$event->machine?->name}: {$event->reasonLabel()}");

        return $event;
    }

    /**
     * Summary tiles for the machine detail panel. Only counted events
     * (user-recorded or human-confirmed) contribute to totals; unreviewed
     * detections are reported separately.
     *
     * @return array{total_hours: float, today_hours: float, week_hours: float, month_hours: float, event_count: int, primary_reason: string|null, latest_event_at: Carbon|null, pending_review: int}
     */
    public function summaryForMachine(Machine $machine): array
    {
        $counted = ProductionLossEvent::counted()
            ->where('machine_id', $machine->id)
            ->get();

        $primary = $counted->whereNotNull('reason')
            ->groupBy('reason')
            ->sortByDesc(fn (Collection $group) => $group->sum('lost_hours'))
            ->keys()
            ->first();

        return [
            'total_hours' => round($counted->sum('lost_hours'), 2),
            'today_hours' => round($counted->filter(fn ($e) => $e->started_at->isToday())->sum('lost_hours'), 2),
            'week_hours' => round($counted->filter(fn ($e) => $e->started_at->greaterThanOrEqualTo(now()->startOfWeek()))->sum('lost_hours'), 2),
            'month_hours' => round($counted->filter(fn ($e) => $e->started_at->greaterThanOrEqualTo(now()->startOfMonth()))->sum('lost_hours'), 2),
            'event_count' => $counted->count(),
            'primary_reason' => $primary !== null ? ucfirst(str_replace('_', ' ', (string) $primary)) : null,
            'latest_event_at' => $counted->max('started_at'),
            'pending_review' => ProductionLossEvent::pendingReview()->where('machine_id', $machine->id)->count(),
        ];
    }

    /**
     * Estimated production opportunity lost, from the MACHINE'S OWN recent
     * rate (last 14 days of production tonnes over the same period's
     * engine-hour delta). Returns null when the data cannot support an
     * estimate -- never fabricates one.
     *
     * @return array{rate_per_hour: float, estimated_loss: float, unit: string, basis_days: int}|null
     */
    public function estimateImpact(Machine $machine, float $lostHours): ?array
    {
        if ($lostHours <= 0) {
            return null;
        }

        $since = now()->subDays(14);

        $tonnes = (float) $machine->productionRecords()
            ->where('record_date', '>=', $since->toDateString())
            ->sum('quantity_produced');

        $meter = $machine->metrics()
            ->where('recorded_at', '>=', $since)
            ->get()
            ->pluck('operating_hours')
            ->filter(fn ($value) => $value !== null)
            ->map(fn ($value) => (float) $value);

        $hoursWorked = $meter->count() >= 2 ? max(0.0, $meter->max() - $meter->min()) : 0.0;

        if ($tonnes <= 0 || $hoursWorked < 1.0) {
            return null;
        }

        $rate = $tonnes / $hoursWorked;

        return [
            'rate_per_hour' => round($rate, 1),
            'estimated_loss' => round($rate * $lostHours, 1),
            'unit' => 'tonnes',
            'basis_days' => 14,
        ];
    }

    private function logActivity(?Machine $machine, User $user, string $action, string $description): void
    {
        if ($machine === null) {
            return;
        }

        ActivityLog::create([
            'team_id' => $machine->team_id,
            'user_id' => $user->id,
            'action' => $action,
            'description' => $description,
        ]);
    }
}
