<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $equipment_key
 * @property float $fuel_remaining_percent
 * @property Carbon|null $snapshot_time
 * @property Carbon $created_at
 * @property-read BellEquipment $equipment
 */
class BellFuelLevel extends Model
{
    protected $table = 'bell_fuel_levels';

    public $timestamps = false;

    protected $fillable = [
        'equipment_key',
        'fuel_remaining_percent',
        'snapshot_time',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'fuel_remaining_percent' => 'decimal:2',
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
