<?php

namespace App\Models;

use App\Traits\HasTeamFilters;
use Carbon\Carbon;
use Database\Factories\AIPredictiveAlertFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read AIAgent|null $aiAgent
 * @property int $id
 * @property string $severity
 * @property string $title
 * @property string|null $description
 * @property Carbon $created_at
 * @property-read Machine|null $machine
 */
class AIPredictiveAlert extends Model
{
    /** @use HasFactory<AIPredictiveAlertFactory> */
    use HasFactory, HasTeamFilters;

    protected $table = 'ai_predictive_alerts';

    /** @var array<int, string> */
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

    /** @var array<string, string> */
    protected $casts = [
        'predictions' => 'array',
        'probability' => 'float',
        'predicted_occurrence' => 'datetime',
        'recommended_actions' => 'array',
        'is_acknowledged' => 'boolean',
        'acknowledged_at' => 'datetime',
        'was_accurate' => 'boolean',
    ];

    /** @return BelongsTo<Team,$this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /** @return BelongsTo<AIAgent,$this> */
    public function aiAgent(): BelongsTo
    {
        return $this->belongsTo(AIAgent::class);
    }

    /** @return BelongsTo<Machine,$this> */
    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class, 'related_machine_id');
    }

    /** @return BelongsTo<User,$this> */
    public function acknowledger(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeUnacknowledged($query)
    {
        return $query->where('is_acknowledged', false);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeCritical($query)
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
        $this->aiAgent?->updateAccuracy($wasAccurate);
    }
}
