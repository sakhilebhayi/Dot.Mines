<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * BellEquipmentLoadCountHistory Model
 *
 * Cumulative load count and payload history per Bell machine.
 * Used for production analytics: loads per shift, tonnes per hour, etc.
 * Delta-processed: only inserted when load_count has changed.
 *
 * @property int $history_id
 * @property int $equipment_key
 * @property int|null $load_count
 * @property float|null $cumulative_payload
 * @property string $payload_units
 * @property string $source
 * @property Carbon $recorded_at
 * @property Carbon $created_at
 */
class BellEquipmentLoadCountHistory extends Model
{
    protected $primaryKey = 'history_id';

    protected $table = 'bell_equipment_load_count_history';

    public $timestamps = false;

    protected $fillable = [
        'equipment_key',
        'load_count',
        'cumulative_payload',
        'payload_units',
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
            'cumulative_payload' => 'decimal:2',
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
