<?php

namespace App\Models;

use App\Services\QueryCacheService;
use App\Traits\HasSyncVersion;
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
 * @property string $allocation_state
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
 * @property-read MachineMetric|null $latestMetric
 * @property-read MachineMetric|null $latestEngineHoursMetric
 * @property-read Collection<int, MaintenanceRecord> $maintenanceRecords
 * @property int|null $sync_version
 * @property float|numeric-string|null $operating_hours
 * @property float|numeric-string|null $total_distance_km
 * @property float|numeric-string|null $odometer
 * @property mixed|null $last_seen_at
 */
class Machine extends Model
{
    /** @use HasFactory<MachineFactory> */
    use HasFactory, HasSyncVersion, HasTeamFilters;

    /** @var list<string> */
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
        'allocation_state', // occupying, pending_activation, released (entitlement dimension)
        'last_location_latitude',
        'last_location_longitude',
        'last_location_update',
        'integration_id',
        'mine_area_id', // Current mine area assignment
        'excavator_id', // Assigned excavator
        'assigned_to_excavator_at',
        'notes',
    ];

    /** @var array<string, string> */
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
    #[\Override]
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
     *
     * @return BelongsTo<Team,$this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the integration this machine belongs to
     *
     * @return BelongsTo<Integration,$this>
     */
    public function integration(): BelongsTo
    {
        return $this->belongsTo(Integration::class);
    }

    /**
     * Get all metrics for this machine
     *
     * @return HasMany<MachineMetric,$this>
     */
    public function metrics(): HasMany
    {
        return $this->hasMany(MachineMetric::class);
    }

    /**
     * Get all alerts for this machine
     *
     * @return HasMany<Alert,$this>
     */
    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class);
    }

    /**
     * Get all geofence entries for this machine
     *
     * @return HasMany<GeofenceEntry,$this>
     */
    public function geofenceEntries(): HasMany
    {
        return $this->hasMany(GeofenceEntry::class);
    }

    /**
     * Get the mine area this machine is assigned to
     *
     * @return BelongsTo<MineArea,$this>
     */
    public function mineArea(): BelongsTo
    {
        return $this->belongsTo(MineArea::class);
    }

    /**
     * Get assignment history for this machine
     *
     * @return HasMany<MachineAreaAssignment,$this>
     */
    public function areaAssignments(): HasMany
    {
        return $this->hasMany(MachineAreaAssignment::class);
    }

    /**
     * Get the excavator this machine is assigned to
     *
     * @return BelongsTo<Machine,$this>
     */
    public function excavator(): BelongsTo
    {
        return $this->belongsTo(Machine::class, 'excavator_id');
    }

    /**
     * Get all machines assigned to this excavator
     *
     * @return HasMany<Machine,$this>
     */
    public function assignedMachines(): HasMany
    {
        return $this->hasMany(Machine::class, 'excavator_id');
    }

    /**
     * Get all maintenance records for this machine
     *
     * @return HasMany<MaintenanceRecord,$this>
     */
    public function maintenanceRecords(): HasMany
    {
        return $this->hasMany(MaintenanceRecord::class);
    }

    /**
     * Get all production records for this machine
     *
     * @return HasMany<ProductionRecord,$this>
     */
    public function productionRecords(): HasMany
    {
        return $this->hasMany(ProductionRecord::class);
    }

    /**
     * Get all production loss events for this machine
     *
     * @return HasMany<ProductionLossEvent,$this>
     */
    public function lossEvents(): HasMany
    {
        return $this->hasMany(ProductionLossEvent::class);
    }

    /**
     * Get the health status for this machine
     *
     * @return HasOne<MachineHealthStatus,$this>
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
     * @return HasOne<MachineMetric,$this>
     */
    public function latestEngineHoursMetric(): HasOne
    {
        return $this->hasOne(MachineMetric::class)->ofMany(
            ['created_at' => 'max', 'id' => 'max'],
            fn ($query): mixed => $query->whereNotNull('operating_hours')
        );
    }

    /**
     * The newest telemetry row of any kind -- its recorded_at is the
     * machine's honest data age (Bell's own telemetry timestamp, not the
     * moment we synced), which is what freshness badges must show.
     * Eager-loadable so listing pages avoid an N+1.
     *
     * Eager-loaded by name from Fleet::render(), which psalm cannot see;
     * ofMany()'s framework stubs return an untyped relation, as with the
     * sibling latestEngineHoursMetric().
     *
     * @psalm-suppress PossiblyUnusedMethod
     * @psalm-suppress MixedReturnStatement
     * @psalm-suppress MixedMethodCall
     *
     * @return HasOne<MachineMetric,$this>
     */
    public function latestMetric(): HasOne
    {
        return $this->hasOne(MachineMetric::class)->ofMany(
            ['created_at' => 'max', 'id' => 'max']
        );
    }
}
