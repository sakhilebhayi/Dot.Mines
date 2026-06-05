<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * SensorReading Model
 *
 * @property int $id
 * @property int $iot_sensor_id
 * @property string $sensor_type
 * @property float $value
 * @property string $unit
 * @property Carbon $timestamp
 * @property float $quality_score
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class SensorReading extends Model
{
    protected $fillable = [
        'iot_sensor_id',
        'sensor_type',
        'value',
        'unit',
        'timestamp',
        'quality_score',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value' => 'float',
            'quality_score' => 'float',
            'timestamp' => 'datetime',
        ];
    }

    /** @return BelongsTo<IoTSensor, $this> */
    public function sensor(): BelongsTo
    {
        return $this->belongsTo(IoTSensor::class);
    }
}
