<?php

namespace App\Models;

use App\Traits\HasTeamFilters;
use Carbon\Carbon;
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
 *
 * @method static \Illuminate\Database\Eloquent\Builder|HealthMetric where(string $column, mixed $operator = null, mixed $value = null)
 * @method static \Illuminate\Database\Eloquent\Builder|HealthMetric whereIn(string $column, array<string|int> $values)
 * @method static \Illuminate\Database\Eloquent\Builder|HealthMetric orderBy(string $column, string $direction = 'asc')
 * @method static HealthMetric|null find(mixed $id, array<string> $columns = ['*'])
 * @method static HealthMetric findOrFail(mixed $id, array<string> $columns = ['*'])
 * @method static \Illuminate\Database\Eloquent\Collection<int,HealthMetric> all(array<string> $columns = ['*'])
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

    protected $casts = [
        'value' => 'float',
        'normal_min' => 'float',
        'normal_max' => 'float',
        'is_normal' => 'boolean',
        'recorded_at' => 'datetime',
    ];

    /**
     * Relationships
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }

    /**
     * Scopes
     */
    public function scopeAbnormal($query)
    {
        return $query->where('is_normal', false);
    }

    public function scopeCritical($query)
    {
        return $query->where('severity', 'critical');
    }

    public function scopeComponent($query, string $component)
    {
        return $query->where('component', $component);
    }

    public function scopeMetricType($query, string $type)
    {
        return $query->where('metric_type', $type);
    }

    public function scopeRecent($query, int $days = 7)
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

        $deviation = abs($this->deviation);

        return ($deviation / $normalRange) * 100;
    }
}
