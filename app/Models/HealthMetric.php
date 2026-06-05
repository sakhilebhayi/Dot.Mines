<?php

namespace App\Models;

use App\Traits\HasTeamFilters;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * HealthMetric Model
 *
 * @property int $id
 * @property int $team_id
 * @property int $machine_id
 * @property string $component
 * @property string $metric_type
 * @property float $value
 * @property string $unit
 * @property float|null $normal_min
 * @property float|null $normal_max
 * @property bool $is_normal
 * @property string|null $severity
 * @property string|null $sensor_id
 * @property Carbon $recorded_at
 * @property string|null $notes
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read float|null $deviation
 * @property-read float|null $deviation_percentage
 */
class HealthMetric extends Model
{
    use HasFactory, HasTeamFilters;

    protected $fillable = [
        'team_id',
        'machine_id',
        'component',
        'metric_type',
        'value',
        'unit',
        'normal_min',
        'normal_max',
        'is_normal',
        'severity',
        'sensor_id',
        'recorded_at',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value' => 'float',
            'normal_min' => 'float',
            'normal_max' => 'float',
            'is_normal' => 'boolean',
            'recorded_at' => 'datetime',
        ];
    }

    /**
     * Relationships
     */
    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /** @return BelongsTo<Machine, $this> */
    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }

    /**
     * Scopes
     */
    public function scopeAbnormal(Builder $query): Builder
    {
        return $query->where('is_normal', false);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeCritical(Builder $query): Builder
    {
        return $query->where('severity', 'critical');
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeComponent(Builder $query, string $component): Builder
    {
        return $query->where('component', $component);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeMetricType(Builder $query, string $type): Builder
    {
        return $query->where('metric_type', $type);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeRecent(Builder $query, int $days = 7): Builder
    {
        return $query->where('recorded_at', '>=', now()->subDays($days));
    }

    /**
     * Get deviation from normal
     */
    public function getDeviationAttribute(): ?float
    {
        if (! $this->normal_min || ! $this->normal_max) {
            return null;
        }

        $normalMid = ($this->normal_min + $this->normal_max) / 2;

        return $this->value - $normalMid;
    }

    /**
     * Get deviation percentage
     */
    public function getDeviationPercentageAttribute(): ?float
    {
        if (! $this->normal_min || ! $this->normal_max) {
            return null;
        }

        $normalRange = $this->normal_max - $this->normal_min;
        if ($normalRange == 0) {
            return 0;
        }

        $deviation = abs($this->deviation ?? 0.0);

        return ($deviation / $normalRange) * 100;
    }
}
