<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
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
 * @property array<string, mixed>|null $forecast_method
 * @property Carbon $created_at
 * @property Carbon $updated_at
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

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'forecasted_quantity' => 'decimal:2',
            'confidence_level' => 'decimal:2',
            'forecast_date' => 'date',
            'forecast_method' => 'array',
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

    public function scopeForDate(Builder $query, string $date): Builder
    {
        return $query->where('forecast_date', $date);
    }

    public function scopeHighConfidence(Builder $query, int $threshold = 80): Builder
    {
        return $query->where('confidence_level', '>=', $threshold);
    }
}
