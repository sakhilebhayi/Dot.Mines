<?php

namespace App\Models;

use App\Traits\HasSyncVersion;
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
 * @property string|Carbon $record_date
 * @property string $shift
 * @property string|float $quantity_produced
 * @property string $unit
 * @property string|float $target_quantity
 * @property string|null $notes
 * @property string $status
 * @property array<string, mixed>|null $metadata
 * @property float $variance_percentage
 * @property bool $is_above_target
 * @property Carbon|null $deleted_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property int|null $sync_version
 */
class ProductionRecord extends Model
{
    use HasSyncVersion;
    use SoftDeletes;

    /** @var list<string> */
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

    /** @var array<string, string> */
    protected $casts = [
        'quantity_produced' => 'decimal:2',
        'target_quantity' => 'decimal:2',
        'record_date' => 'date',
        'metadata' => 'array',
    ];

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
    public function scopeForTeam($query, int $teamId)
    {
        return $query->where('team_id', $teamId);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeBetweenDates($query, string|\DateTimeInterface $startDate, string|\DateTimeInterface $endDate)
    {
        $query->whereBetween('record_date', [$startDate, $endDate]);

        return $query;
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForMineArea($query, int $mineAreaId)
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
