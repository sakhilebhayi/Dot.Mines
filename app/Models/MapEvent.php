<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $team_id
 * @property int|null $machine_id
 * @property int|null $mine_area_id
 * @property string $event_type
 * @property string $title
 * @property string|null $notes
 * @property float|null $latitude
 * @property float|null $longitude
 * @property \Carbon\Carbon $occurred_at
 * @property \Carbon\Carbon|null $resolved_at
 * @property array<string, mixed>|null $metadata
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read Machine|null  $machine
 * @property-read MineArea|null $mineArea
 * @property-read string        $icon_emoji
 * @property-read string        $color_hex
 */
class MapEvent extends Model
{
    // ─── Event type catalogue ─────────────────────────────────────────────────

    public const TYPE_LOADING = 'loading';

    public const TYPE_DUMPING = 'dumping';

    public const TYPE_BREAKDOWN = 'breakdown';

    public const TYPE_IDLING = 'idling';

    public const TYPE_MAINTENANCE = 'maintenance';

    public const TYPE_FUELING = 'fueling';

    public const TYPE_GEOFENCE_ENTRY = 'geofence_entry';

    public const TYPE_GEOFENCE_EXIT = 'geofence_exit';

    public const TYPE_SPEED_VIOLATION = 'speed_violation';

    public const TYPE_STATUS_CHANGE = 'status_change';

    public const TYPE_OTHER = 'other';

    /**
     * Visual configuration for each event type.
     * Used both server-side (blade @json) and client-side JS.
     */
    public const TYPE_CONFIG = [
        self::TYPE_LOADING => ['label' => 'Loading',          'color' => '#10b981', 'emoji' => '📦', 'icon' => 'box'],
        self::TYPE_DUMPING => ['label' => 'Dumping',          'color' => '#f59e0b', 'emoji' => '🪣', 'icon' => 'dump'],
        self::TYPE_BREAKDOWN => ['label' => 'Breakdown',        'color' => '#ef4444', 'emoji' => '🔧', 'icon' => 'wrench'],
        self::TYPE_IDLING => ['label' => 'Idling',           'color' => '#3b82f6', 'emoji' => '⏸️', 'icon' => 'pause'],
        self::TYPE_MAINTENANCE => ['label' => 'Maintenance',      'color' => '#8b5cf6', 'emoji' => '🛠️', 'icon' => 'tool'],
        self::TYPE_FUELING => ['label' => 'Fueling',          'color' => '#06b6d4', 'emoji' => '⛽', 'icon' => 'fuel'],
        self::TYPE_GEOFENCE_ENTRY => ['label' => 'Geofence Entry',   'color' => '#6366f1', 'emoji' => '🚧', 'icon' => 'enter'],
        self::TYPE_GEOFENCE_EXIT => ['label' => 'Geofence Exit',    'color' => '#a855f7', 'emoji' => '🚦', 'icon' => 'exit'],
        self::TYPE_SPEED_VIOLATION => ['label' => 'Speed Violation',  'color' => '#f97316', 'emoji' => '⚡', 'icon' => 'speed'],
        self::TYPE_STATUS_CHANGE => ['label' => 'Status Change',    'color' => '#64748b', 'emoji' => '🔄', 'icon' => 'sync'],
        self::TYPE_OTHER => ['label' => 'Other',            'color' => '#94a3b8', 'emoji' => '📍', 'icon' => 'pin'],
    ];

    // ─── Eloquent config ──────────────────────────────────────────────────────

    protected $fillable = [
        'team_id',
        'machine_id',
        'mine_area_id',
        'event_type',
        'title',
        'notes',
        'latitude',
        'longitude',
        'occurred_at',
        'resolved_at',
        'metadata',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'occurred_at' => 'datetime',
            'resolved_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    /** @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\Machine, $this> */
    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }

    /** @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\MineArea, $this> */
    public function mineArea(): BelongsTo
    {
        return $this->belongsTo(MineArea::class);
    }

    /** @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    // ─── Computed accessors ───────────────────────────────────────────────────

    public function getIconEmojiAttribute(): string
    {
        return self::TYPE_CONFIG[$this->event_type]['emoji'] ?? '📍';
    }

    public function getColorHexAttribute(): string
    {
        return self::TYPE_CONFIG[$this->event_type]['color'] ?? '#94a3b8';
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeForTeam(Builder $query, int $teamId): Builder
    {
        return $query->where('team_id', $teamId);
    }

    public function scopeRecent(Builder $query, int $hours = 24): Builder
    {
        return $query->where('occurred_at', '>=', now()->subHours($hours));
    }

    public function scopeWithLocation(Builder $query): Builder
    {
        return $query->whereNotNull('latitude')->whereNotNull('longitude');
    }

    public function scopeOfType(Builder $query, string|array $types): Builder
    {
        return is_array($types)
            ? $query->whereIn('event_type', $types)
            : $query->where('event_type', $types);
    }
}
