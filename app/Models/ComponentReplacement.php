<?php

namespace App\Models;

use App\Traits\HasTeamFilters;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int|null $km_at_replacement
 * @property int|null $hours_at_replacement
 * @property int|null $expected_lifespan_km
 * @property int|null $expected_lifespan_hours
 * @property Carbon|null $warranty_expiry
 */
class ComponentReplacement extends Model
{
    use HasTeamFilters;

    /** @var array<int, string> */
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

    /** @var array<string, string> */
    protected $casts = [
        'replaced_at' => 'datetime',
        'hours_at_replacement' => 'integer',
        'km_at_replacement' => 'integer',
        'expected_lifespan_hours' => 'integer',
        'expected_lifespan_km' => 'integer',
        'cost' => 'decimal:2',
        'warranty_expiry' => 'date',
    ];

    /**
     * Relationships
     *
     * @return BelongsTo<Team,$this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /** @return BelongsTo<Machine,$this> */
    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }

    /** @return BelongsTo<MaintenanceRecord,$this> */
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
    public function scopeComponent($query, string $component)
    {
        return $query->where('component_name', $component);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeRecentReplacements($query, int $days = 30)
    {
        return $query->where('replaced_at', '>=', now()->subDays($days));
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeUnderWarranty($query)
    {
        return $query->where('warranty_expiry', '>=', now());
    }

    /**
     * Get expected replacement date based on hours
     */
    protected function getExpectedReplacementHoursAttribute(): ?int
    {
        if (($this->expected_lifespan_hours === null || $this->expected_lifespan_hours === 0)) {
            return null;
        }

        return ($this->hours_at_replacement ?? 0) + $this->expected_lifespan_hours;
    }

    /**
     * Get expected replacement km
     */
    protected function getExpectedReplacementKmAttribute(): ?int
    {
        if (($this->expected_lifespan_km === null || $this->expected_lifespan_km === 0)) {
            return null;
        }

        return ($this->km_at_replacement ?? 0) + $this->expected_lifespan_km;
    }

    /**
     * Check if component is due for replacement based on machine hours
     */
    public function isDueByHours(Machine $machine): bool
    {
        $lifespan = $this->expected_lifespan_hours;
        $operating = $machine->operating_hours !== null ? (float) $machine->operating_hours : null;

        if ($lifespan === null || $lifespan === 0 || $operating === null || $operating === 0.0) {
            return false;
        }

        $hoursOnComponent = $operating - (float) ($this->hours_at_replacement ?? 0);

        return $hoursOnComponent >= (float) $lifespan;
    }

    /**
     * Check if component is due for replacement based on km
     */
    public function isDueByKm(Machine $machine): bool
    {
        $lifespan = $this->expected_lifespan_km;
        $distance = $machine->total_distance_km !== null ? (float) $machine->total_distance_km : null;

        if ($lifespan === null || $lifespan === 0 || $distance === null || $distance === 0.0) {
            return false;
        }

        $kmOnComponent = $distance - (float) ($this->km_at_replacement ?? 0);

        return $kmOnComponent >= (float) $lifespan;
    }

    /**
     * Get remaining lifespan percentage
     */
    public function getRemainingLifespanPercentage(Machine $machine): ?float
    {
        $lifespanHours = $this->expected_lifespan_hours;
        $operating = $machine->operating_hours !== null ? (float) $machine->operating_hours : null;

        if ($lifespanHours !== null && $lifespanHours !== 0 && $operating !== null && $operating !== 0.0) {
            $hoursUsed = $operating - (float) ($this->hours_at_replacement ?? 0);
            $percentage = (((float) $lifespanHours - $hoursUsed) / (float) $lifespanHours) * 100.0;

            return max(0.0, min(100.0, $percentage));
        }

        $lifespanKm = $this->expected_lifespan_km;
        $distance = $machine->total_distance_km !== null ? (float) $machine->total_distance_km : null;

        if ($lifespanKm !== null && $lifespanKm !== 0 && $distance !== null && $distance !== 0.0) {
            $kmUsed = $distance - (float) ($this->km_at_replacement ?? 0);
            $percentage = (((float) $lifespanKm - $kmUsed) / (float) $lifespanKm) * 100.0;

            return max(0.0, min(100.0, $percentage));
        }

        return null;
    }

    /**
     * Check if warranty is still valid
     */
    protected function getIsUnderWarrantyAttribute(): bool
    {
        return $this->warranty_expiry !== null && $this->warranty_expiry >= now();
    }
}
