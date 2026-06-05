<?php

namespace App\Models;

use Carbon\Carbon;
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
 * @property Carbon|null $unit_install_date_time
 * @property Carbon $created_at
 * @property Carbon $updated_at
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

    /**
     * @return array<mixed>
     */
    protected function casts(): array
    {
        return [
            'unit_install_date_time' => 'datetime',
        ];
    }

    /** @return HasOne<BellEquipmentCurrentStatus, $this> */
    public function currentStatus(): HasOne
    {
        return $this->hasOne(BellEquipmentCurrentStatus::class, 'equipment_key', 'equipment_key');
    }

    /** @return HasMany<BellEquipmentTelemetryHistory, $this> */
    public function telemetryHistory(): HasMany
    {
        return $this->hasMany(BellEquipmentTelemetryHistory::class, 'equipment_key', 'equipment_key');
    }

    /** @return HasMany<BellEquipmentDailyKpi, $this> */
    public function dailyKpis(): HasMany
    {
        return $this->hasMany(BellEquipmentDailyKpi::class, 'equipment_key', 'equipment_key');
    }

    /** @return HasMany<BellEquipmentLocationHistory, $this> */
    public function locationHistory(): HasMany
    {
        return $this->hasMany(BellEquipmentLocationHistory::class, 'equipment_key', 'equipment_key');
    }

    /** @return HasMany<BellEquipmentFuelUsageHistory, $this> */
    public function fuelUsageHistory(): HasMany
    {
        return $this->hasMany(BellEquipmentFuelUsageHistory::class, 'equipment_key', 'equipment_key');
    }

    /** @return HasMany<BellEquipmentOperatingHoursHistory, $this> */
    public function operatingHoursHistory(): HasMany
    {
        return $this->hasMany(BellEquipmentOperatingHoursHistory::class, 'equipment_key', 'equipment_key');
    }

    /** @return HasMany<BellEquipmentIdleHoursHistory, $this> */
    public function idleHoursHistory(): HasMany
    {
        return $this->hasMany(BellEquipmentIdleHoursHistory::class, 'equipment_key', 'equipment_key');
    }

    /** @return HasMany<BellEquipmentLoadCountHistory, $this> */
    public function loadCountHistory(): HasMany
    {
        return $this->hasMany(BellEquipmentLoadCountHistory::class, 'equipment_key', 'equipment_key');
    }

    /** @return HasMany<BellEquipmentHealthHistory, $this> */
    public function healthHistory(): HasMany
    {
        return $this->hasMany(BellEquipmentHealthHistory::class, 'equipment_key', 'equipment_key');
    }

    /** @return HasMany<BellEquipmentCautionCode, $this> */
    public function cautionCodes(): HasMany
    {
        return $this->hasMany(BellEquipmentCautionCode::class, 'equipment_key', 'equipment_key');
    }
}
