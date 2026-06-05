<?php

namespace App\Models;

use App\Traits\HasTeamFilters;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * IoTSensor Model
 *
 * @property float|null $compliance_score
 * @property float $confidence_score
 * @property Carbon $created_at
 * @property array<string, mixed>|null $data
 * @property mixed $device_id
 * @property array<string, mixed>|null $factors
 * @property string|null $file_path
 * @property mixed $forecast_date
 * @property mixed $generated_by
 * @property int $id
 * @property mixed $iot_sensor_id
 * @property array<string, mixed>|null $issues
 * @property array<string, mixed>|null $last_reading
 * @property Carbon|null $last_reading_at
 * @property float|null $location_latitude
 * @property float|null $location_longitude
 * @property string $material_name
 * @property array<string, mixed>|null $metadata
 * @property mixed|null $mine_area_id
 * @property string $model_version
 * @property string $name
 * @property float $predicted_tonnage
 * @property float $quality_score
 * @property Carbon $report_date
 * @property mixed $report_type
 * @property mixed $sensor_type
 * @property mixed $status
 * @property mixed $team_id
 * @property mixed $timestamp
 * @property string $unit
 * @property Carbon $updated_at
 * @property float $value
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

    public function readings(): HasMany
    {
        return $this->hasMany(SensorReading::class);
    }
}
