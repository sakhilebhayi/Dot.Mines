<?php

namespace App\Models;

use App\Traits\HasTeamFilters;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AIPredictiveAlert extends Model
{
    use HasFactory, HasTeamFilters;

    protected $table = 'ai_predictive_alerts';

    protected $fillable = [
        'team_id',
        'ai_agent_id',
        'alert_type',
        'severity',
        'title',
        'description',
        'predictions',
        'probability',
        'predicted_occurrence',
        'recommended_actions',
        'related_machine_id',
        'related_mine_area_id',
        'is_acknowledged',
        'acknowledged_by',
        'acknowledged_at',
        'was_accurate',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'predictions' => 'array',
            'probability' => 'float',
            'predicted_occurrence' => 'datetime',
            'recommended_actions' => 'array',
            'is_acknowledged' => 'boolean',
            'acknowledged_at' => 'datetime',
            'was_accurate' => 'boolean',
        ];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function aiAgent(): BelongsTo
    {
        return $this->belongsTo(AIAgent::class);
    }

    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class, 'related_machine_id');
    }

    public function acknowledger(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }

    public function scopeUnacknowledged(Builder $query): Builder
    {
        return $query->where('is_acknowledged', false);
    }

    public function scopeCritical(Builder $query): Builder
    {
        return $query->where('severity', 'critical');
    }

    public function acknowledge(User $user): void
    {
        $this->update([
            'is_acknowledged' => true,
            'acknowledged_by' => $user->id,
            'acknowledged_at' => now(),
        ]);
    }

    public function recordAccuracy(bool $wasAccurate): void
    {
        $this->update(['was_accurate' => $wasAccurate]);
        $this->aiAgent->updateAccuracy($wasAccurate);
    }
}
