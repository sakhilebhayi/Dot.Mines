<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * BellEquipment Model
 *
 * Master record for a Bell machine synced via the ISO15143-3 fleet API.
 * One record per unique EquipmentID.
 *
 * @property int $equipment_key
 * @property string|null $oem_name
 * @property string|null $model
 * @property string $equipment_id
 * @property string|null $serial_number
 * @property string|null $pin
 * @property \Carbon\Carbon|null $unit_install_date_time
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class BellEquipment extends Model
{
    protected $primaryKey = 'equipment_key';

    protected $table = 'bell_equipment';

    protected $fillable = [
        'oem_name',
        'model',
        'equipment_id',
        'serial_number',
        'pin',
        'unit_install_date_time',
    ];

    protected function casts(): array
    {
        return [
            'unit_install_date_time' => 'datetime',
        ];
    }

    public function currentStatus(): HasOne
    {
        return $this->hasOne(BellEquipmentCurrentStatus::class, 'equipment_key', 'equipment_key');
    }

    public function telemetryHistory(): HasMany
    {
        return $this->hasMany(BellEquipmentTelemetryHistory::class, 'equipment_key', 'equipment_key');
    }

    public function dailyKpis(): HasMany
    {
        return $this->hasMany(BellEquipmentDailyKpi::class, 'equipment_key', 'equipment_key');
    }
}
