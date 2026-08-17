<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * ProductionRecord Model
 *
 * @property int $id
 * @property int $team_id
 * @property int $mine_area_id
 * @property int $machine_id
 * @property-read Machine|null $machine
 * @property string|Carbon $record_date
 * @property string $shift
 * @property string|float $quantity_produced
 * @property string|float|null $system_quantity
 * @property string $unit
 * @property string|float $target_quantity
 * @property string|null $notes
 * @property string $status
 * @property array<string, mixed>|null $metadata
 * @property float $variance_percentage
 * @property bool $is_above_target
 * @property float|null $system_variance_percentage
 * @property Carbon|null $deleted_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class ProductionRecord extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'team_id',
        'mine_area_id',
        'machine_id',
        'record_date',
        'shift',
        'quantity_produced',
        'system_quantity',
        'loads_moved',
        'cycles_completed',
        'unit',
        'target_quantity',
        'notes',
        'status',
        'metadata',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity_produced' => 'decimal:2',
            'system_quantity' => 'decimal:2',
            'target_quantity' => 'decimal:2',
            'loads_moved' => 'integer',
            'cycles_completed' => 'integer',
            'record_date' => 'date',
            'metadata' => 'array',
        ];
    }

    /**
     * Variance between system-recorded and operator-reported quantities.
     * Returns signed percentage: positive = system > operator, negative = system < operator.
     * Returns null when system_quantity is not set.
     */
    public function getSystemVariancePercentageAttribute(): ?float
    {
        if ($this->system_quantity === null || $this->system_quantity == 0) {
            return null;
        }

        return round(
            (((float) $this->quantity_produced - (float) $this->system_quantity) / (float) $this->system_quantity) * 100,
            1
        );
    }

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /** @return BelongsTo<MineArea, $this> */
    public function mineArea(): BelongsTo
    {
        return $this->belongsTo(MineArea::class);
    }

    /** @return BelongsTo<Machine, $this> */
    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForTeam(Builder $query, int $teamId): Builder
    {
        return $query->where('team_id', $teamId);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeByStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeBetweenDates(Builder $query, string $startDate, string $endDate): Builder
    {
        $query->whereBetween('record_date', [$startDate, $endDate]);

        return $query;
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForMineArea(Builder $query, int $mineAreaId): Builder
    {
        return $query->where('mine_area_id', $mineAreaId);
    }

    public function getVariancePercentageAttribute(): float
    {
        if (! $this->target_quantity || $this->target_quantity == 0) {
            return 0;
        }

        return (((float) $this->quantity_produced - (float) $this->target_quantity) / (float) $this->target_quantity) * 100;
    }

    public function getIsAboveTargetAttribute(): bool
    {
        if (! $this->target_quantity) {
            return false;
        }

        return $this->quantity_produced >= $this->target_quantity;
    }
}
