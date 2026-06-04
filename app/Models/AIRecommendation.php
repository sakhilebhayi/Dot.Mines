<?php

namespace App\Models;

use App\Traits\HasTeamFilters;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * AIRecommendation Model
 *
 * @property float|null $accuracy
 * @property float $accuracy_score
 * @property \Carbon\Carbon|null $acknowledged_at
 * @property mixed|null $acknowledged_by
 * @property array|null $actual_output
 * @property mixed $ai_agent_id
 * @property string $alert_type
 * @property string $analysis_type
 * @property array|null $capabilities
 * @property string $category
 * @property \Carbon\Carbon|null $completed_at
 * @property float $confidence_score
 * @property array|null $configuration
 * @property \Carbon\Carbon $created_at
 * @property array|null $data
 * @property string $data_type
 * @property string|null $description
 * @property string|null $error_message
 * @property float|null $estimated_efficiency_gain
 * @property float|null $estimated_savings
 * @property int $id
 * @property array|null $impact_analysis
 * @property string|null $implementation_notes
 * @property \Carbon\Carbon|null $implemented_at
 * @property mixed|null $implemented_by
 * @property array|null $input_data
 * @property array|null $input_parameters
 * @property string $insight_type
 * @property bool $is_acknowledged
 * @property bool $is_read
 * @property \Carbon\Carbon|null $last_trained_at
 * @property string $name
 * @property string|null $notes
 * @property \Carbon\Carbon|null $predicted_occurrence
 * @property array|null $predicted_output
 * @property array|null $predictions
 * @property int $predictions_made
 * @property string $priority
 * @property float $probability
 * @property int|null $processing_time_ms
 * @property mixed|null $recommendation_id
 * @property int $recommendations_generated
 * @property array|null $recommended_actions
 * @property mixed|null $related_machine_id
 * @property mixed|null $related_mine_area_id
 * @property mixed|null $related_route_id
 * @property array|null $results
 * @property string $severity
 * @property \Carbon\Carbon|null $started_at
 * @property string $status
 * @property int $successful_predictions
 * @property mixed $team_id
 * @property string $title
 * @property mixed $type
 * @property \Carbon\Carbon $updated_at
 * @property mixed|null $user_id
 * @property \Carbon\Carbon|null $valid_until
 * @property array|null $visualization_data
 * @property bool|null $was_accurate
 *
 * @method static \Illuminate\Database\Eloquent\Builder|AIRecommendation where(string $column, mixed $operator = null, mixed $value = null)
 * @method static \Illuminate\Database\Eloquent\Builder|AIRecommendation whereIn(string $column, array $values)
 * @method static \Illuminate\Database\Eloquent\Builder|AIRecommendation orderBy(string $column, string $direction = 'asc')
 * @method static AIRecommendation|null find(mixed $id, array $columns = ['*'])
 * @method static AIRecommendation findOrFail(mixed $id, array $columns = ['*'])
 * @method static \Illuminate\Database\Eloquent\Collection all(array $columns = ['*'])
 */
class AIRecommendation extends Model
{
    use HasFactory, HasTeamFilters;

    protected $fillable = [
        'team_id',
        'ai_agent_id',
        'user_id',
        'category',
        'priority',
        'title',
        'description',
        'data',
        'impact_analysis',
        'confidence_score',
        'estimated_savings',
        'estimated_efficiency_gain',
        'related_machine_id',
        'related_mine_area_id',
        'related_route_id',
        'status',
        'is_read',
        'is_acknowledged',
        'acknowledged_by',
        'acknowledged_at',
        'implemented_by',
        'implemented_at',
        'implementation_notes',
        'valid_until',
        'recommended_actions',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'data' => 'array',
            'impact_analysis' => 'array',
            'recommended_actions' => 'array',
            'confidence_score' => 'float',
            'estimated_savings' => 'float',
            'estimated_efficiency_gain' => 'float',
            'is_read' => 'boolean',
            'is_acknowledged' => 'boolean',
            'acknowledged_at' => 'datetime',
            'implemented_at' => 'datetime',
            'valid_until' => 'datetime',
        ];
    }
}
