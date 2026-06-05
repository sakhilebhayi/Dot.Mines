<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * BellEquipmentDailyKpi Model
 *
 * Derived daily production KPIs calculated from consecutive telemetry snapshots.
 *
 * @property int $kpi_id
 * @property int $equipment_key
 * @property Carbon $kpi_date
 * @property int $loads_moved
 * @property float $payload_moved
 * @property float $operating_hours
 * @property float $idle_hours
 * @property float $distance_travelled
 * @property float $fuel_used
 * @property float $utilization_percent
 * @property Carbon $created_date
 */
class BellEquipmentDailyKpi extends Model
{
    protected $primaryKey = 'kpi_id';

    protected $table = 'bell_equipment_daily_kpis';

    public $timestamps = false;

    protected $fillable = [
        'equipment_key',
        'kpi_date',
        'loads_moved',
        'payload_moved',
        'operating_hours',
        'idle_hours',
        'distance_travelled',
        'fuel_used',
        'utilization_percent',
        'created_date',
    ];

    /**
     * @return array<mixed>
     */
    protected function casts(): array
    {
        return [
            'kpi_date' => 'date',
            'created_date' => 'datetime',
            'payload_moved' => 'decimal:2',
            'operating_hours' => 'decimal:2',
            'idle_hours' => 'decimal:2',
            'distance_travelled' => 'decimal:2',
            'fuel_used' => 'decimal:2',
            'utilization_percent' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<BellEquipment, $this> */
    public function equipment(): BelongsTo
    {
        return $this->belongsTo(BellEquipment::class, 'equipment_key', 'equipment_key');
    }
}
