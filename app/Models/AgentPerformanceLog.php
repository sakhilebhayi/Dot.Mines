<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Captures every agent operation with outcome, confidence, and performance metadata.
 *
 * Used by the MEGA V2 "Agent Reliability" and "Agent Collaboration" scoring domains.
 *
 * @property int $id
 * @property string $agent_name
 * @property string $operation
 * @property string $status 'success'|'failure'|'partial'
 * @property float|null $confidence_score
 * @property int $evidence_count
 * @property int $finding_count
 * @property int|null $execution_time_ms
 * @property bool $is_false_positive
 * @property bool $is_false_negative
 * @property string|null $summary
 * @property array<mixed>|null $metadata
 * @property Carbon $created_at
 */
class AgentPerformanceLog extends Model
{
    protected $table = 'agent_performance_logs';

    protected $fillable = [
        'agent_name',
        'operation',
        'status',
        'confidence_score',
        'evidence_count',
        'finding_count',
        'execution_time_ms',
        'is_false_positive',
        'is_false_negative',
        'summary',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'confidence_score' => 'float',
            'is_false_positive' => 'boolean',
            'is_false_negative' => 'boolean',
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
