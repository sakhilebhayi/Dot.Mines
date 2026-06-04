<?php

namespace App\Models;

use App\Traits\HasTeamFilters;
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
 * @property \Carbon\Carbon $entry_time
 * @property \Carbon\Carbon|null $exit_time
 * @property float $entry_latitude
 * @property float $entry_longitude
 * @property float|null $exit_latitude
 * @property float|null $exit_longitude
 * @property float|null $tonnage_loaded
 * @property string|null $material_type
 * @property string|null $notes
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder|GeofenceEntry where(string $column, mixed $operator = null, mixed $value = null)
 * @method static \Illuminate\Database\Eloquent\Builder|GeofenceEntry whereIn(string $column, array $values)
 * @method static \Illuminate\Database\Eloquent\Builder|GeofenceEntry orderBy(string $column, string $direction = 'asc')
 * @method static GeofenceEntry|null find(mixed $id, array $columns = ['*'])
 * @method static GeofenceEntry findOrFail(mixed $id, array $columns = ['*'])
 * @method static \Illuminate\Database\Eloquent\Collection all(array $columns = ['*'])
 */
class GeofenceEntry extends Model
{
    use HasFactory, HasTeamFilters;

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

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
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
    }

    /**
     * Get the machine for this entry
     */
    /** @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\Machine, $this> */
    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }

    /**
     * Get the geofence for this entry
     */
    /** @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\Geofence, $this> */
    public function geofence(): BelongsTo
    {
        return $this->belongsTo(Geofence::class);
    }

    /**
     * Get the team for this entry
     */
    /** @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\Team, $this> */
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

        return $this->exit_time->diffInMinutes($this->entry_time);
    }

    /**
     * Get duration formatted as HH:MM
     */
    public function getFormattedDuration(): string
    {
        $minutes = $this->getDurationMinutes();
        if (! $minutes) {
            return 'Active';
        }

        $hours = floor($minutes / 60);
        $mins = $minutes % 60;

        return sprintf('%02d:%02d', $hours, $mins);
    }
}
