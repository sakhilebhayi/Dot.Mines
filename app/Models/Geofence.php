<?php

namespace App\Models;

use App\Traits\HasTeamFilters;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Geofence Model
 *
 * Represents a pit or work area defined by coordinates
 * Used for geofencing, entry/exit tracking, and material tracking
 *
 * @property int $id
 * @property int $team_id
 * @property int|null $mine_area_id
 * @property string $name
 * @property string|null $description
 * @property string $type
 * @property array<string, mixed> $coordinates
 * @property float $center_latitude
 * @property float $center_longitude
 * @property float $area_sqm
 * @property float $perimeter_m
 * @property string $status
 * @property string|null $notes
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder|Geofence where(string $column, mixed $operator = null, mixed $value = null)
 * @method static \Illuminate\Database\Eloquent\Builder|Geofence whereIn(string $column, array<string|int> $values)
 * @method static \Illuminate\Database\Eloquent\Builder|Geofence orderBy(string $column, string $direction = 'asc')
 * @method static Geofence|null find(mixed $id, array<string> $columns = ['*'])
 * @method static Geofence findOrFail(mixed $id, array<string> $columns = ['*'])
 * @method static \Illuminate\Database\Eloquent\Collection<int,Geofence> all(array<string> $columns = ['*'])
 */
class Geofence extends Model
{
    use HasFactory, HasTeamFilters;

    protected $fillable = [
        'team_id',
        'mine_area_id',
        'name',
        'description',
        'type', // pit, stockpile, dump, facility
        'coordinates', // JSON format for polygon
        'center_latitude',
        'center_longitude',
        'area_sqm', // calculated area in square meters
        'perimeter_m', // calculated perimeter in meters
        'status', // active, inactive
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'coordinates' => 'array',
            'center_latitude' => 'float',
            'center_longitude' => 'float',
            'area_sqm' => 'float',
            'perimeter_m' => 'float',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Get the team that owns this geofence
     */
    /** @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the mine area this geofence belongs to
     */
    /** @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\MineArea, $this> */
    public function mineArea(): BelongsTo
    {
        return $this->belongsTo(MineArea::class);
    }

    /**
     * Get all entry/exit records for this geofence
     */
    /** @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Models\GeofenceEntry, $this> */
    public function entries(): HasMany
    {
        return $this->hasMany(GeofenceEntry::class);
    }

    /**
     * Get all active machines currently in this geofence
     */
    public function activeMachines(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->entries()
            ->where('exit_time', null)
            ->with('machine')
            ->get()
            ->pluck('machine');
    }

    /**
     * Get today's entry records
     */
    public function getTodayEntries(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->entries()
            ->whereDate('entry_time', today())
            ->get();
    }

    /**
     * Calculate total tonnage for a date range
     */
    public function getTonnageForDateRange(\Carbon\Carbon|string $startDate, \Carbon\Carbon|string $endDate): float|int
    {
        return $this->entries()
            ->whereBetween('created_at', [$startDate, $endDate])
            ->sum('tonnage_loaded');
    }
}
