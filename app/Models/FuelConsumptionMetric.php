<?php

namespace App\Models;

use App\Traits\HasTeamFilters;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * FuelConsumptionMetric Model
 *
 * @property int $id
 * @property int $team_id
 * @property int $machine_id
 * @property Carbon $date
 * @property float $fuel_consumed_liters
 * @property float|null $distance_traveled_km
 * @property float|null $operating_hours
 * @property float|null $fuel_efficiency_lph
 * @property float|null $fuel_efficiency_lpkm
 * @property float|null $idle_time_hours
 * @property float|null $idle_fuel_consumed
 * @property float|null $average_load_percentage
 * @property string|null $shift
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class FuelConsumptionMetric extends Model
{
    /** @use HasFactory<Factory<static>> */
    use HasFactory, HasTeamFilters;

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

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
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
    }

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /** @return BelongsTo<Machine, $this> */
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

        return round($this->fuel_consumed_liters / $this->operating_hours, 4);
    }

    /**
     * Calculate fuel efficiency (liters per km)
     */
    public function calculateLpkm(): ?float
    {
        if ($this->distance_traveled_km == 0) {
            return null;
        }

        return round($this->fuel_consumed_liters / $this->distance_traveled_km, 4);
    }

    /**
     * Get idle fuel percentage
     */
    public function getIdleFuelPercentageAttribute(): ?float
    {
        if ($this->fuel_consumed_liters == 0 || ! $this->idle_fuel_consumed) {
            return null;
        }

        return round(($this->idle_fuel_consumed / $this->fuel_consumed_liters) * 100, 2);
    }
}
