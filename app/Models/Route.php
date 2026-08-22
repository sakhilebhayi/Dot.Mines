<?php

namespace App\Models;

use App\Support\Geo;
use App\Traits\HasTeamFilters;
use Carbon\Carbon;
use Database\Factories\RouteFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Route Model
 *
 * Represents a planned route for a machine from point A to point B
 * Includes waypoints, distance, fuel consumption, and time estimates
 *
 * @property int $id
 * @property int $team_id
 * @property int|null $machine_id
 * @property int|null $mine_area_id
 * @property string $name
 * @property string|null $description
 * @property float $start_latitude
 * @property float $start_longitude
 * @property float $end_latitude
 * @property float $end_longitude
 * @property float $total_distance
 * @property int $estimated_time
 * @property float $estimated_fuel
 * @property string|null $route_type
 * @property int|null $speed_limit
 * @property string $status
 * @property array<string, mixed>|null $metadata
 * @property array<string, mixed>|null $route_geometry
 * @property float $fuel_savings
 * @property int $time_savings
 * @property-read Collection<int, Waypoint> $waypoints
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Machine|null $machine
 */
class Route extends Model
{
    /** @use HasFactory<RouteFactory> */
    use HasFactory, HasTeamFilters;

    /** @var list<string> */
    protected $fillable = [
        'team_id',
        'machine_id',
        'mine_area_id',
        'name',
        'description',
        'start_latitude',
        'start_longitude',
        'end_latitude',
        'end_longitude',
        'total_distance',
        'estimated_time',
        'estimated_fuel',
        'route_type',
        'speed_limit',
        'status',
        'metadata',
        'route_geometry',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'start_latitude' => 'float',
        'start_longitude' => 'float',
        'end_latitude' => 'float',
        'end_longitude' => 'float',
        'total_distance' => 'float',
        'estimated_time' => 'integer',
        'estimated_fuel' => 'float',
        'speed_limit' => 'integer',
        'metadata' => 'array',
        'route_geometry' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the team that owns the route.
     *
     * @return BelongsTo<Team,$this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the machine this route is planned for.
     *
     * @return BelongsTo<Machine,$this>
     */
    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }

    /**
     * Get the mine area this route belongs to.
     *
     * @return BelongsTo<MineArea,$this>
     */
    public function mineArea(): BelongsTo
    {
        return $this->belongsTo(MineArea::class);
    }

    /**
     * Get the waypoints for this route.
     *
     * @return HasMany<Waypoint,$this>
     */
    public function waypoints(): HasMany
    {
        $relation = $this->hasMany(Waypoint::class);
        $relation->orderBy('sequence_order');

        return $relation;
    }

    /**
     * Calculate fuel savings compared to direct route
     */
    protected function getFuelSavingsAttribute(): float
    {
        // Calculate direct distance using Haversine formula
        $directDistance = Geo::distanceKm(
            $this->start_latitude,
            $this->start_longitude,
            $this->end_latitude,
            $this->end_longitude
        );

        // Assume average fuel consumption of 0.4L/km
        $directFuel = $directDistance * 0.4;

        return max(0, $directFuel - $this->estimated_fuel);
    }

    /**
     * Calculate time savings compared to direct route
     */
    protected function getTimeSavingsAttribute(): int
    {
        // Calculate direct distance
        $directDistance = Geo::distanceKm(
            $this->start_latitude,
            $this->start_longitude,
            $this->end_latitude,
            $this->end_longitude
        );

        // Assume average speed of 40 km/h
        $directTime = ($directDistance / 40) * 60; // in minutes

        return max(0, (int) ($directTime - $this->estimated_time));
    }

    /**
     * Scope query to active routes only
     */
    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope query to draft routes only
     */
    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', 'draft');
    }
}
