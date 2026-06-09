<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $equipment_key
 * @property float $distance_km
 * @property Carbon|null $snapshot_time
 * @property Carbon $created_at
 * @property-read BellEquipment $equipment
 */
class BellDistanceTravelled extends Model
{
    protected $table = 'bell_distance_travelled';

    public $timestamps = false;

    protected $fillable = [
        'equipment_key',
        'distance_km',
        'snapshot_time',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'distance_km' => 'decimal:3',
            'snapshot_time' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<BellEquipment, $this> */
    public function equipment(): BelongsTo
    {
        return $this->belongsTo(BellEquipment::class, 'equipment_key', 'equipment_key');
    }
}
