<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $team_id
 * @property int $machine_id
 * @property int|null $mine_area_id
 * @property string $status
 * @property string|null $origin_name
 * @property float|null $origin_latitude
 * @property float|null $origin_longitude
 * @property string|null $destination_name
 * @property float|null $destination_latitude
 * @property float|null $destination_longitude
 * @property float|null $current_latitude
 * @property float|null $current_longitude
 * @property float|null $current_heading
 * @property float $current_speed_kmh
 * @property float $current_tonnage
 * @property float|null $current_fuel_level_litres
 * @property float|null $fuel_capacity_litres
 * @property float|null $total_distance_km
 * @property float|null $distance_remaining_km
 * @property \Carbon\Carbon|null $started_at
 * @property \Carbon\Carbon|null $estimated_arrival_at
 * @property \Carbon\Carbon|null $completed_at
 * @property array<string, mixed>|null $path_coordinates
 * @property array<string, mixed>|null $metadata
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read Machine $machine
 * @property-read MineArea|null $mineArea
 * @property-read float $fuel_percentage
 * @property-read string $eta_formatted
 */
class HaulDispatch extends Model
{
    protected $fillable = [
        'team_id',
        'machine_id',
        'mine_area_id',
        'status',
        'origin_name',
        'origin_latitude',
        'origin_longitude',
        'destination_name',
        'destination_latitude',
        'destination_longitude',
        'current_latitude',
        'current_longitude',
        'current_heading',
        'current_speed_kmh',
        'current_tonnage',
        'current_fuel_level_litres',
        'fuel_capacity_litres',
        'total_distance_km',
        'distance_remaining_km',
        'started_at',
        'estimated_arrival_at',
        'completed_at',
        'path_coordinates',
        'metadata',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'origin_latitude' => 'float',
            'origin_longitude' => 'float',
            'destination_latitude' => 'float',
            'destination_longitude' => 'float',
            'current_latitude' => 'float',
            'current_longitude' => 'float',
            'current_heading' => 'float',
            'current_speed_kmh' => 'float',
            'current_tonnage' => 'float',
            'current_fuel_level_litres' => 'float',
            'fuel_capacity_litres' => 'float',
            'total_distance_km' => 'float',
            'distance_remaining_km' => 'float',
            'started_at' => 'datetime',
            'estimated_arrival_at' => 'datetime',
            'completed_at' => 'datetime',
            'path_coordinates' => 'array',
            'metadata' => 'array',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    /** @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\Machine, $this> */
    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }

    /** @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\MineArea, $this> */
    public function mineArea(): BelongsTo
    {
        return $this->belongsTo(MineArea::class);
    }

    /** @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    // ─── Computed Accessors ───────────────────────────────────────────────────

    /**
     * Fuel level as a 0–100 percentage.
     */
    public function getFuelPercentageAttribute(): float
    {
        $capacity = $this->fuel_capacity_litres
            ?? $this->machine?->fuel_capacity
            ?? 0;

        if ($capacity <= 0 || $this->current_fuel_level_litres === null) {
            return 0.0;
        }

        return round(min(100, ($this->current_fuel_level_litres / $capacity) * 100), 1);
    }

    /**
     * Human-readable ETA string, e.g. "14m", "1h 5m", "Overdue".
     */
    public function getEtaFormattedAttribute(): string
    {
        if (! $this->estimated_arrival_at) {
            return 'N/A';
        }

        if ($this->estimated_arrival_at->isPast()) {
            return 'Overdue';
        }

        $diffMins = (int) now()->diffInMinutes($this->estimated_arrival_at);

        if ($diffMins < 60) {
            return "{$diffMins}m";
        }

        $hours = intdiv($diffMins, 60);
        $mins = $diffMins % 60;

        return $mins > 0 ? "{$hours}h {$mins}m" : "{$hours}h";
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    /**
     * Only dispatches that are currently active (not yet completed).
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('completed_at');
    }

    /**
     * Dispatches for a specific team.
     */
    public function scopeForTeam(Builder $query, int $teamId): Builder
    {
        return $query->where('team_id', $teamId);
    }
}
