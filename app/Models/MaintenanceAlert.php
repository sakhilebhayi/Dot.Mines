<?php

namespace App\Models;

use App\Traits\HasTeamFilters;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
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
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder|MaintenanceAlert where(string $column, mixed $operator = null, mixed $value = null)
 * @method static \Illuminate\Database\Eloquent\Builder|MaintenanceAlert whereIn(string $column, array<string|int> $values)
 * @method static \Illuminate\Database\Eloquent\Builder|MaintenanceAlert orderBy(string $column, string $direction = 'asc')
 * @method static MaintenanceAlert|null find(mixed $id, array<string> $columns = ['*'])
 * @method static MaintenanceAlert findOrFail(mixed $id, array<string> $columns = ['*'])
 * @method static \Illuminate\Database\Eloquent\Collection<int,MaintenanceAlert> all(array<string> $columns = ['*'])
 */
class MaintenanceAlert extends Model
{
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

    protected $casts = [
        'triggered_at' => 'datetime',
        'acknowledged_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    /**
     * Relationships
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }

    public function maintenanceSchedule(): BelongsTo
    {
        return $this->belongsTo(MaintenanceSchedule::class);
    }

    public function acknowledgedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }

    public function resolvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    /**
     * Scopes
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopeCritical(Builder $query): Builder
    {
        return $query->where('severity', 'critical');
    }

    public function scopeUnacknowledged(Builder $query): Builder
    {
        return $query->whereNull('acknowledged_at');
    }

    public function scopeUnresolved(Builder $query): Builder
    {
        return $query->whereNull('resolved_at');
    }

    public function scopeAlertType(Builder $query, string $type): Builder
    {
        return $query->where('alert_type', $type);
    }

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
        $score += min($this->age_hours, 48);

        // Unacknowledged weight
        if (! $this->acknowledged_at) {
            $score += 30;
        }

        return $score;
    }
}
