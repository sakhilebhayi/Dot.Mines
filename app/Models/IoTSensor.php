<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IoTSensor extends Model
{
    use HasFactory;

    protected $table = 'iot_sensors';

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
            'last_reading' => 'json',
            'metadata' => 'json',
            'last_reading_at' => 'datetime',
        ];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function readings(): HasMany
    {
        return $this->hasMany(SensorReading::class);
    }

    public function isOnline(): bool
    {
        return $this->status === 'active' && $this->last_reading_at?->isAfter(now()->subMinutes(5));
    }
}
