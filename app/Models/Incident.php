<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $team_id
 * @property int|null $machine_id
 * @property int|null $mine_area_id
 * @property int|null $reported_by
 * @property int|null $resolved_by
 * @property string $category safety|mechanical|delay|environmental|equipment_damage|near_miss|other
 * @property string $severity low|medium|high|critical
 * @property string $title
 * @property string $description
 * @property \Carbon\Carbon $occurred_at
 * @property string $status open|investigating|resolved|closed
 * @property string|null $resolution_notes
 * @property \Carbon\Carbon|null $resolved_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class Incident extends Model
{
    use HasFactory;

    public const CATEGORIES = [
        'safety' => 'Safety',
        'mechanical' => 'Mechanical',
        'delay' => 'Delay',
        'environmental' => 'Environmental',
        'equipment_damage' => 'Equipment Damage',
        'near_miss' => 'Near Miss',
        'other' => 'Other',
    ];

    public const SEVERITIES = [
        'low' => 'Low',
        'medium' => 'Medium',
        'high' => 'High',
        'critical' => 'Critical',
    ];

    public const STATUSES = [
        'open' => 'Open',
        'investigating' => 'Investigating',
        'resolved' => 'Resolved',
        'closed' => 'Closed',
    ];

    protected $fillable = [
        'team_id',
        'machine_id',
        'mine_area_id',
        'reported_by',
        'resolved_by',
        'category',
        'severity',
        'title',
        'description',
        'occurred_at',
        'status',
        'resolution_notes',
        'resolved_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    // ── Relationships ──────────────────────────────────────────────────────────

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }

    public function mineArea(): BelongsTo
    {
        return $this->belongsTo(MineArea::class);
    }

    public function reportedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    public function isResolved(): bool
    {
        return in_array($this->status, ['resolved', 'closed']);
    }

    public function severityColor(): string
    {
        return match ($this->severity) {
            'critical' => 'red',
            'high' => 'orange',
            'medium' => 'amber',
            default => 'slate',
        };
    }

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category] ?? ucfirst($this->category);
    }
}
