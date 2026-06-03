<?php

namespace App\Models;

use App\Traits\HasTeamFilters;
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
 * @property array|null $metadata
 * @property array|null $route_geometry
 * @property float $fuel_savings
 * @property int $time_savings
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder|Route where(string $column, mixed $operator = null, mixed $value = null)
 * @method static \Illuminate\Database\Eloquent\Builder|Route whereIn(string $column, array<string|int> $values)
 * @method static \Illuminate\Database\Eloquent\Builder|Route orderBy(string $column, string $direction = 'asc')
 * @method static Route|null find(mixed $id, array<string> $columns = ['*'])
 * @method static Route findOrFail(mixed $id, array<string> $columns = ['*'])
 * @method static \Illuminate\Database\Eloquent\Collection<int,Route> all(array<string> $columns = ['*'])
 */
class Route extends Model
{
    use HasFactory, HasTeamFilters;

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

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
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
    }

    /**
     * Get the team that owns the route.
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the machine this route is planned for.
     */
    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }

    /**
     * Get the mine area this route belongs to.
     */
    public function mineArea(): BelongsTo
    {
        return $this->belongsTo(MineArea::class);
    }

    /**
     * Get the waypoints for this route.
     */
    public function waypoints(): HasMany
    {
        return $this->hasMany(Waypoint::class)->orderBy('sequence_order');
    }

    /**
     * Calculate fuel savings compared to direct route
     */
    public function getFuelSavingsAttribute(): float
    {
        // Calculate direct distance using Haversine formula
        $directDistance = $this->calculateDistance(
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
    public function getTimeSavingsAttribute(): int
    {
        // Calculate direct distance
        $directDistance = $this->calculateDistance(
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
     * Calculate distance between two coordinates using Haversine formula
     */
    protected function calculateDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371; // km

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    /**
     * Scope query to active routes only
     */
    public function scopeActive(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope query to draft routes only
     */
    public function scopeDraft(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('status', 'draft');
    }
}
