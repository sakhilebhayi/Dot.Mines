<?php

namespace App\Models;

use App\Traits\HasTeamFilters;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $team_id
 * @property int $machine_id
 * @property int|null $maintenance_record_id
 * @property string $component_name
 * @property string|null $component_type
 * @property string|null $part_number
 * @property string|null $serial_number
 * @property Carbon $replaced_at
 * @property int|null $hours_at_replacement
 * @property int|null $km_at_replacement
 * @property int|null $expected_lifespan_hours
 * @property int|null $expected_lifespan_km
 * @property string|null $replacement_reason
 * @property string|null $cost
 * @property string|null $supplier
 * @property Carbon|null $warranty_expiry
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ComponentReplacement extends Model
{
    /** @use HasFactory<Factory<static>> */
    use HasFactory, HasTeamFilters;

    protected $fillable = [
        'team_id',
        'machine_id',
        'maintenance_record_id',
        'component_name',
        'component_type',
        'part_number',
        'serial_number',
        'replaced_at',
        'hours_at_replacement',
        'km_at_replacement',
        'expected_lifespan_hours',
        'expected_lifespan_km',
        'replacement_reason',
        'cost',
        'supplier',
        'warranty_expiry',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'replaced_at' => 'datetime',
            'hours_at_replacement' => 'integer',
            'km_at_replacement' => 'integer',
            'expected_lifespan_hours' => 'integer',
            'expected_lifespan_km' => 'integer',
            'cost' => 'decimal:2',
            'warranty_expiry' => 'date',
        ];
    }

    /**
     * Relationships
     */
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

    /** @return BelongsTo<MaintenanceRecord, $this> */
    public function maintenanceRecord(): BelongsTo
    {
        return $this->belongsTo(MaintenanceRecord::class);
    }

    /**
     * Scopes
     */
    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeComponent(Builder $query, string $component): Builder
    {
        return $query->where('component_name', $component);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeRecentReplacements(Builder $query, int $days = 30): Builder
    {
        return $query->where('replaced_at', '>=', now()->subDays($days));
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeUnderWarranty(Builder $query): Builder
    {
        return $query->where('warranty_expiry', '>=', now());
    }

    /**
     * Get expected replacement date based on hours
     */
    public function getExpectedReplacementHoursAttribute(): ?int
    {
        if (! $this->expected_lifespan_hours) {
            return null;
        }

        return $this->hours_at_replacement + $this->expected_lifespan_hours;
    }

    /**
     * Get expected replacement km
     */
    public function getExpectedReplacementKmAttribute(): ?int
    {
        if (! $this->expected_lifespan_km) {
            return null;
        }

        return $this->km_at_replacement + $this->expected_lifespan_km;
    }

    /**
     * Check if component is due for replacement based on machine hours
     */
    public function isDueByHours(Machine $machine): bool
    {
        if (! $this->expected_lifespan_hours || ! $machine->operating_hours) {
            return false;
        }

        $hoursOnComponent = $machine->operating_hours - $this->hours_at_replacement;

        return $hoursOnComponent >= $this->expected_lifespan_hours;
    }

    /**
     * Check if component is due for replacement based on km
     */
    public function isDueByKm(Machine $machine): bool
    {
        if (! $this->expected_lifespan_km || ! $machine->total_distance_km) {
            return false;
        }

        $kmOnComponent = $machine->total_distance_km - $this->km_at_replacement;

        return $kmOnComponent >= $this->expected_lifespan_km;
    }

    /**
     * Get remaining lifespan percentage
     */
    public function getRemainingLifespanPercentage(Machine $machine): ?float
    {
        if ($this->expected_lifespan_hours && $machine->operating_hours) {
            $hoursUsed = $machine->operating_hours - $this->hours_at_replacement;
            $percentage = (($this->expected_lifespan_hours - $hoursUsed) / $this->expected_lifespan_hours) * 100;

            return max(0, min(100, $percentage));
        }

        if ($this->expected_lifespan_km && $machine->total_distance_km) {
            $kmUsed = $machine->total_distance_km - $this->km_at_replacement;
            $percentage = (($this->expected_lifespan_km - $kmUsed) / $this->expected_lifespan_km) * 100;

            return max(0, min(100, $percentage));
        }

        return null;
    }

    /**
     * Check if warranty is still valid
     */
    public function getIsUnderWarrantyAttribute(): bool
    {
        return $this->warranty_expiry && $this->warranty_expiry >= now();
    }
}
