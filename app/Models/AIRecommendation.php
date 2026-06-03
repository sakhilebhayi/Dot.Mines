<?php

namespace App\Models;

use App\Traits\HasTeamFilters;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AIRecommendation extends Model
{
    use HasFactory, HasTeamFilters;

    protected $table = 'ai_recommendations';

    protected $fillable = [
        'team_id',
        'ai_agent_id',
        'user_id',
        'category',
        'priority',
        'status',
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
        'implemented_at',
        'implemented_by',
        'implementation_notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'data' => 'array',
            'impact_analysis' => 'array',
            'confidence_score' => 'float',
            'estimated_savings' => 'decimal:2',
            'estimated_efficiency_gain' => 'decimal:2',
            'implemented_at' => 'datetime',
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class, 'related_machine_id');
    }

    public function route(): BelongsTo
    {
        return $this->belongsTo(Route::class, 'related_route_id');
    }

    public function mineArea(): BelongsTo
    {
        return $this->belongsTo(MineArea::class, 'related_mine_area_id');
    }

    public function implementer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'implemented_by');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopeHighPriority(Builder $query): Builder
    {
        return $query->whereIn('priority', ['critical', 'high']);
    }

    public function scopeByCategory(Builder $query, string $category): Builder
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
