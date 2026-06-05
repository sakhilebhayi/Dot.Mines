<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * BellEquipmentOperatingHoursHistory Model
 *
 * Cumulative operating hours history per Bell machine.
 * Delta-processed: only inserted when operating_hours value has changed.
 *
 * @property int $history_id
 * @property int $equipment_key
 * @property float|null $operating_hours
 * @property string $source
 * @property Carbon $recorded_at
 * @property Carbon $created_at
 */
class BellEquipmentOperatingHoursHistory extends Model
{
    protected $primaryKey = 'history_id';

    protected $table = 'bell_equipment_operating_hours_history';

    public $timestamps = false;

    protected $fillable = [
        'equipment_key',
        'operating_hours',
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
            'operating_hours' => 'decimal:2',
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
