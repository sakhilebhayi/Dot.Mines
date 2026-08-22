<?php

namespace App\Models;

use App\Traits\HasTeamFilters;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
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
 * @property array<string, mixed>|null $metadata
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class FuelAlert extends Model
{
    use HasTeamFilters;

    /** @var list<string> */
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

    /** @var array<string, string> */
    protected $casts = [
        'triggered_at' => 'datetime',
        'acknowledged_at' => 'datetime',
        'resolved_at' => 'datetime',
        'metadata' => 'json',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /** @return BelongsTo<FuelTank, $this> */
    public function fuelTank(): BelongsTo
    {
        return $this->belongsTo(FuelTank::class);
    }

    /** @return BelongsTo<Machine, $this> */
    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class);
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
     * Scope for active alerts
     */
    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope for critical alerts
     */
    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeCritical($query)
    {
        return $query->where('severity', 'critical');
    }

    /**
     * Scope for unacknowledged alerts
     */
    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeUnacknowledged($query)
    {
        $query->whereNull('acknowledged_at');

        return $query;
    }
}
