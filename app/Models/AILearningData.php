<?php

namespace App\Models;

use App\Traits\HasTeamFilters;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AILearningData extends Model
{
    use HasTeamFilters;

    protected $table = 'ai_learning_data';

    /** @var list<string> */
    protected $fillable = [
        'ai_agent_id',
        'team_id',
        'recommendation_id',
        'data_type',
        'input_data',
        'predicted_output',
        'actual_output',
        'accuracy',
        'was_accurate',
        'notes',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'input_data' => 'array',
        'predicted_output' => 'array',
        'actual_output' => 'array',
        'accuracy' => 'float',
        'was_accurate' => 'boolean',
    ];

    /** @return BelongsTo<AIAgent,$this> */
    public function aiAgent(): BelongsTo
    {
        return $this->belongsTo(AIAgent::class);
    }

    /** @return BelongsTo<Team,$this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /** @return BelongsTo<AIRecommendation,$this> */
    public function recommendation(): BelongsTo
    {
        return $this->belongsTo(AIRecommendation::class);
    }
}
