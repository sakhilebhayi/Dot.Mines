<?php

namespace App\Models;

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
 * @property-read \App\Models\Machine|null $machine
 * @property string|\Carbon\Carbon $record_date
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
 * @property \Carbon\Carbon|null $deleted_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder|ProductionRecord where(string $column, mixed $operator = null, mixed $value = null)
 * @method static \Illuminate\Database\Eloquent\Builder|ProductionRecord whereIn(string $column, array $values)
 * @method static \Illuminate\Database\Eloquent\Builder|ProductionRecord orderBy(string $column, string $direction = 'asc')
 * @method static ProductionRecord|null find(mixed $id, array $columns = ['*'])
 * @method static ProductionRecord findOrFail(mixed $id, array $columns = ['*'])
 * @method static \Illuminate\Database\Eloquent\Collection all(array $columns = ['*'])
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

    /** @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /** @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\MineArea, $this> */
    public function mineArea(): BelongsTo
    {
        return $this->belongsTo(MineArea::class);
    }

    /** @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\Machine, $this> */
    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }

    public function scopeForTeam(Builder $query, int $teamId): Builder
    {
        return $query->where('team_id', $teamId);
    }

    public function scopeByStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    public function scopeBetweenDates(Builder $query, string $startDate, string $endDate): Builder
    {
        return $query->whereBetween('record_date', [$startDate, $endDate]);
    }

    public function scopeForMineArea(Builder $query, int $mineAreaId): Builder
    {
        return $query->where('mine_area_id', $mineAreaId);
    }

    public function getVariancePercentageAttribute(): float
    {
        if (! $this->target_quantity || $this->target_quantity == 0) {
            return 0;
        }

        return (($this->quantity_produced - $this->target_quantity) / $this->target_quantity) * 100;
    }

    public function getIsAboveTargetAttribute(): bool
    {
        if (! $this->target_quantity) {
            return false;
        }

        return $this->quantity_produced >= $this->target_quantity;
    }
}
