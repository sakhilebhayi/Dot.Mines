<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ProductionForecast Model
 *
 * @property int $id
 * @property int $team_id
 * @property int $mine_area_id
 * @property string|Carbon $forecast_date
 * @property string|float $forecasted_quantity
 * @property string $unit
 * @property string|float $confidence_level
 * @property array|null $forecast_method
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder|ProductionForecast where(string $column, mixed $operator = null, mixed $value = null)
 * @method static \Illuminate\Database\Eloquent\Builder|ProductionForecast whereIn(string $column, array $values)
 * @method static \Illuminate\Database\Eloquent\Builder|ProductionForecast orderBy(string $column, string $direction = 'asc')
 * @method static ProductionForecast|null find(mixed $id, array $columns = ['*'])
 * @method static ProductionForecast findOrFail(mixed $id, array $columns = ['*'])
 * @method static \Illuminate\Database\Eloquent\Collection all(array $columns = ['*'])
 */
class ProductionForecast extends Model
{
    protected $fillable = [
        'team_id',
        'mine_area_id',
        'forecast_date',
        'forecasted_quantity',
        'unit',
        'confidence_level',
        'forecast_method',
    ];

    protected $casts = [
        'forecasted_quantity' => 'decimal:2',
        'confidence_level' => 'decimal:2',
        'forecast_date' => 'date',
        'forecast_method' => 'array',
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

    public function scopeForDate($query, $date)
    {
        return $query->where('forecast_date', $date);
    }

    public function scopeHighConfidence($query, $threshold = 80)
    {
        return $query->where('confidence_level', '>=', $threshold);
    }
}
