<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * EngineHourSession Model
 *
 * Records a single engine ON → OFF session for a machine.
 * A null `ignition_off_at` means the engine is currently running.
 *
 * @property int $id
 * @property int $team_id
 * @property int $machine_id
 * @property \Carbon\Carbon $ignition_on_at
 * @property \Carbon\Carbon|null $ignition_off_at
 * @property int|null $duration_seconds
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class EngineHourSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'team_id',
        'machine_id',
        'ignition_on_at',
        'ignition_off_at',
        'duration_seconds',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ignition_on_at' => 'datetime',
            'ignition_off_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    /** @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\Machine, $this> */
    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }

    /** @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    /** Sessions that started today (from midnight). */
    public function scopeToday(Builder $query): Builder
    {
        return $query->where('ignition_on_at', '>=', now()->startOfDay());
    }

    /** Sessions that are currently open (engine is running). */
    public function scopeRunning(Builder $query): Builder
    {
        return $query->whereNull('ignition_off_at');
    }

    // ── Accessors ─────────────────────────────────────────────────────────────

    /**
     * Effective duration in seconds.
     * For closed sessions, returns the stored value.
     * For open/running sessions, calculates elapsed seconds from ignition_on_at to now.
     */
    public function getEffectiveDurationSecondsAttribute(): int
    {
        if ($this->ignition_off_at !== null) {
            return $this->duration_seconds ?? (int) $this->ignition_on_at->diffInSeconds($this->ignition_off_at);
        }

        return (int) $this->ignition_on_at->diffInSeconds(now());
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Record an ignition ON event for a machine.
     * Silently ignored if the machine already has an open session.
     */
    public static function recordIgnitionOn(Machine $machine): self
    {
        // Don't open another session if one is already running
        $open = static::where('machine_id', $machine->id)
            ->whereNull('ignition_off_at')
            ->latest('ignition_on_at')
            ->first();

        if ($open) {
            return $open;
        }

        return static::create([
            'team_id' => $machine->team_id,
            'machine_id' => $machine->id,
            'ignition_on_at' => now(),
        ]);
    }

    /**
     * Record an ignition OFF event for a machine.
     * Closes the most recent open session; silently ignored if none exists.
     */
    public static function recordIgnitionOff(Machine $machine): ?self
    {
        $session = static::where('machine_id', $machine->id)
            ->whereNull('ignition_off_at')
            ->latest('ignition_on_at')
            ->first();

        if (! $session) {
            return null;
        }

        $off = now();
        $session->update([
            'ignition_off_at' => $off,
            'duration_seconds' => (int) $session->ignition_on_at->diffInSeconds($off),
        ]);

        return $session->fresh();
    }
}
