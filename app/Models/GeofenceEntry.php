<?php

namespace App\Models;

use App\Traits\HasTeamFilters;
use Carbon\Carbon;
use Database\Factories\GeofenceEntryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * GeofenceEntry Model
 *
 * Records machine entry and exit times from geofenced areas
 * Tracks tonnage and material movement
 *
 * @property int $id
 * @property int $team_id
 * @property int $geofence_id
 * @property int $machine_id
 * @property Carbon $entry_time
 * @property Carbon|null $exit_time
 * @property float $entry_latitude
 * @property float $entry_longitude
 * @property float|null $exit_latitude
 * @property float|null $exit_longitude
 * @property float|null $tonnage_loaded
 * @property string|null $material_type
 * @property string|null $notes
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property mixed|null $latitude
 * @property mixed|null $longitude
 * @property-read Machine|null $machine
 * @property-read Geofence|null $geofence
 * @property mixed|null $exited_at
 */
class GeofenceEntry extends Model
{
    /** @use HasFactory<GeofenceEntryFactory> */
    use HasFactory, HasTeamFilters;

    /** @var list<string> */
    protected $fillable = [
        'team_id',
        'geofence_id',
        'machine_id',
        'entry_time',
        'exit_time',
        'entry_latitude',
        'entry_longitude',
        'exit_latitude',
        'exit_longitude',
        'tonnage_loaded',
        'material_type',
        'notes',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'entry_time' => 'datetime',
        'exit_time' => 'datetime',
        'entry_latitude' => 'float',
        'entry_longitude' => 'float',
        'exit_latitude' => 'float',
        'exit_longitude' => 'float',
        'tonnage_loaded' => 'float',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the machine for this entry
     *
     * @return BelongsTo<Machine,$this>
     */
    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }

    /**
     * Get the geofence for this entry
     *
     * @return BelongsTo<Geofence,$this>
     */
    public function geofence(): BelongsTo
    {
        return $this->belongsTo(Geofence::class);
    }

    /**
     * Get the team for this entry
     *
     * @return BelongsTo<Team,$this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Calculate duration in minutes
     */
    public function getDurationMinutes(): ?int
    {
        if (! $this->exit_time) {
            return null;
        }

        // Carbon 3 diffs are signed: measure forward from entry to exit.
        return (int) $this->entry_time->diffInMinutes($this->exit_time);
    }

    /**
     * Get duration formatted as HH:MM
     */
    public function getFormattedDuration(): string
    {
        $minutes = $this->getDurationMinutes();
        if (($minutes === null || $minutes === 0)) {
            return 'Active';
        }

        $hours = floor($minutes / 60);
        $mins = $minutes % 60;

        return sprintf('%02d:%02d', $hours, $mins);
    }
}
