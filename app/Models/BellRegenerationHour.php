<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $equipment_key
 * @property float $regeneration_hours
 * @property Carbon|null $snapshot_time
 * @property Carbon $created_at
 * @property-read BellEquipment $equipment
 */
class BellRegenerationHour extends Model
{
    protected $table = 'bell_regeneration_hours';

    public $timestamps = false;

    protected $fillable = [
        'equipment_key',
        'regeneration_hours',
        'snapshot_time',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'regeneration_hours' => 'decimal:2',
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
