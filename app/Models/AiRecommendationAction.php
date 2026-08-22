<?php

namespace App\Models;

use App\Traits\HasTeamFilters;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiRecommendationAction extends Model
{
    use HasTeamFilters;

    protected $table = 'ai_recommendation_actions';

    /** @var list<string> */
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

    /** @var array<string, string> */
    protected $casts = [
        'recommendation' => 'json',
        'performance_impact' => 'json',
        'actioned_at' => 'datetime',
    ];

    /** @return BelongsTo<AIRecommendation, $this> */
    public function aiRecommendation(): BelongsTo
    {
        return $this->belongsTo(AIRecommendation::class);
    }

    /** @return BelongsTo<User, $this> */
    public function actionedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actioned_by');
    }
}
