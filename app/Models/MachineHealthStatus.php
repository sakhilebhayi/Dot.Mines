<?php

namespace App\Models;

use App\Traits\HasTeamFilters;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MachineHealthStatus extends Model
{
    use HasFactory, HasTeamFilters;

    protected $table = 'machine_health_status';

    /**
     * MachineHealthStatus Model
     *
     * @property int $id
     * @property int $team_id
     * @property int $machine_id
     * @property int $overall_health_score
     * @property string $health_status
     * @property array|null $component_scores
     * @property int|null $engine_health
     * @property int|null $transmission_health
     * @property int|null $hydraulics_health
     * @property int|null $electrical_health
     * @property int|null $brakes_health
     * @property int|null $cooling_system_health
     * @property \Carbon\Carbon|null $last_diagnostic_scan
     * @property array|null $active_fault_codes
     * @property int $fault_code_count
     * @property string|null $recommendations
     * @property \Carbon\Carbon $created_at
     * @property \Carbon\Carbon $updated_at
     *
     * @method static \Illuminate\Database\Eloquent\Builder|MachineHealthStatus where(string $column, mixed $operator = null, mixed $value = null)
     * @method static \Illuminate\Database\Eloquent\Builder|MachineHealthStatus whereIn(string $column, array $values)
     * @method static \Illuminate\Database\Eloquent\Builder|MachineHealthStatus orderBy(string $column, string $direction = 'asc')
     * @method static MachineHealthStatus|null find(mixed $id, array $columns = ['*'])
     * @method static MachineHealthStatus findOrFail(mixed $id, array $columns = ['*'])
     * @method static \Illuminate\Database\Eloquent\Collection all(array $columns = ['*'])
     */
    protected $fillable = [
        'team_id',
        'machine_id',
        'overall_health_score',
        'health_status',
        'component_scores',
        'engine_health',
        'transmission_health',
        'hydraulics_health',
        'electrical_health',
        'brakes_health',
        'cooling_system_health',
        'last_diagnostic_scan',
        'active_fault_codes',
        'fault_code_count',
        'recommendations',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'component_scores' => 'json',
            'active_fault_codes' => 'json',
            'last_diagnostic_scan' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }

    public function healthMetrics(): HasMany
    {
        return $this->hasMany(HealthMetric::class, 'machine_id', 'machine_id');
    }

    /**
     * Calculate overall health score from components
     */
    public function calculateHealthScore(): int
    {
        $components = [
            $this->engine_health,
            $this->transmission_health,
            $this->hydraulics_health,
            $this->electrical_health,
            $this->brakes_health,
            $this->cooling_system_health,
        ];

        $validComponents = array_filter($components, fn ($val) => ! is_null($val));

        if (empty($validComponents)) {
            return 100;
        }

        return (int) round(array_sum($validComponents) / count($validComponents));
    }

    /**
     * Determine health status from score
     */
    public function determineHealthStatus(): string
    {
        return match (true) {
            $this->overall_health_score >= 90 => 'excellent',
            $this->overall_health_score >= 75 => 'good',
            $this->overall_health_score >= 60 => 'fair',
            $this->overall_health_score >= 40 => 'poor',
            default => 'critical',
        };
    }

    /**
     * Check if maintenance is needed
     */
    public function needsMaintenance(): bool
    {
        return $this->overall_health_score < 70 || $this->fault_code_count > 0;
    }

    /**
     * Scope for machines needing attention
     */
    public function scopeNeedsAttention(Builder $query): Builder
    {
        return $query->where(function ($q) {
            $q->where('overall_health_score', '<', 70)
                ->orWhere('fault_code_count', '>', 0);
        });
    }

    /**
     * Scope for critical health
     */
    public function scopeCritical(Builder $query): Builder
    {
        return $query->where('health_status', 'critical')
            ->orWhere('overall_health_score', '<', 40);
    }
}
