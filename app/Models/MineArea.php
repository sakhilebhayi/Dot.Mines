<?php

namespace App\Models;

use App\Traits\HasSyncVersion;
use Carbon\Carbon;
use Database\Factories\MineAreaFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * MineArea Model
 *
 * Represents a mining area/site within a team
 *
 * @property int $id
 * @property int $team_id
 * @property string $name
 * @property string|null $description
 * @property string|null $location
 * @property array<string, mixed>|null $coordinates
 * @property float|null $center_latitude
 * @property float|null $center_longitude
 * @property float|null $latitude
 * @property float|null $longitude
 * @property float|null $area_size_hectares
 * @property string $status
 * @property string|null $manager_name
 * @property string|null $manager_contact
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $deleted_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property int|null $sync_version
 * @property-read Team|null $team
 */
class MineArea extends Model
{
    /** @use HasFactory<MineAreaFactory> */
    use HasFactory, HasSyncVersion, SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'team_id',
        'name',
        'description',
        'location',
        'coordinates',
        'center_latitude',
        'center_longitude',
        'latitude',
        'longitude',
        'area_size_hectares',
        'status',
        'manager_name',
        'manager_contact',
        'metadata',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'center_latitude' => 'float',
        'center_longitude' => 'float',
        'area_size_hectares' => 'float',
        'metadata' => 'array',
    ];

    /**
     * Get the team this mine area belongs to
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get machines assigned to this mine area
     *
     * @return HasMany<Machine, $this>
     */
    public function machines(): HasMany
    {
        return $this->hasMany(Machine::class);
    }

    /**
     * Get geofences in this mine area
     *
     * @return HasMany<Geofence, $this>
     */
    public function geofences(): HasMany
    {
        return $this->hasMany(Geofence::class);
    }

    /**
     * Get alerts for this mine area
     *
     * @return HasMany<Alert, $this>
     */
    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class);
    }

    /**
     * Get production records for this mine area
     *
     * @return HasMany<ProductionRecord, $this>
     */
    public function productionRecords(): HasMany
    {
        return $this->hasMany(ProductionRecord::class);
    }

    /**
     * Get production targets for this mine area
     *
     * @return HasMany<ProductionTarget, $this>
     */
    public function productionTargets(): HasMany
    {
        return $this->hasMany(ProductionTarget::class);
    }

    /**
     * Get production forecasts for this mine area
     *
     * @return HasMany<ProductionForecast, $this>
     */
    public function productionForecasts(): HasMany
    {
        return $this->hasMany(ProductionForecast::class);
    }

    /**
     * Get mine plan uploads for this mine area
     *
     * @return HasMany<MinePlanUpload, $this>
     */
    public function minePlanUploads(): HasMany
    {
        return $this->hasMany(MinePlanUpload::class);
    }

    /**
     * Get routes in this mine area
     *
     * @return HasMany<Route, $this>
     */
    public function routes(): HasMany
    {
        return $this->hasMany(Route::class);
    }

    /**
     * Get assignment history for this mine area
     *
     * @return HasMany<MachineAreaAssignment, $this>
     */
    public function machineAssignments(): HasMany
    {
        return $this->hasMany(MachineAreaAssignment::class);
    }

    /**
     * Scope to filter by team
     */
    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForTeam($query, int $teamId)
    {
        return $query->where('team_id', $teamId);
    }

    /**
     * Scope to filter by status
     */
    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Check if mine area is active
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
