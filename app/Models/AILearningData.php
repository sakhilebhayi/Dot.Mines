<?php

namespace App\Models;

use App\Traits\HasTeamFilters;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AILearningData extends Model
{
    use HasFactory, HasTeamFilters;

    protected $table = 'ai_learning_data';

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

    protected $casts = [
        'input_data' => 'array',
        'predicted_output' => 'array',
        'actual_output' => 'array',
        'accuracy' => 'float',
        'was_accurate' => 'boolean',
    ];

    public function aiAgent(): BelongsTo
    {
        return $this->belongsTo(AIAgent::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function recommendation(): BelongsTo
    {
        return $this->belongsTo(AIRecommendation::class);
    }
}
