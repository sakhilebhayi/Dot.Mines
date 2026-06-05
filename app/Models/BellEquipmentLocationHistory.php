<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * BellEquipmentLocationHistory Model
 *
 * Append-only location trail for Bell equipment.
 * Populated by both the ISO15143-3 snapshot sync (every 15 min)
 * and the historical telemetry service (hourly REST API).
 * Delta-processed: a new row is only inserted when lat/lng have changed.
 *
 * @property int $location_id
 * @property int $equipment_key
 * @property float|null $latitude
 * @property float|null $longitude
 * @property float|null $heading_degrees
 * @property float|null $speed_kmh
 * @property string $source
 * @property Carbon $recorded_at
 * @property Carbon $created_at
 */
class BellEquipmentLocationHistory extends Model
{
    protected $primaryKey = 'location_id';

    protected $table = 'bell_equipment_location_history';

    public $timestamps = false;

    protected $fillable = [
        'equipment_key',
        'latitude',
        'longitude',
        'heading_degrees',
        'speed_kmh',
        'source',
        'recorded_at',
        'created_at',
    ];

    /**
     * @return array<mixed>
     */
    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:8',
            'longitude' => 'decimal:8',
            'heading_degrees' => 'decimal:2',
            'speed_kmh' => 'decimal:2',
            'recorded_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<BellEquipment, $this> */
    public function equipment(): BelongsTo
    {
        return $this->belongsTo(BellEquipment::class, 'equipment_key', 'equipment_key');
    }
}
