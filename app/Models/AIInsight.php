<?php

namespace App\Models;

use App\Traits\HasTeamFilters;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AIInsight extends Model
{
    use HasFactory, HasTeamFilters;

    protected $table = 'ai_insights';

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

    protected $casts = [
        'data' => 'array',
        'visualization_data' => 'array',
        'is_read' => 'boolean',
        'valid_until' => 'datetime',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    public function scopeValid($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('valid_until')
                ->orWhere('valid_until', '>', now());
        });
    }

    public function scopeBySeverity($query, string $severity)
    {
        return $query->where('severity', $severity);
    }

    public function markAsRead(): void
    {
        $this->update(['is_read' => true]);
    }
}
