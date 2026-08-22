<?php

namespace App\Models;

use App\Traits\HasTeamFilters;
use Carbon\Carbon;
use Database\Factories\MaintenanceRecordFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property Carbon|string|null $completed_at
 * @property Carbon|string|null $started_at
 */
class MaintenanceRecord extends Model
{
    /** @use HasFactory<MaintenanceRecordFactory> */
    use HasFactory, HasTeamFilters;

    /**
     * MaintenanceRecord Model
     *
     * @property int $id
     * @property int $team_id
     * @property int $machine_id
     * @property int|null $maintenance_schedule_id
     * @property string $work_order_number
     * @property string $maintenance_type
     * @property string $title
     * @property string|null $description
     * @property string|null $work_performed
     * @property string $status
     * @property string $priority
     * @property Carbon $scheduled_date
     * @property Carbon|null $started_at
     * @property Carbon|null $completed_at
     * @property int|null $assigned_to
     * @property int|null $completed_by
     * @property string|float $labor_hours
     * @property string|float $labor_cost
     * @property string|float $parts_cost
     * @property string|float $total_cost
     * @property array<string, mixed>|null $parts_used
     * @property array<string, mixed>|null $fault_codes_cleared
     * @property int|null $odometer_reading
     * @property int|null $hour_meter_reading
     * @property string|null $technician_notes
     * @property array<string, mixed>|null $attachments
     * @property bool $machine_operational
     * @property float|null $duration
     * @property Carbon $created_at
     * @property Carbon $updated_at
     *
     * @method static \Illuminate\Database\Eloquent\Builder|MaintenanceRecord where(string $column, mixed $operator = null, mixed $value = null)
     * @method static \Illuminate\Database\Eloquent\Builder|MaintenanceRecord whereIn(string $column, array $values)
     * @method static \Illuminate\Database\Eloquent\Builder|MaintenanceRecord orderBy(string $column, string $direction = 'asc')
     * @method static MaintenanceRecord|null find(mixed $id, array $columns = ['*'])
     * @method static MaintenanceRecord findOrFail(mixed $id, array $columns = ['*'])
     * @method static \Illuminate\Database\Eloquent\Collection all(array $columns = ['*'])
     */
    /** @var list<string> */
    protected $fillable = [
        'team_id',
        'machine_id',
        'maintenance_schedule_id',
        'work_order_number',
        'maintenance_type',
        'title',
        'description',
        'work_performed',
        'status',
        'priority',
        'scheduled_date',
        'started_at',
        'completed_at',
        'assigned_to',
        'completed_by',
        'labor_hours',
        'labor_cost',
        'parts_cost',
        'total_cost',
        'parts_used',
        'fault_codes_cleared',
        'odometer_reading',
        'hour_meter_reading',
        'technician_notes',
        'attachments',
        'machine_operational',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'scheduled_date' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'labor_hours' => 'decimal:2',
        'labor_cost' => 'decimal:2',
        'parts_cost' => 'decimal:2',
        'total_cost' => 'decimal:2',
        'parts_used' => 'json',
        'fault_codes_cleared' => 'json',
        'attachments' => 'json',
        'machine_operational' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($record) {
            if (! $record->work_order_number) {
                $record->work_order_number = 'WO-'.strtoupper(uniqid());
            }
        });
    }

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
    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /** @return BelongsTo<User, $this> */
    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    /**
     * Get duration in hours
     */
    public function getDurationAttribute(): ?float
    {
        if (! $this->started_at || ! $this->completed_at) {
            return null;
        }

        return $this->started_at->diffInHours($this->completed_at, true);
    }

    /**
     * Scope for completed records
     */
    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope for in progress
     */
    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeInProgress($query)
    {
        return $query->where('status', 'in_progress');
    }
}
