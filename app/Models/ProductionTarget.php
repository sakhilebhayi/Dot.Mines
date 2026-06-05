<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * ProductionTarget Model
 *
 * @property int $id
 * @property int $team_id
 * @property int $mine_area_id
 * @property string $period_type
 * @property string|Carbon $start_date
 * @property string|Carbon $end_date
 * @property string|float $target_quantity
 * @property string $unit
 * @property string|null $description
 * @property bool $is_active
 * @property Carbon|null $deleted_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class ProductionTarget extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'team_id',
        'mine_area_id',
        'period_type',
        'start_date',
        'end_date',
        'target_quantity',
        'unit',
        'description',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'target_quantity' => 'decimal:2',
            'start_date' => 'date',
            'end_date' => 'date',
            'is_active' => 'boolean',
        ];
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

    public function scopeForTeam(Builder $query, int $teamId): Builder
    {
        return $query->where('team_id', $teamId);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeByPeriod(Builder $query, string $periodType): Builder
    {
        return $query->where('period_type', $periodType);
    }
}
