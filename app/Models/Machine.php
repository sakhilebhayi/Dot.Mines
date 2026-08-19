<?php

namespace App\Models;

use App\Services\QueryCacheService;
use App\Traits\HasTeamFilters;
use Carbon\Carbon;
use Database\Factories\MachineFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
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
 * @property Carbon|null $last_location_update
 * @property int|null $integration_id
 * @property int|null $mine_area_id
 * @property int|null $excavator_id
 * @property Carbon|null $assigned_to_excavator_at
 * @property string|null $notes
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Team|null $team
 * @property-read Integration|null $integration
 * @property-read MineArea|null $mineArea
 * @property-read Machine|null $excavator
 * @property-read MachineHealthStatus|null $healthStatus
 * @property-read Collection<int, Alert> $alerts
 * @property-read Collection<int, MachineMetric> $metrics
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
    /** @use HasFactory<MachineFactory> */
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

    protected $casts = [
        'capacity' => 'float',
        'fuel_capacity' => 'float',
        'hours_meter' => 'float',
        'last_location_latitude' => 'float',
        'last_location_longitude' => 'float',
        'last_location_update' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

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
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the integration this machine belongs to
     */
    public function integration(): BelongsTo
    {
        return $this->belongsTo(Integration::class);
    }

    /**
     * Get all metrics for this machine
     */
    public function metrics(): HasMany
    {
        return $this->hasMany(MachineMetric::class);
    }

    /**
     * Get all alerts for this machine
     */
    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class);
    }

    /**
     * Get all geofence entries for this machine
     */
    public function geofenceEntries(): HasMany
    {
        return $this->hasMany(GeofenceEntry::class);
    }

    /**
     * Get the mine area this machine is assigned to
     */
    public function mineArea(): BelongsTo
    {
        return $this->belongsTo(MineArea::class);
    }

    /**
     * Get assignment history for this machine
     */
    public function areaAssignments(): HasMany
    {
        return $this->hasMany(MachineAreaAssignment::class);
    }

    /**
     * Get the excavator this machine is assigned to
     */
    public function excavator(): BelongsTo
    {
        return $this->belongsTo(Machine::class, 'excavator_id');
    }

    /**
     * Get all machines assigned to this excavator
     */
    public function assignedMachines(): HasMany
    {
        return $this->hasMany(Machine::class, 'excavator_id');
    }

    /**
     * Get all maintenance records for this machine
     */
    public function maintenanceRecords(): HasMany
    {
        return $this->hasMany(MaintenanceRecord::class);
    }

    /**
     * Get all production records for this machine
     *
     * @return HasMany<ProductionRecord, $this>
     */
    public function productionRecords(): HasMany
    {
        return $this->hasMany(ProductionRecord::class);
    }

    /**
     * Get all production loss events for this machine
     *
     * @return HasMany<ProductionLossEvent, $this>
     */
    public function lossEvents(): HasMany
    {
        return $this->hasMany(ProductionLossEvent::class);
    }

    /**
     * Get the health status for this machine
     */
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
     *
     * @return Builder<Alert>
     */
    public function activeAlerts(): Builder
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
    public function getLatestMetric(): ?Model
    {
        return $this->metrics()->latest('created_at')->first();
    }

    /**
     * The newest telemetry reading that actually carries an engine-hours
     * value. Cumulative operating hours arrive with every Bell /Fleet
     * snapshot sync (BellService::buildCurrentMetric() maps OperatingHours
     * to machine_metrics.operating_hours), but some rows are location- or
     * fuel-only -- the fleet card wants the latest AVAILABLE reading, as
     * an eager-loadable relationship so listing pages avoid an N+1.
     *
     * @return HasOne<MachineMetric, $this>
     */
    public function latestEngineHoursMetric(): HasOne
    {
        return $this->hasOne(MachineMetric::class)->ofMany(
            ['created_at' => 'max', 'id' => 'max'],
            fn ($query) => $query->whereNotNull('operating_hours')
        );
    }
}
