<?php

namespace App\Models;

use App\Traits\HasTeamFilters;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property float|numeric-string|null $fuel_consumed_liters
 * @property float|numeric-string|null $distance_traveled_km
 * @property float|numeric-string|null $idle_fuel_consumed
 * @property float|numeric-string|null $operating_hours
 */
class FuelConsumptionMetric extends Model
{
    use HasTeamFilters;

    /** @var array<int, string> */
    protected $fillable = [
        'team_id',
        'machine_id',
        'date',
        'fuel_consumed_liters',
        'distance_traveled_km',
        'operating_hours',
        'fuel_efficiency_lph',
        'fuel_efficiency_lpkm',
        'idle_time_hours',
        'idle_fuel_consumed',
        'average_load_percentage',
        'shift',
        'metadata',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'date' => 'date',
        'fuel_consumed_liters' => 'decimal:2',
        'distance_traveled_km' => 'decimal:2',
        'operating_hours' => 'decimal:2',
        'fuel_efficiency_lph' => 'decimal:4',
        'fuel_efficiency_lpkm' => 'decimal:4',
        'idle_time_hours' => 'decimal:2',
        'idle_fuel_consumed' => 'decimal:2',
        'average_load_percentage' => 'decimal:2',
        'metadata' => 'json',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /** @return BelongsTo<Team,$this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /** @return BelongsTo<Machine,$this> */
    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }

    /**
     * Calculate fuel efficiency (liters per hour)
     */
    public function calculateLph(): ?float
    {
        if ($this->operating_hours == 0) {
            return null;
        }

        return round((float) ($this->fuel_consumed_liters ?? 0) / (float) $this->operating_hours, 4);
    }

    /**
     * Calculate fuel efficiency (liters per km)
     */
    public function calculateLpkm(): ?float
    {
        $distance = (float) ($this->distance_traveled_km ?? 0);

        if ($distance === 0.0) {
            return null;
        }

        return round((float) ($this->fuel_consumed_liters ?? 0) / $distance, 4);
    }

    /**
     * Get idle fuel percentage
     */
    protected function getIdleFuelPercentageAttribute(): ?float
    {
        $consumed = (float) ($this->fuel_consumed_liters ?? 0);
        $idle = (float) ($this->idle_fuel_consumed ?? 0);

        if ($consumed === 0.0 || $idle === 0.0) {
            return null;
        }

        return round(($idle / $consumed) * 100.0, 2);
    }
}
