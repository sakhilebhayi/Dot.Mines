<?php

namespace App\Models;

use App\Traits\HasTeamFilters;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AiRecommendationAction extends Model
{
    use HasFactory, HasTeamFilters;

    protected $table = 'ai_recommendation_actions';

    protected $fillable = [
        'team_id',
        'recommendation_hash',
        'recommendation',
        'status',
        'actioned_by',
        'actioned_at',
        'reject_reason',
        'performance_impact',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'recommendation' => 'json',
            'performance_impact' => 'json',
            'actioned_at' => 'datetime',
        ];
    }
}
