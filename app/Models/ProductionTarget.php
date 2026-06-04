<?php

namespace App\Models;

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
 * @property string|\Carbon\Carbon $start_date
 * @property string|\Carbon\Carbon $end_date
 * @property string|float $target_quantity
 * @property string $unit
 * @property string|null $description
 * @property bool $is_active
 * @property \Carbon\Carbon|null $deleted_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder|ProductionTarget where(string $column, mixed $operator = null, mixed $value = null)
 * @method static \Illuminate\Database\Eloquent\Builder|ProductionTarget whereIn(string $column, array $values)
 * @method static \Illuminate\Database\Eloquent\Builder|ProductionTarget orderBy(string $column, string $direction = 'asc')
 * @method static ProductionTarget|null find(mixed $id, array $columns = ['*'])
 * @method static ProductionTarget findOrFail(mixed $id, array $columns = ['*'])
 * @method static \Illuminate\Database\Eloquent\Collection all(array $columns = ['*'])
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
