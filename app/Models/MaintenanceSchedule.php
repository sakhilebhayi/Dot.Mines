<?php

namespace App\Models;

use App\Traits\HasTeamFilters;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * MaintenanceSchedule Model
 *
 * @property int $id
 * @property int $team_id
 * @property int $machine_id
 * @property string $maintenance_type
 * @property string $title
 * @property string|null $description
 * @property string $schedule_type
 * @property int|null $interval_hours
 * @property int|null $interval_km
 * @property int|null $interval_days
 * @property int|null $last_service_hours
 * @property int|null $last_service_km
 * @property string|Carbon|null $last_service_date
 * @property int|null $next_service_hours
 * @property int|null $next_service_km
 * @property string|Carbon|null $next_service_date
 * @property string $priority
 * @property string $status
 * @property string|float|null $estimated_cost
 * @property float|null $estimated_duration_hours
 * @property array<string, mixed>|null $required_parts
 * @property array<string, mixed>|null $required_tools
 * @property bool $auto_generate_work_order
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class MaintenanceSchedule extends Model
{
    use HasTeamFilters;

    /** @var list<string> */
    protected $fillable = [
        'team_id',
        'machine_id',
        'maintenance_type',
        'title',
        'description',
        'schedule_type',
        'interval_hours',
        'interval_km',
        'interval_days',
        'last_service_hours',
        'last_service_km',
        'last_service_date',
        'next_service_hours',
        'next_service_km',
        'next_service_date',
        'priority',
        'status',
        'estimated_cost',
        'estimated_duration_hours',
        'required_parts',
        'required_tools',
        'auto_generate_work_order',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'last_service_date' => 'date',
        'next_service_date' => 'date',
        'estimated_cost' => 'decimal:2',
        'required_parts' => 'json',
        'required_tools' => 'json',
        'auto_generate_work_order' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

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

    /** @return HasMany<MaintenanceRecord, $this> */
    public function maintenanceRecords(): HasMany
    {
        return $this->hasMany(MaintenanceRecord::class);
    }

    /**
     * Check if service is due
     */
    public function isDue(Machine $machine): bool
    {
        return match ($this->schedule_type) {
            'hours' => $machine->operating_hours >= $this->next_service_hours,
            'kilometers' => $machine->odometer >= $this->next_service_km,
            'calendar' => now()->gte($this->next_service_date),
            default => false,
        };
    }

    /**
     * Check if service is overdue
     */
    public function isOverdue(Machine $machine): bool
    {
        return match ($this->schedule_type) {
            'hours' => $machine->operating_hours > ($this->next_service_hours + ($this->interval_hours * 0.1)),
            'kilometers' => $machine->odometer > ($this->next_service_km + ($this->interval_km * 0.1)),
            'calendar' => now()->gt($this->next_service_date->addDays(7)),
            default => false,
        };
    }

    /**
     * Scope for due schedules
     */
    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeDue($query)
    {
        return $query->where('status', 'due');
    }

    /**
     * Scope for overdue schedules
     */
    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeOverdue($query)
    {
        return $query->where('status', 'overdue');
    }
}
