<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * BellEquipmentFuelUsageHistory Model
 *
 * Per-sync fuel consumption history for Bell equipment.
 * Stores both cumulative fuel used and the remaining fuel % at the time.
 * Delta-processed: only inserted when fuel_used_cumulative or fuel_remaining_percent changed.
 *
 * @property int $history_id
 * @property int $equipment_key
 * @property float|null $fuel_used_cumulative
 * @property float|null $fuel_remaining_percent
 * @property string $fuel_units
 * @property string $source
 * @property Carbon $recorded_at
 * @property Carbon $created_at
 */
class BellEquipmentFuelUsageHistory extends Model
{
    protected $primaryKey = 'history_id';

    protected $table = 'bell_equipment_fuel_usage_history';

    public $timestamps = false;

    protected $fillable = [
        'equipment_key',
        'fuel_used_cumulative',
        'fuel_remaining_percent',
        'fuel_units',
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
            'fuel_used_cumulative' => 'decimal:2',
            'fuel_remaining_percent' => 'decimal:2',
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
