<?php

namespace App\Models;

use Carbon\Carbon;
use Database\Factories\IoTSensorFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property Carbon|null $last_reading_at
 * @property string|null $status
 * @property int $id
 * @property string $name
 * @property string $sensor_type
 */
class IoTSensor extends Model
{
    /** @use HasFactory<IoTSensorFactory> */
    use HasFactory;

    protected $table = 'iot_sensors';

    /** @var list<string> */
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

    /** @var array<string, string> */
    protected $casts = [
        'last_reading' => 'json',
        'metadata' => 'json',
        'last_reading_at' => 'datetime',
    ];

    /** @return BelongsTo<Team,$this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /** @return HasMany<SensorReading,$this> */
    public function readings(): HasMany
    {
        return $this->hasMany(SensorReading::class);
    }

    public function isOnline(): bool
    {
        return $this->status === 'active' && $this->last_reading_at?->isAfter(now()->subMinutes(5));
    }
}
