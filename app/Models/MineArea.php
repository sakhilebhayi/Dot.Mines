<?php

namespace App\Models;

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
 * @property array|null $coordinates
 * @property float|null $center_latitude
 * @property float|null $center_longitude
 * @property float|null $latitude
 * @property float|null $longitude
 * @property float|null $area_size_hectares
 * @property string $status
 * @property string|null $manager_name
 * @property string|null $manager_contact
 * @property array|null $metadata
 * @property \Carbon\Carbon|null $deleted_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder|MineArea where(string $column, mixed $operator = null, mixed $value = null)
 * @method static \Illuminate\Database\Eloquent\Builder|MineArea whereIn(string $column, array $values)
 * @method static \Illuminate\Database\Eloquent\Builder|MineArea orderBy(string $column, string $direction = 'asc')
 * @method static MineArea|null find(mixed $id, array $columns = ['*'])
 * @method static MineArea findOrFail(mixed $id, array $columns = ['*'])
 * @method static \Illuminate\Database\Eloquent\Collection all(array $columns = ['*'])
 */
class MineArea extends Model
{
    use SoftDeletes;

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
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get machines assigned to this mine area
     */
    public function machines(): HasMany
    {
        return $this->hasMany(Machine::class);
    }

    /**
     * Get geofences in this mine area
     */
    public function geofences(): HasMany
    {
        return $this->hasMany(Geofence::class);
    }

    /**
     * Get alerts for this mine area
     */
    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class);
    }

    /**
     * Get production records for this mine area
     */
    public function productionRecords(): HasMany
    {
        return $this->hasMany(ProductionRecord::class);
    }

    /**
     * Get production targets for this mine area
     */
    public function productionTargets(): HasMany
    {
        return $this->hasMany(ProductionTarget::class);
    }

    /**
     * Get production forecasts for this mine area
     */
    public function productionForecasts(): HasMany
    {
        return $this->hasMany(ProductionForecast::class);
    }

    /**
     * Get mine plan uploads for this mine area
     */
    public function minePlanUploads(): HasMany
    {
        return $this->hasMany(MinePlanUpload::class);
    }

    /**
     * Get routes in this mine area
     */
    public function routes(): HasMany
    {
        return $this->hasMany(Route::class);
    }

    /**
     * Get assignment history for this mine area
     */
    public function machineAssignments(): HasMany
    {
        return $this->hasMany(MachineAreaAssignment::class);
    }

    /**
     * Scope to filter by team
     */
    public function scopeForTeam($query, $teamId)
    {
        return $query->where('team_id', $teamId);
    }

    /**
     * Scope to filter by status
     */
    public function scopeByStatus($query, $status)
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
