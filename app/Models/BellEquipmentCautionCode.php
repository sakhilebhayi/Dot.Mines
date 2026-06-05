<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * BellEquipmentCautionCode Model
 *
 * OEM fault / caution code record for Bell equipment.
 * Codes are upserted on every sync: new codes become active, codes no longer
 * present in the XML are automatically marked cleared.
 *
 * @property int $fault_id
 * @property int $equipment_key
 * @property string $fault_code
 * @property string|null $fault_description
 * @property string|null $severity
 * @property string $source
 * @property bool $is_active
 * @property Carbon $occurred_at
 * @property Carbon|null $cleared_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @method static Builder<static> active()
 * @method static Builder<static> critical()
 */
class BellEquipmentCautionCode extends Model
{
    protected $primaryKey = 'fault_id';

    protected $table = 'bell_equipment_caution_codes';

    protected $fillable = [
        'equipment_key',
        'fault_code',
        'fault_description',
        'severity',
        'source',
        'is_active',
        'occurred_at',
        'cleared_at',
    ];

    /**
     * @return array<mixed>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'occurred_at' => 'datetime',
            'cleared_at' => 'datetime',
        ];
    }

    /** @param Builder<static> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /** @param Builder<static> $query */
    public function scopeCritical(Builder $query): void
    {
        $query->where('severity', 'Critical');
    }

    /** @return BelongsTo<BellEquipment, $this> */
    public function equipment(): BelongsTo
    {
        return $this->belongsTo(BellEquipment::class, 'equipment_key', 'equipment_key');
    }
}
