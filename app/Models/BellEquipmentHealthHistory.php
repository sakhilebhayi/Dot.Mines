<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * BellEquipmentHealthHistory Model
 *
 * Machine health snapshot per Bell equipment, recorded every sync.
 * Tracks engine condition, DEF level, active DPF regeneration hours,
 * active caution code count, and a derived 0–100 health score.
 *
 * Health score algorithm (100 = perfect):
 *   - Engine condition not OK:          -20
 *   - DEF < 5%:                         -25  / < 10%: -20  / < 20%: -10
 *   - Active regen rate > 10% of hours: -20  / > 5%:  -10
 *   - Per active caution code:          -5  (max -30)
 *
 * @property int $health_id
 * @property int $equipment_key
 * @property string|null $engine_condition
 * @property float|null $def_remaining_percent
 * @property float|null $active_regen_hours
 * @property int $caution_code_count
 * @property float|null $health_score
 * @property Carbon $recorded_at
 * @property Carbon $created_at
 */
class BellEquipmentHealthHistory extends Model
{
    protected $primaryKey = 'health_id';

    protected $table = 'bell_equipment_health_history';

    public $timestamps = false;

    protected $fillable = [
        'equipment_key',
        'engine_condition',
        'def_remaining_percent',
        'active_regen_hours',
        'caution_code_count',
        'health_score',
        'recorded_at',
        'created_at',
    ];

    /**
     * @return array<mixed>
     */
    protected function casts(): array
    {
        return [
            'def_remaining_percent' => 'decimal:2',
            'active_regen_hours' => 'decimal:2',
            'health_score' => 'decimal:2',
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
