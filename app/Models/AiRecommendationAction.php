<?php

namespace App\Models;

use App\Traits\HasTeamFilters;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiRecommendationAction extends Model
{
    use HasFactory, HasTeamFilters;

    protected $table = 'ai_recommendation_actions';

    protected $fillable = [
        'team_id',
        'ai_recommendation_id',
        'recommendation_hash',
        'recommendation',
        'status',
        'actioned_by',
        'actioned_at',
        'reject_reason',
        'performance_impact',
    ];

    protected $casts = [
        'recommendation' => 'json',
        'performance_impact' => 'json',
        'actioned_at' => 'datetime',
    ];

    public function aiRecommendation(): BelongsTo
    {
        return $this->belongsTo(AIRecommendation::class);
    }

    public function actionedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actioned_by');
    }
}
