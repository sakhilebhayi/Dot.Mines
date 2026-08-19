<?php

namespace App\Models;

use App\Traits\HasTeamFilters;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * FuelAlert Model
 *
 * @property int $id
 * @property int $team_id
 * @property int|null $fuel_tank_id
 * @property int|null $machine_id
 * @property string $alert_type
 * @property string $title
 * @property string $message
 * @property string $severity
 * @property string $status
 * @property Carbon $triggered_at
 * @property Carbon|null $acknowledged_at
 * @property Carbon|null $resolved_at
 * @property int|null $acknowledged_by
 * @property int|null $resolved_by
 * @property array|null $metadata
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder|FuelAlert where(string $column, mixed $operator = null, mixed $value = null)
 * @method static \Illuminate\Database\Eloquent\Builder|FuelAlert whereIn(string $column, array $values)
 * @method static \Illuminate\Database\Eloquent\Builder|FuelAlert orderBy(string $column, string $direction = 'asc')
 * @method static FuelAlert|null find(mixed $id, array $columns = ['*'])
 * @method static FuelAlert findOrFail(mixed $id, array $columns = ['*'])
 * @method static \Illuminate\Database\Eloquent\Collection all(array $columns = ['*'])
 */
class FuelAlert extends Model
{
    use HasFactory, HasTeamFilters;

    protected $fillable = [
        'team_id',
        'fuel_tank_id',
        'machine_id',
        'alert_type',
        'title',
        'message',
        'severity',
        'status',
        'triggered_at',
        'acknowledged_at',
        'resolved_at',
        'acknowledged_by',
        'resolved_by',
        'metadata',
    ];

    protected $casts = [
        'triggered_at' => 'datetime',
        'acknowledged_at' => 'datetime',
        'resolved_at' => 'datetime',
        'metadata' => 'json',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function fuelTank(): BelongsTo
    {
        return $this->belongsTo(FuelTank::class);
    }

    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class);
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
     * Scope for active alerts
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope for critical alerts
     */
    public function scopeCritical($query)
    {
        return $query->where('severity', 'critical');
    }

    /**
     * Scope for unacknowledged alerts
     */
    public function scopeUnacknowledged($query)
    {
        return $query->whereNull('acknowledged_at');
    }
}
