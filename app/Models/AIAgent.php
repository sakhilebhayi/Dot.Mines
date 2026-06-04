<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * AIAgent Model
 *
 * @property int $id
 * @property string $name
 * @property string $type
 * @property string|null $description
 * @property string $status
 * @property array<string, mixed>|null $configuration
 * @property array<string, mixed>|null $capabilities
 * @property float $accuracy_score
 * @property int $predictions_made
 * @property int $successful_predictions
 * @property \Carbon\Carbon|null $last_trained_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder|AIAgent where(string $column, mixed $operator = null, mixed $value = null)
 * @method static \Illuminate\Database\Eloquent\Builder|AIAgent whereIn(string $column, array $values)
 * @method static \Illuminate\Database\Eloquent\Builder|AIAgent orderBy(string $column, string $direction = 'asc')
 * @method static AIAgent|null find(mixed $id, array $columns = ['*'])
 * @method static AIAgent findOrFail(mixed $id, array $columns = ['*'])
 * @method static \Illuminate\Database\Eloquent\Collection all(array $columns = ['*'])
 */
class AIAgent extends Model
{
    use HasFactory;

    protected $table = 'ai_agents';

    protected $fillable = [
        'name',
        'type',
        'description',
        'status',
        'configuration',
        'capabilities',
        'accuracy_score',
        'predictions_made',
        'successful_predictions',
        'last_trained_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'configuration' => 'array',
            'capabilities' => 'array',
            'accuracy_score' => 'float',
            'last_trained_at' => 'datetime',
        ];
    }

    // Agent types
    const TYPE_FLEET_OPTIMIZER = 'fleet_optimizer';

    const TYPE_ROUTE_ADVISOR = 'route_advisor';

    const TYPE_FUEL_PREDICTOR = 'fuel_predictor';

    const TYPE_MAINTENANCE_PREDICTOR = 'maintenance_predictor';

    const TYPE_PRODUCTION_OPTIMIZER = 'production_optimizer';

    const TYPE_COST_ANALYZER = 'cost_analyzer';

    const TYPE_ANOMALY_DETECTOR = 'anomaly_detector';

    public function recommendations(): HasMany
    {
        return $this->hasMany(AIRecommendation::class);
    }

    public function analysisSessions(): HasMany
    {
        return $this->hasMany(AIAnalysisSession::class);
    }

    public function learningData(): HasMany
    {
        return $this->hasMany(AILearningData::class);
    }

    public function predictiveAlerts(): HasMany
    {
        return $this->hasMany(AIPredictiveAlert::class);
    }

    public function updateAccuracy(bool $wasSuccessful): void
    {
        $this->increment('predictions_made');
        if ($wasSuccessful) {
            $this->increment('successful_predictions');
        }

        $this->accuracy_score = $this->predictions_made > 0
            ? $this->successful_predictions / $this->predictions_made
            : 0;
        $this->save();
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopeByType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }
}
