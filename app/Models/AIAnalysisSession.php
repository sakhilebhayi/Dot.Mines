<?php

namespace App\Models;

use App\Traits\HasTeamFilters;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AIAnalysisSession extends Model
{
    use HasFactory, HasTeamFilters;

    protected $table = 'ai_analysis_sessions';

    protected $fillable = [
        'team_id',
        'ai_agent_id',
        'user_id',
        'analysis_type',
        'status',
        'input_parameters',
        'results',
        'recommendations_generated',
        'processing_time_ms',
        'started_at',
        'completed_at',
        'error_message',
    ];

    protected $casts = [
        'input_parameters' => 'array',
        'results' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function aiAgent(): BelongsTo
    {
        return $this->belongsTo(AIAgent::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function markAsCompleted(array $results, int $recommendationsGenerated): void
    {
        // Carbon 3 diffs are signed floats: now()->diffInMilliseconds($past)
        // is NEGATIVE, and processing_time_ms is an integer column, so the
        // old expression made this update fail on Postgres (SQLSTATE 22P02).
        $this->update([
            'status' => 'completed',
            'results' => $results,
            'recommendations_generated' => $recommendationsGenerated,
            'completed_at' => now(),
            'processing_time_ms' => $this->started_at ? (int) $this->started_at->diffInMilliseconds(now()) : null,
        ]);
    }

    public function markAsFailed(string $errorMessage): void
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $errorMessage,
            'completed_at' => now(),
        ]);
    }
}
