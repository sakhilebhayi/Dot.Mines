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
 *
 * @method static \Illuminate\Database\Eloquent\Builder|SensorReading where(string $column, mixed $operator = null, mixed $value = null)
 * @method static \Illuminate\Database\Eloquent\Builder|SensorReading whereIn(string $column, array<string|int> $values)
 * @method static SensorReading|null find(mixed $id, array<string> $columns = ['*'])
 * @method static SensorReading findOrFail(mixed $id, array<string> $columns = ['*'])
 * @method static \Illuminate\Database\Eloquent\Collection<int,SensorReading> all(array<string> $columns = ['*'])
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

    protected $casts = [
        'value' => 'float',
        'quality_score' => 'float',
        'timestamp' => 'datetime',
    ];

    public function sensor(): BelongsTo
    {
        return $this->belongsTo(IoTSensor::class);
    }
}
