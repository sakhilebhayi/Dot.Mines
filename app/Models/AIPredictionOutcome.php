<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Records an AI agent's prediction and the eventual real-world outcome.
 *
 * Used by the MEGA V2 "AI Reliability" and "AI Drift Control" scoring domains
 * to calculate accuracy, false positive rate, and model drift over time.
 *
 * @property int $id
 * @property string $agent_type
 * @property string $prediction_type
 * @property int $team_id
 * @property int|null $machine_id
 * @property array<mixed> $predicted_value
 * @property Carbon $predicted_at
 * @property float|null $confidence_score
 * @property array<mixed>|null $actual_value
 * @property Carbon|null $outcome_recorded_at
 * @property float|null $accuracy_score
 * @property bool $false_positive
 * @property bool $false_negative
 * @property string|null $notes
 * @property array<mixed>|null $metadata
 */
class AIPredictionOutcome extends Model
{
    protected $table = 'ai_prediction_outcomes';

    protected $fillable = [
        'agent_type',
        'prediction_type',
        'team_id',
        'machine_id',
        'predicted_value',
        'predicted_at',
        'confidence_score',
        'actual_value',
        'outcome_recorded_at',
        'accuracy_score',
        'false_positive',
        'false_negative',
        'notes',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'predicted_value' => 'array',
            'actual_value' => 'array',
            'metadata' => 'array',
            'predicted_at' => 'datetime',
            'outcome_recorded_at' => 'datetime',
            'confidence_score' => 'float',
            'accuracy_score' => 'float',
            'false_positive' => 'boolean',
            'false_negative' => 'boolean',
        ];
    }

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Record an outcome and calculate accuracy score.
     *
     * @param  array<mixed>  $actualValue
     */
    public function recordOutcome(array $actualValue, float $accuracyScore, bool $falsePositive = false, bool $falseNegative = false): static
    {
        $this->update([
            'actual_value' => $actualValue,
            'outcome_recorded_at' => now(),
            'accuracy_score' => $accuracyScore,
            'false_positive' => $falsePositive,
            'false_negative' => $falseNegative,
        ]);

        return $this;
    }

    /**
     * Scope to predictions that have a recorded outcome (evaluable predictions).
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeEvaluated(Builder $query): Builder // @phpstan-ignore-line
    {
        return $query->whereNotNull('accuracy_score'); // @phpstan-ignore-line
    }
}
