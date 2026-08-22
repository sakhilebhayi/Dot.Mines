<?php

namespace App\Models;

use App\Traits\HasTeamFilters;
use Database\Factories\AIRecommendationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string|null $status
 * @property int $team_id
 * @property-read Team|null $team
 */
class AIRecommendation extends Model
{
    /** @use HasFactory<AIRecommendationFactory> */
    use HasFactory, HasTeamFilters;

    protected $table = 'ai_recommendations';

    /** @var list<string> */
    protected $fillable = [
        'team_id',
        'ai_agent_id',
        'user_id',
        'category',
        'priority',
        'status',
        'title',
        'description',
        'proposed_action',
        'data',
        'impact_analysis',
        'confidence_score',
        'estimated_savings',
        'estimated_efficiency_gain',
        'related_machine_id',
        'related_mine_area_id',
        'related_route_id',
        'implemented_at',
        'implemented_by',
        'implementation_notes',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'data' => 'array',
        'impact_analysis' => 'array',
        'confidence_score' => 'float',
        'estimated_savings' => 'decimal:2',
        'estimated_efficiency_gain' => 'decimal:2',
        'implemented_at' => 'datetime',
    ];

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /** @return BelongsTo<AIAgent, $this> */
    public function aiAgent(): BelongsTo
    {
        return $this->belongsTo(AIAgent::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Machine, $this> */
    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class, 'related_machine_id');
    }

    /** @return BelongsTo<Route, $this> */
    public function route(): BelongsTo
    {
        return $this->belongsTo(Route::class, 'related_route_id');
    }

    /** @return BelongsTo<MineArea, $this> */
    public function mineArea(): BelongsTo
    {
        return $this->belongsTo(MineArea::class, 'related_mine_area_id');
    }

    /** @return BelongsTo<User, $this> */
    public function implementer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'implemented_by');
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeHighPriority($query)
    {
        $query->whereIn('priority', ['critical', 'high']);

        return $query;
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isImplemented(): bool
    {
        return $this->status === 'implemented';
    }

    public function markAsImplemented(User $user, ?string $notes = null): void
    {
        $this->update([
            'status' => 'implemented',
            'implemented_at' => now(),
            'implemented_by' => $user->id,
            'implementation_notes' => $notes,
        ]);
    }
}
