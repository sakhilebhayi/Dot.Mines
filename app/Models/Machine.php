<?php

namespace App\Models;

use App\Services\QueryCacheService;
use App\Traits\HasTeamFilters;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Machine Model
 *
 * Represents a mining machine (Volvo, CAT, Komatsu, Bell truck, etc.)
 * Tracks metadata, status, and integrations with manufacturer systems
 *
 * @property int $id
 * @property int $team_id
 * @property string $name
 * @property string $machine_type
 * @property string $manufacturer
 * @property string $model
 * @property int|null $year_of_manufacture
 * @property string|null $registration_number
 * @property string|null $serial_number
 * @property string|null $manufacturer_id
 * @property float $capacity
 * @property float $fuel_capacity
 * @property float $hours_meter
 * @property string $status
 * @property float|null $last_location_latitude
 * @property float|null $last_location_longitude
 * @property \Carbon\Carbon|null $last_location_update
 * @property int|null $integration_id
 * @property int|null $mine_area_id
 * @property int|null $excavator_id
 * @property \Carbon\Carbon|null $assigned_to_excavator_at
 * @property string|null $notes
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder|Machine where(string $column, mixed $operator = null, mixed $value = null)
 * @method static \Illuminate\Database\Eloquent\Builder|Machine whereIn(string $column, array<string|int> $values)
 * @method static \Illuminate\Database\Eloquent\Builder|Machine orderBy(string $column, string $direction = 'asc')
 * @method static \Illuminate\Database\Eloquent\Builder|Machine latest(string $column = 'created_at')
 * @method static \Illuminate\Database\Eloquent\Builder|Machine select(array<string> $columns = ['*'])
 * @method static Machine|null find(mixed $id, array<string> $columns = ['*'])
 * @method static Machine findOrFail(mixed $id, array<string> $columns = ['*'])
 * @method static \Illuminate\Database\Eloquent\Collection<int,Machine> all(array<string> $columns = ['*'])
 * @method static \Illuminate\Pagination\Paginator paginate(int $perPage = 15, array<string> $columns = ['*'], string $pageName = 'page', int $page = null)
 */
class Machine extends Model
{
    use HasFactory, HasTeamFilters;

    protected $fillable = [
        'team_id',
        'name',
        'machine_type', // volvo, cat, komatsu, bell, ldv
        'manufacturer',
        'model',
        'year_of_manufacture',
        'registration_number',
        'serial_number',
        'manufacturer_id', // ID from manufacturer system
        'capacity', // in tonnes
        'fuel_capacity', // in litres
        'cycle_time_minutes', // full haul cycle time in minutes
        'queue_time_minutes', // queue / wait time in minutes
        'loading_time_minutes', // loading time in minutes
        'hours_meter', // total hours
        'status', // active, idle, maintenance, offline
        'last_location_latitude',
        'last_location_longitude',
        'last_location_update',
        'integration_id',
        'mine_area_id', // Current mine area assignment
        'excavator_id', // Assigned excavator
        'assigned_to_excavator_at',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'capacity' => 'float',
            'fuel_capacity' => 'float',
            'cycle_time_minutes' => 'integer',
            'queue_time_minutes' => 'integer',
            'loading_time_minutes' => 'integer',
            'hours_meter' => 'float',
            'last_location_latitude' => 'float',
            'last_location_longitude' => 'float',
            'last_location_update' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        // Invalidate cache when machine is created, updated, or deleted
        static::saved(function (Machine $machine) {
            QueryCacheService::invalidateMachine($machine->id, $machine->team_id);
            QueryCacheService::invalidateDashboard($machine->team_id);
        });

        static::deleted(function (Machine $machine) {
            QueryCacheService::invalidateMachine($machine->id, $machine->team_id);
            QueryCacheService::invalidateDashboard($machine->team_id);
        });
    }

    /**
     * Get the team that owns this machine
     */
    /** @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the integration this machine belongs to
     */
    /** @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\Integration, $this> */
    public function integration(): BelongsTo
    {
        return $this->belongsTo(Integration::class);
    }

    /**
     * Get all metrics for this machine
     */
    /** @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Models\MachineMetric, $this> */
    public function metrics(): HasMany
    {
        return $this->hasMany(MachineMetric::class);
    }

    /**
     * Get all alerts for this machine
     */
    /** @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Models\Alert, $this> */
    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class);
    }

    /**
     * Get all geofence entries for this machine
     */
    /** @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Models\GeofenceEntry, $this> */
    public function geofenceEntries(): HasMany
    {
        return $this->hasMany(GeofenceEntry::class);
    }

    /**
     * Get the mine area this machine is assigned to
     */
    /** @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\MineArea, $this> */
    public function mineArea(): BelongsTo
    {
        return $this->belongsTo(MineArea::class);
    }

    /**
     * Get assignment history for this machine
     */
    /** @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Models\MachineAreaAssignment, $this> */
    public function areaAssignments(): HasMany
    {
        return $this->hasMany(MachineAreaAssignment::class);
    }

    /**
     * Get all engine hour sessions for this machine
     */
    /** @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Models\EngineHourSession, $this> */
    public function engineHourSessions(): HasMany
    {
        return $this->hasMany(EngineHourSession::class);
    }

    /**
     * Get the currently running engine session (if any)
     */
    /** @return \Illuminate\Database\Eloquent\Relations\HasOne<\App\Models\EngineHourSession, $this> */
    public function activeEngineSession(): HasOne
    {
        return $this->hasOne(EngineHourSession::class)
            ->ofMany(
                ['ignition_on_at' => 'max'],
                fn ($q) => $q->whereNull('ignition_off_at')
            );
    }

    /**
     * Get the excavator this machine is assigned to
     */
    /** @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\Machine, $this> */
    public function excavator(): BelongsTo
    {
        return $this->belongsTo(Machine::class, 'excavator_id');
    }

    /**
     * Get all machines assigned to this excavator
     */
    /** @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Models\Machine, $this> */
    public function assignedMachines(): HasMany
    {
        return $this->hasMany(Machine::class, 'excavator_id');
    }

    /**
     * Get all maintenance records for this machine
     */
    /** @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Models\MaintenanceRecord, $this> */
    public function maintenanceRecords(): HasMany
    {
        return $this->hasMany(MaintenanceRecord::class);
    }

    /**
     * Get the health status for this machine
     */
    /** @return \Illuminate\Database\Eloquent\Relations\HasOne<\App\Models\MachineHealthStatus, $this> */
    public function healthStatus(): HasOne
    {
        return $this->hasOne(MachineHealthStatus::class);
    }

    /**
     * Assign this machine to an excavator
     */
    public function assignToExcavator(int|string|null $excavatorId): void
    {
        $this->update([
            'excavator_id' => $excavatorId,
            'assigned_to_excavator_at' => now(),
        ]);
    }

    /**
     * Unassign this machine from its excavator
     */
    public function unassignFromExcavator(): void
    {
        $this->update([
            'excavator_id' => null,
            'assigned_to_excavator_at' => null,
        ]);
    }

    /**
     * Get active alerts for this machine
     */
    public function activeAlerts(): \Illuminate\Database\Eloquent\Builder
    {
        return $this->alerts()->where('status', 'active');
    }

    /**
     * Update machine location
     */
    public function updateLocation(float|string $latitude, float|string $longitude): void
    {
        $this->update([
            'last_location_latitude' => $latitude,
            'last_location_longitude' => $longitude,
            'last_location_update' => now(),
        ]);
    }

    /**
     * Get latest metric
     */
    public function getLatestMetric(): ?\Illuminate\Database\Eloquent\Model
    {
        return $this->metrics()->latest('created_at')->first();
    }
}
