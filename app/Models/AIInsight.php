<?php

namespace App\Models;

use App\Traits\HasTeamFilters;
use Carbon\Carbon;
use Database\Factories\AIInsightFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * AIInsight Model
 *
 * @property float|null $accuracy
 * @property float $accuracy_score
 * @property Carbon|null $acknowledged_at
 * @property mixed|null $acknowledged_by
 * @property array<string, mixed>|null $actual_output
 * @property mixed $ai_agent_id
 * @property string $alert_type
 * @property string $analysis_type
 * @property array<string, mixed>|null $capabilities
 * @property string $category
 * @property Carbon|null $completed_at
 * @property float $confidence_score
 * @property array<string, mixed>|null $configuration
 * @property Carbon $created_at
 * @property array<string, mixed>|null $data
 * @property string $data_type
 * @property string|null $description
 * @property string|null $error_message
 * @property float|null $estimated_efficiency_gain
 * @property float|null $estimated_savings
 * @property int $id
 * @property array<string, mixed>|null $impact_analysis
 * @property string|null $implementation_notes
 * @property Carbon|null $implemented_at
 * @property mixed|null $implemented_by
 * @property array<string, mixed>|null $input_data
 * @property array<string, mixed>|null $input_parameters
 * @property string $insight_type
 * @property bool $is_acknowledged
 * @property bool $is_read
 * @property Carbon|null $last_trained_at
 * @property string $name
 * @property string|null $notes
 * @property Carbon|null $predicted_occurrence
 * @property array<string, mixed>|null $predicted_output
 * @property array<string, mixed>|null $predictions
 * @property int $predictions_made
 * @property string $priority
 * @property float $probability
 * @property int|null $processing_time_ms
 * @property mixed|null $recommendation_id
 * @property int $recommendations_generated
 * @property array<string, mixed>|null $recommended_actions
 * @property mixed|null $related_machine_id
 * @property mixed|null $related_mine_area_id
 * @property mixed|null $related_route_id
 * @property array<string, mixed>|null $results
 * @property string $severity
 * @property Carbon|null $started_at
 * @property string $status
 * @property int $successful_predictions
 * @property mixed $team_id
 * @property string $title
 * @property mixed $type
 * @property Carbon $updated_at
 * @property mixed|null $user_id
 * @property Carbon|null $valid_until
 * @property array<string, mixed>|null $visualization_data
 * @property bool|null $was_accurate
 */
class AIInsight extends Model
{
    /** @use HasFactory<AIInsightFactory> */
    use HasFactory, HasTeamFilters;

    protected $fillable = [
        'team_id',
        'insight_type',
        'category',
        'severity',
        'title',
        'description',
        'data',
        'visualization_data',
        'is_read',
        'valid_until',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'data' => 'array',
            'visualization_data' => 'array',
            'is_read' => 'boolean',
            'valid_until' => 'datetime',
        ];
    }
}
