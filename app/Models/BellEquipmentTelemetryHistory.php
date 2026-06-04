<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * BellEquipmentTelemetryHistory Model
 *
 * Append-only record of every API snapshot per machine.
 * Never modified after insert.
 *
 * @property int $history_id
 * @property int $equipment_key
 * @property \Carbon\Carbon|null $snapshot_time
 * @property float|null $latitude
 * @property float|null $longitude
 * @property float|null $idle_hours
 * @property int|null $load_count
 * @property float|null $operating_hours
 * @property float|null $payload
 * @property string|null $payload_units
 * @property float|null $def_percent
 * @property float|null $odometer
 * @property string|null $odometer_units
 * @property float|null $fuel_consumed
 * @property string|null $fuel_units
 * @property float|null $fuel_remaining_percent
 * @property bool|null $engine_running
 * @property string|null $engine_number
 * @property \Carbon\Carbon|null $telemetry_date
 * @property \Carbon\Carbon $created_date
 */
class BellEquipmentTelemetryHistory extends Model
{
    protected $primaryKey = 'history_id';

    protected $table = 'bell_equipment_telemetry_history';

    public $timestamps = false;

    protected $fillable = [
        'equipment_key',
        'snapshot_time',
        'latitude',
        'longitude',
        'idle_hours',
        'load_count',
        'operating_hours',
        'payload',
        'payload_units',
        'def_percent',
        'odometer',
        'odometer_units',
        'fuel_consumed',
        'fuel_units',
        'fuel_remaining_percent',
        'engine_running',
        'engine_number',
        'telemetry_date',
        'created_date',
    ];

    protected function casts(): array
    {
        return [
            'snapshot_time' => 'datetime',
            'telemetry_date' => 'datetime',
            'created_date' => 'datetime',
            'engine_running' => 'boolean',
            'latitude' => 'decimal:8',
            'longitude' => 'decimal:8',
            'idle_hours' => 'decimal:2',
            'operating_hours' => 'decimal:2',
            'payload' => 'decimal:2',
            'def_percent' => 'decimal:2',
            'odometer' => 'decimal:2',
            'fuel_consumed' => 'decimal:2',
            'fuel_remaining_percent' => 'decimal:2',
        ];
    }

    /** @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\BellEquipment, $this> */
    public function equipment(): BelongsTo
    {
        return $this->belongsTo(BellEquipment::class, 'equipment_key', 'equipment_key');
    }
}
