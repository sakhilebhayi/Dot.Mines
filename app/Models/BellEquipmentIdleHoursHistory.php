<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * BellEquipmentIdleHoursHistory Model
 *
 * Cumulative idle hours history per Bell machine.
 * High idle hours relative to operating hours indicates underutilization.
 * Delta-processed: only inserted when idle_hours value has changed.
 *
 * @property int $history_id
 * @property int $equipment_key
 * @property float|null $idle_hours
 * @property string $source
 * @property Carbon $recorded_at
 * @property Carbon $created_at
 */
class BellEquipmentIdleHoursHistory extends Model
{
    protected $primaryKey = 'history_id';

    protected $table = 'bell_equipment_idle_hours_history';

    public $timestamps = false;

    protected $fillable = [
        'equipment_key',
        'idle_hours',
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
            'idle_hours' => 'decimal:2',
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
