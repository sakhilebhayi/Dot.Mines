<?php

namespace App\Models;

use App\Services\QueryCacheService;
use App\Traits\HasTeamFilters;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * Alert Model
 *
 * Represents system alerts triggered by rules
 * Can be about machines, maintenance, fuel, or custom conditions
 *
 * @property int $id
 * @property int $team_id
 * @property int|null $machine_id
 * @property int|null $mine_area_id
 * @property string $type
 * @property string $title
 * @property string|null $description
 * @property string $priority
 * @property string $status
 * @property \Carbon\Carbon $triggered_at
 * @property \Carbon\Carbon|null $acknowledged_at
 * @property \Carbon\Carbon|null $resolved_at
 * @property int|null $acknowledged_by
 * @property int|null $resolved_by
 * @property array|null $metadata
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder|Alert where(string $column, mixed $operator = null, mixed $value = null)
 * @method static \Illuminate\Database\Eloquent\Builder|Alert whereIn(string $column, array $values)
 * @method static \Illuminate\Database\Eloquent\Builder|Alert orderBy(string $column, string $direction = 'asc')
 * @method static Alert|null find(mixed $id, array $columns = ['*'])
 * @method static Alert findOrFail(mixed $id, array $columns = ['*'])
 * @method static \Illuminate\Database\Eloquent\Collection all(array $columns = ['*'])
 */
class Alert extends Model
{
    use HasFactory, HasTeamFilters;

    protected $fillable = [
        'team_id',
        'machine_id',
        'mine_area_id',
        'type', // engine, fuel, maintenance, geofence, temperature, area, etc.
        'title',
        'description',
        'priority', // critical, high, medium, low
        'status', // active, acknowledged, resolved
        'triggered_at',
        'acknowledged_at',
        'resolved_at',
        'acknowledged_by',
        'resolved_by',
        'metadata', // JSON for rule details
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
            'metadata' => 'json',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        // Invalidate cache when alert is created, updated, or deleted
        static::saved(function (Alert $alert) {
            QueryCacheService::invalidateAlerts($alert->team_id);
        });

        static::deleted(function (Alert $alert) {
            QueryCacheService::invalidateAlerts($alert->team_id);
        });
    }

    /**
     * Get the machine this alert is about
     */
    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }

    /**
     * Get the mine area this alert is about
     */
    public function mineArea(): BelongsTo
    {
        return $this->belongsTo(MineArea::class);
    }

    /**
     * Get the team this alert belongs to
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the user who acknowledged this alert
     */
    public function acknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }

    /**
     * Get the user who resolved this alert
     */
    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    /**
     * Acknowledge this alert
     */
    public function acknowledge(?int $userId = null): bool
    {
        return $this->update([
            'status' => 'acknowledged',
            'acknowledged_at' => now(),
            'acknowledged_by' => $userId ?? Auth::id(),
        ]);
    }

    /**
     * Resolve this alert
     */
    public function resolve(?int $userId = null): bool
    {
        return $this->update([
            'status' => 'resolved',
            'resolved_at' => now(),
            'resolved_by' => $userId ?? Auth::id(),
        ]);
    }

    /**
     * Scope to active alerts
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope to critical alerts
     */
    public function scopeCritical(Builder $query): Builder
    {
        return $query->where('priority', 'critical');
    }
}
