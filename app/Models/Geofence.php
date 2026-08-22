<?php

namespace App\Models;

use App\Traits\HasTeamFilters;
use Carbon\Carbon;
use Database\Factories\GeofenceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

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
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Geofence extends Model
{
    /** @use HasFactory<GeofenceFactory> */
    use HasFactory, HasTeamFilters;

    /** @var list<string> */
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

    /** @var array<string, string> */
    protected $casts = [
        'coordinates' => 'array',
        'center_latitude' => 'float',
        'center_longitude' => 'float',
        'area_sqm' => 'float',
        'perimeter_m' => 'float',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the team that owns this geofence
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the mine area this geofence belongs to
     *
     * @return BelongsTo<MineArea, $this>
     */
    public function mineArea(): BelongsTo
    {
        return $this->belongsTo(MineArea::class);
    }

    /**
     * Get all entry/exit records for this geofence
     *
     * @return HasMany<GeofenceEntry, $this>
     */
    public function entries(): HasMany
    {
        return $this->hasMany(GeofenceEntry::class);
    }

    /**
     * Get all active machines currently in this geofence
     */
    /** @return Collection<int, Machine|null> */
    public function activeMachines(): Collection
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
    /** @return \Illuminate\Database\Eloquent\Collection<int, GeofenceEntry> */
    public function getTodayEntries(): \Illuminate\Database\Eloquent\Collection
    {
        $query = $this->entries();
        $query->whereDate('entry_time', today());

        return $query->get();
    }

    /**
     * Calculate total tonnage for a date range
     */
    public function getTonnageForDateRange(string|\DateTimeInterface $startDate, string|\DateTimeInterface $endDate): float
    {
        return (float) $this->entries()
            ->whereBetween('created_at', [$startDate, $endDate])
            ->sum('tonnage_loaded');
    }
}
