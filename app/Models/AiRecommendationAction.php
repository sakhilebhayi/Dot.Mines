<?php

namespace App\Models;

use App\Traits\HasTeamFilters;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * AiRecommendationAction Model
 *
 * @property Carbon|null $actioned_at
 * @property mixed|null $actioned_by
 * @property Carbon $created_at
 * @property int $id
 * @property array<string, mixed>|null $performance_impact
 * @property array<string, mixed> $recommendation
 * @property string $recommendation_hash
 * @property string|null $reject_reason
 * @property string $status
 * @property mixed $team_id
 * @property Carbon $updated_at
 */
class AiRecommendationAction extends Model
{
    use HasFactory, HasTeamFilters;

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
            'recommendation' => 'array',
            'performance_impact' => 'array',
            'actioned_at' => 'datetime',
        ];
    }
}
