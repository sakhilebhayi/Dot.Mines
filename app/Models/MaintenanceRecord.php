<?php

namespace App\Models;

use App\Traits\HasTeamFilters;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
 * @property \Carbon\Carbon $scheduled_date
 * @property \Carbon\Carbon|null $started_at
 * @property \Carbon\Carbon|null $completed_at
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
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder|MaintenanceRecord where(string $column, mixed $operator = null, mixed $value = null)
 * @method static \Illuminate\Database\Eloquent\Builder|MaintenanceRecord whereIn(string $column, array $values)
 * @method static \Illuminate\Database\Eloquent\Builder|MaintenanceRecord orderBy(string $column, string $direction = 'asc')
 * @method static MaintenanceRecord|null find(mixed $id, array $columns = ['*'])
 * @method static MaintenanceRecord findOrFail(mixed $id, array $columns = ['*'])
 * @method static \Illuminate\Database\Eloquent\Collection all(array $columns = ['*'])
 *
 * @property-read \App\Models\Machine $machine
 * @property-read \App\Models\MaintenanceSchedule|null $maintenanceSchedule
 */
class MaintenanceRecord extends Model
{
    use HasFactory, HasTeamFilters;

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

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
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
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($record) {
            if (! $record->work_order_number) {
                $record->work_order_number = 'WO-'.strtoupper(uniqid());
            }
        });

        // When a maintenance booking is created, put the machine into Maintenance status.
        static::created(function (self $record) {
            if (in_array($record->status, ['scheduled', 'in_progress'])) {
                Machine::where('id', $record->machine_id)
                    ->whereNotIn('status', ['maintenance'])
                    ->update(['status' => 'maintenance']);
            }
        });

        // When a maintenance record status changes, sync the machine status.
        static::updated(function (self $record) {
            if (! $record->wasChanged('status')) {
                return;
            }

            $newStatus = $record->status;

            if (in_array($newStatus, ['scheduled', 'in_progress'])) {
                // Booking moved to active — ensure machine is in maintenance.
                Machine::where('id', $record->machine_id)
                    ->update(['status' => 'maintenance']);
            } elseif (in_array($newStatus, ['completed', 'cancelled'])) {
                // Check whether any other open records still hold this machine.
                $stillActive = static::where('machine_id', $record->machine_id)
                    ->whereIn('status', ['scheduled', 'in_progress'])
                    ->exists();

                if (! $stillActive) {
                    // No more active bookings — restore machine to active.
                    Machine::where('id', $record->machine_id)
                        ->where('status', 'maintenance')
                        ->update(['status' => 'active']);
                }
            }
        });
    }

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
    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /** @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\User, $this> */
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
    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope for in progress
     */
    public function scopeInProgress(Builder $query): Builder
    {
        return $query->where('status', 'in_progress');
    }
}
