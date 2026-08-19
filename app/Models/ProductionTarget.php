<?php

namespace App\Models;

use Carbon\Carbon;
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

    protected $casts = [
        'target_quantity' => 'decimal:2',
        'start_date' => 'date',
        'end_date' => 'date',
        'is_active' => 'boolean',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function mineArea(): BelongsTo
    {
        return $this->belongsTo(MineArea::class);
    }

    public function scopeForTeam($query, $teamId)
    {
        return $query->where('team_id', $teamId);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByPeriod($query, $periodType)
    {
        return $query->where('period_type', $periodType);
    }
}
