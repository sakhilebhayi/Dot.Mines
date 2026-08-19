<?php

namespace App\Models;

use Carbon\Carbon;
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
 * @property string|Carbon $record_date
 * @property string $shift
 * @property string|float $quantity_produced
 * @property string $unit
 * @property string|float $target_quantity
 * @property string|null $notes
 * @property string $status
 * @property array|null $metadata
 * @property float $variance_percentage
 * @property bool $is_above_target
 * @property Carbon|null $deleted_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
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
        'unit',
        'target_quantity',
        'notes',
        'status',
        'metadata',
    ];

    protected $casts = [
        'quantity_produced' => 'decimal:2',
        'target_quantity' => 'decimal:2',
        'record_date' => 'date',
        'metadata' => 'array',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function mineArea(): BelongsTo
    {
        return $this->belongsTo(MineArea::class);
    }

    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }

    public function scopeForTeam($query, $teamId)
    {
        return $query->where('team_id', $teamId);
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeBetweenDates($query, $startDate, $endDate)
    {
        return $query->whereBetween('record_date', [$startDate, $endDate]);
    }

    public function scopeForMineArea($query, $mineAreaId)
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
