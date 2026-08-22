<?php

namespace App\Models;

use App\Traits\HasTeamFilters;
use Database\Factories\AIInsightFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AIInsight extends Model
{
    /** @use HasFactory<AIInsightFactory> */
    use HasFactory, HasTeamFilters;

    protected $table = 'ai_insights';

    /** @var list<string> */
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

    /** @var array<string, string> */
    protected $casts = [
        'data' => 'array',
        'visualization_data' => 'array',
        'is_read' => 'boolean',
        'valid_until' => 'datetime',
    ];

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeValid($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('valid_until')
                ->orWhere('valid_until', '>', now());
        });
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeBySeverity($query, string $severity)
    {
        return $query->where('severity', $severity);
    }

    public function markAsRead(): void
    {
        $this->update(['is_read' => true]);
    }
}
