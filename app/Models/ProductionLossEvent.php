<?php

namespace App\Models;

use Carbon\Carbon;
use Database\Factories\ProductionLossEventFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A period of lost productive time for one machine: when it happened, how
 * long, why (structured taxonomy), how it was identified (telemetry vs a
 * person), who accounted for it, and an append-only audit trail.
 *
 * @property int $id
 * @property int $team_id
 * @property int $machine_id
 * @property Carbon $started_at
 * @property Carbon $ended_at
 * @property float $lost_hours
 * @property string $source
 * @property string $status
 * @property string|null $category
 * @property string|null $reason
 * @property string|null $notes
 * @property string|null $detection_basis
 * @property int|null $created_by
 * @property int|null $classified_by
 * @property Carbon|null $classified_at
 * @property array<int, array<string, mixed>>|null $audit_log
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Machine|null $machine
 * @property-read Team|null $team
 * @property-read User|null $creator
 * @property-read User|null $classifier
 */
class ProductionLossEvent extends Model
{
    /** @use HasFactory<ProductionLossEventFactory> */
    use HasFactory;

    public const SOURCE_SYSTEM = 'system';

    public const SOURCE_USER = 'user';

    public const STATUS_PENDING = 'pending_classification';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_DISPUTED = 'disputed';

    public const STATUS_RESOLVED = 'resolved';

    /**
     * Structured reason taxonomy (category => reasons). "other" is always
     * available so users are never forced into an inaccurate label.
     *
     * @var array<string, list<string>>
     */
    public const REASONS = [
        'mechanical' => ['breakdown', 'hydraulic', 'engine', 'transmission', 'electrical', 'tyre', 'other'],
        'operational' => ['waiting_for_loading', 'waiting_for_dumping', 'waiting_for_support_equipment', 'operator_unavailable', 'congestion', 'site_access', 'refuelling', 'other'],
        'planned' => ['scheduled_maintenance', 'shift_change', 'planned_shutdown', 'inspection', 'other'],
        'environmental' => ['weather', 'rain', 'dust', 'ground_conditions', 'other'],
        'safety' => ['safety_stoppage', 'blast_clearance', 'incident_investigation', 'other'],
        'other' => ['unknown', 'other'],
    ];

    /** @var list<string> */
    protected $fillable = [
        'team_id',
        'machine_id',
        'started_at',
        'ended_at',
        'lost_hours',
        'source',
        'status',
        'category',
        'reason',
        'notes',
        'detection_basis',
        'created_by',
        'classified_by',
        'classified_at',
        'audit_log',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'classified_at' => 'datetime',
            'lost_hours' => 'float',
            'audit_log' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Machine, $this>
     */
    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }

    /**
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function classifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'classified_by');
    }

    /**
     * Append an entry to the immutable audit log -- the only sanctioned way
     * to record a change to loss history.
     *
     * @param  array<string, mixed>  $changes
     */
    public function recordAudit(string $action, ?int $userId, array $changes = []): void
    {
        $log = $this->audit_log ?? [];
        $log[] = [
            'at' => now()->toIso8601String(),
            'by' => $userId,
            'action' => $action,
            'changes' => $changes,
        ];
        $this->audit_log = $log;
    }

    /**
     * Whether this event's window overlaps [$start, $end).
     */
    public function overlaps(Carbon $start, Carbon $end): bool
    {
        return $this->started_at < $end && $this->ended_at > $start;
    }

    /**
     * Human-readable reason ("Waiting for loading" from waiting_for_loading).
     */
    public function reasonLabel(): string
    {
        if ($this->reason === null) {
            return 'Unclassified';
        }

        return ucfirst(str_replace('_', ' ', $this->reason));
    }

    /**
     * Query of events that count toward lost-hours totals: everything except
     * unclassified system detections (those are "potential losses requiring
     * review", surfaced separately, never silently added to the totals).
     *
     * @return Builder<ProductionLossEvent>
     */
    public static function counted(): Builder
    {
        return ProductionLossEvent::query()->where(function (Builder $inner) {
            $inner->where('source', self::SOURCE_USER)
                ->orWhere('status', self::STATUS_CONFIRMED)
                ->orWhere('status', self::STATUS_RESOLVED);
        });
    }

    /**
     * Query of telemetry-detected events still awaiting a human verdict.
     *
     * @return Builder<ProductionLossEvent>
     */
    public static function pendingReview(): Builder
    {
        return ProductionLossEvent::query()->where('source', self::SOURCE_SYSTEM)
            ->where('status', self::STATUS_PENDING);
    }
}
