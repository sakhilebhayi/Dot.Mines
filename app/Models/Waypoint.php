<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Waypoint Model
 *
 * Represents a point along a route
 * Waypoints are ordered and define the path a machine should follow
 *
 * @property int $id
 * @property int $route_id
 * @property int $sequence_order
 * @property float $latitude
 * @property float $longitude
 * @property string $waypoint_type
 * @property string|null $name
 * @property string|null $notes
 * @property int|null $estimated_time_from_previous
 * @property float|null $distance_from_previous
 * @property string $icon
 * @property string $color
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Waypoint extends Model
{
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    protected $fillable = [
        'route_id',
        'sequence_order',
        'latitude',
        'longitude',
        'waypoint_type',
        'name',
        'notes',
        'estimated_time_from_previous',
        'distance_from_previous',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sequence_order' => 'integer',
            'latitude' => 'float',
            'longitude' => 'float',
            'estimated_time_from_previous' => 'integer',
            'distance_from_previous' => 'float',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Get the route that owns the waypoint.
     */
    /** @return BelongsTo<Route, $this> */
    public function route(): BelongsTo
    {
        return $this->belongsTo(Route::class);
    }

    /**
     * Get icon for waypoint type
     */
    public function getIconAttribute(): string
    {
        return match ($this->waypoint_type) {
            'fuel_station' => '⛽',
            'loading_point' => '📦',
            'dump_point' => '🚮',
            'geofence' => '🚧',
            default => '📍',
        };
    }

    /**
     * Get color for waypoint type
     */
    public function getColorAttribute(): string
    {
        return match ($this->waypoint_type) {
            'fuel_station' => 'yellow',
            'loading_point' => 'blue',
            'dump_point' => 'red',
            'geofence' => 'orange',
            default => 'gray',
        };
    }
}
