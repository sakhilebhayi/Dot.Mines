<?php

namespace App\Models;

use App\Traits\HasTeamFilters;
use Carbon\Carbon;
use Database\Factories\FuelTankFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * FuelTank Model
 *
 * @property int $id
 * @property int $team_id
 * @property int|null $mine_area_id
 * @property string $name
 * @property string|null $tank_number
 * @property string|null $location_description
 * @property string|float|null $location_latitude
 * @property string|float|null $location_longitude
 * @property string|float $capacity_liters
 * @property string|float $current_level_liters
 * @property string|float $minimum_level_liters
 * @property string|null $fuel_type
 * @property string $status
 * @property string|Carbon|null $last_inspection_date
 * @property string|Carbon|null $next_inspection_date
 * @property string|null $notes
 * @property float $fill_percentage
 * @property float $available_capacity
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class FuelTank extends Model
{
    /** @use HasFactory<FuelTankFactory> */
    use HasFactory, HasTeamFilters;

    /** @var array<int, string> */
    protected $fillable = [
        'team_id',
        'mine_area_id',
        'name',
        'tank_number',
        'location_description',
        'location_latitude',
        'location_longitude',
        'capacity_liters',
        'current_level_liters',
        'minimum_level_liters',
        'fuel_type',
        'status',
        'last_inspection_date',
        'next_inspection_date',
        'notes',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'capacity_liters' => 'decimal:2',
        'current_level_liters' => 'decimal:2',
        'minimum_level_liters' => 'decimal:2',
        'location_latitude' => 'decimal:8',
        'location_longitude' => 'decimal:8',
        'last_inspection_date' => 'date',
        'next_inspection_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /** @return BelongsTo<Team,$this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /** @return HasMany<FuelTransaction,$this> */
    public function transactions(): HasMany
    {
        return $this->hasMany(FuelTransaction::class);
    }

    /** @return HasMany<FuelAlert,$this> */
    public function alerts(): HasMany
    {
        return $this->hasMany(FuelAlert::class);
    }

    /**
     * Belongs to a mine area (optional)
     *
     * @return BelongsTo<MineArea,$this>
     */
    public function mineArea(): BelongsTo
    {
        return $this->belongsTo(MineArea::class, 'mine_area_id');
    }

    /**
     * Get the fill percentage
     */
    protected function getFillPercentageAttribute(): float
    {
        if ($this->capacity_liters == 0) {
            return 0;
        }

        return round(((float) $this->current_level_liters / (float) $this->capacity_liters) * 100.0, 2);
    }

    /**
     * Check if tank is below minimum level
     */
    public function isBelowMinimum(): bool
    {
        return $this->current_level_liters < $this->minimum_level_liters;
    }

    /**
     * Check if tank is critical (below 10%)
     */
    public function isCritical(): bool
    {
        return $this->fill_percentage < 10;
    }

    /**
     * Get available capacity
     */
    protected function getAvailableCapacityAttribute(): float
    {
        return (float) $this->capacity_liters - (float) $this->current_level_liters;
    }

    /**
     * Scope for active tanks
     */
    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope for low fuel tanks
     */
    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeLowFuel($query)
    {
        $query->whereRaw('current_level_liters < minimum_level_liters');

        return $query;
    }

    /**
     * Scope for critical fuel tanks
     */
    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeCritical($query)
    {
        $query->whereRaw('(current_level_liters / capacity_liters) < 0.1');

        return $query;
    }
}
