<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $equipment_key
 * @property float $payload_tonnes
 * @property Carbon|null $snapshot_time
 * @property Carbon $created_at
 * @property-read BellEquipment $equipment
 */
class BellPayloadTotal extends Model
{
    protected $table = 'bell_payload_totals';

    public $timestamps = false;

    protected $fillable = [
        'equipment_key',
        'payload_tonnes',
        'snapshot_time',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'payload_tonnes' => 'decimal:3',
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
