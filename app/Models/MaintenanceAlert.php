<?php

namespace App\Models;

use App\Traits\HasTeamFilters;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * MaintenanceAlert Model
 *
 * @property int $id
 * @property int $team_id
 * @property int $machine_id
 * @property int|null $maintenance_schedule_id
 * @property string $alert_type
 * @property string $title
 * @property string $message
 * @property string $severity
 * @property string $status
 * @property Carbon $triggered_at
 * @property Carbon|null $acknowledged_at
 * @property int|null $acknowledged_by
 * @property Carbon|null $resolved_at
 * @property int|null $resolved_by
 * @property string|null $notes
 * @property-read float $age_hours
 * @property-read bool $is_stale
 * @property-read int $priority_score
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class MaintenanceAlert extends Model
{
    /** @use HasFactory<Factory<static>> */
    use HasFactory, HasTeamFilters;

    protected $fillable = [
        'team_id',
        'machine_id',
        'maintenance_schedule_id',
        'alert_type',
        'title',
        'message',
        'severity',
        'status',
        'triggered_at',
        'acknowledged_at',
        'acknowledged_by',
        'resolved_at',
        'resolved_by',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'triggered_at' => 'datetime',
            'acknowledged_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    /**
     * Relationships
     */
    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /** @return BelongsTo<Machine, $this> */
    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }

    /** @return BelongsTo<MaintenanceSchedule, $this> */
    public function maintenanceSchedule(): BelongsTo
    {
        return $this->belongsTo(MaintenanceSchedule::class);
    }

    /** @return BelongsTo<User, $this> */
    public function acknowledgedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }

    /** @return BelongsTo<User, $this> */
    public function resolvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    /**
     * Scopes
     */
    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeCritical(Builder $query): Builder
    {
        return $query->where('severity', 'critical');
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeUnacknowledged(Builder $query): Builder
    {
        $query->whereNull('acknowledged_at');

        return $query;
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeUnresolved(Builder $query): Builder
    {
        $query->whereNull('resolved_at');

        return $query;
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeAlertType(Builder $query, string $type): Builder
    {
        return $query->where('alert_type', $type);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeSeverity(Builder $query, string $severity): Builder
    {
        return $query->where('severity', $severity);
    }

    /**
     * Acknowledge the alert
     */
    public function acknowledge(User $user): void
    {
        $this->update([
            'acknowledged_at' => now(),
            'acknowledged_by' => $user->id,
        ]);
    }

    /**
     * Resolve the alert
     */
    public function resolve(User $user, ?string $notes = null): void
    {
        $this->update([
            'status' => 'resolved',
            'resolved_at' => now(),
            'resolved_by' => $user->id,
            'notes' => $notes ?? $this->notes,
        ]);
    }

    /**
     * Get age of alert in hours
     */
    public function getAgeHoursAttribute(): float
    {
        return $this->triggered_at->diffInHours(now());
    }

    /**
     * Check if alert is stale (unacknowledged for > 24 hours)
     */
    public function getIsStaleAttribute(): bool
    {
        return ! $this->acknowledged_at && $this->age_hours > 24;
    }

    /**
     * Get priority score (for sorting)
     */
    public function getPriorityScoreAttribute(): int
    {
        $score = 0;

        // Severity weight
        $score += match ($this->severity) {
            'critical' => 100,
            'warning' => 50,
            'info' => 10,
            default => 0,
        };

        // Age weight (older = higher priority)
        $score += (int) min($this->age_hours, 48);

        // Unacknowledged weight
        if (! $this->acknowledged_at) {
            $score += 30;
        }

        return $score;
    }
}
