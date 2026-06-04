<?php

namespace App\Models;

use App\Traits\HasTeamFilters;
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
 * @property \Carbon\Carbon $triggered_at
 * @property \Carbon\Carbon|null $acknowledged_at
 * @property int|null $acknowledged_by
 * @property \Carbon\Carbon|null $resolved_at
 * @property int|null $resolved_by
 * @property string|null $notes
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
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
    /** @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /** @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\Machine, $this> */
    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }

    /** @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\MaintenanceSchedule, $this> */
    public function maintenanceSchedule(): BelongsTo
    {
        return $this->belongsTo(MaintenanceSchedule::class);
    }

    /** @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\User, $this> */
    public function acknowledgedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }

    /** @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\User, $this> */
    public function resolvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    /**
     * Scopes
     */
    public function scopeActive(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('status', 'active');
    }

    public function scopeCritical(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('severity', 'critical');
    }

    public function scopeUnacknowledged(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->whereNull('acknowledged_at');
    }

    public function scopeUnresolved(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->whereNull('resolved_at');
    }

    public function scopeAlertType(\Illuminate\Database\Eloquent\Builder $query, string $type): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('alert_type', $type);
    }

    public function scopeSeverity(\Illuminate\Database\Eloquent\Builder $query, string $severity): \Illuminate\Database\Eloquent\Builder
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
