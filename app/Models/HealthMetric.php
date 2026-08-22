<?php

namespace App\Models;

use App\Traits\HasTeamFilters;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
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
 * @property mixed|null $deviation
 */
class HealthMetric extends Model
{
    use HasTeamFilters;

    /** @var list<string> */
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

    /** @var array<string, string> */
    protected $casts = [
        'value' => 'float',
        'normal_min' => 'float',
        'normal_max' => 'float',
        'is_normal' => 'boolean',
        'recorded_at' => 'datetime',
    ];

    /**
     * Relationships
     *
     * @return BelongsTo<Team,$this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /** @return BelongsTo<Machine,$this> */
    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }

    /**
     * Scopes
     */
    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeAbnormal($query)
    {
        return $query->where('is_normal', false);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeCritical($query)
    {
        return $query->where('severity', 'critical');
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeComponent($query, string $component)
    {
        return $query->where('component', $component);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeMetricType($query, string $type)
    {
        return $query->where('metric_type', $type);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeRecent($query, int $days = 7)
    {
        return $query->where('recorded_at', '>=', now()->subDays($days));
    }

    /**
     * Get deviation from normal
     */
    protected function getDeviationAttribute(): ?float
    {
        if (($this->normal_min === null || $this->normal_min === 0.0) || ($this->normal_max === null || $this->normal_max === 0.0)) {
            return null;
        }

        $normalMid = ($this->normal_min + $this->normal_max) / 2;

        return $this->value - $normalMid;
    }

    /**
     * Get deviation percentage
     */
    protected function getDeviationPercentageAttribute(): ?float
    {
        if (($this->normal_min === null || $this->normal_min === 0.0) || ($this->normal_max === null || $this->normal_max === 0.0)) {
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
