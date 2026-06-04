<?php

namespace App\Models;

use App\Traits\HasTeamFilters;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * IoTSensor Model
 *
 * @property float|null $compliance_score
 * @property float $confidence_score
 * @property \Carbon\Carbon $created_at
 * @property array|null $data
 * @property mixed $device_id
 * @property array|null $factors
 * @property string|null $file_path
 * @property mixed $forecast_date
 * @property mixed $generated_by
 * @property int $id
 * @property mixed $iot_sensor_id
 * @property array|null $issues
 * @property array|null $last_reading
 * @property \Carbon\Carbon|null $last_reading_at
 * @property float|null $location_latitude
 * @property float|null $location_longitude
 * @property string $material_name
 * @property array|null $metadata
 * @property mixed|null $mine_area_id
 * @property string $model_version
 * @property string $name
 * @property float $predicted_tonnage
 * @property float $quality_score
 * @property \Carbon\Carbon $report_date
 * @property mixed $report_type
 * @property mixed $sensor_type
 * @property mixed $status
 * @property mixed $team_id
 * @property mixed $timestamp
 * @property string $unit
 * @property \Carbon\Carbon $updated_at
 * @property float $value
 *
 * @method static \Illuminate\Database\Eloquent\Builder|IoTSensor where(string $column, mixed $operator = null, mixed $value = null)
 * @method static \Illuminate\Database\Eloquent\Builder|IoTSensor whereIn(string $column, array $values)
 * @method static \Illuminate\Database\Eloquent\Builder|IoTSensor orderBy(string $column, string $direction = 'asc')
 * @method static IoTSensor|null find(mixed $id, array $columns = ['*'])
 * @method static IoTSensor findOrFail(mixed $id, array $columns = ['*'])
 * @method static \Illuminate\Database\Eloquent\Collection all(array $columns = ['*'])
 */
class IoTSensor extends Model
{
    use HasFactory, HasTeamFilters;

    protected $fillable = [
        'team_id',
        'mine_area_id',
        'name',
        'sensor_type',
        'device_id',
        'status',
        'last_reading',
        'last_reading_at',
        'location_latitude',
        'location_longitude',
        'metadata',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_reading' => 'array',
            'metadata' => 'array',
            'last_reading_at' => 'datetime',
            'location_latitude' => 'float',
            'location_longitude' => 'float',
        ];
    }

    public function readings(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(SensorReading::class);
    }
}
