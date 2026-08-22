<?php

namespace App\Models;

use App\Services\Sync\SyncSequence;
use App\Traits\HasTeamFilters;
use Carbon\Carbon;
use Database\Factories\MachineMetricFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

/**
 * MachineMetric Model
 *
 * Stores real-time metrics from machines
 * Includes engine data, fuel, temperature, and other sensor readings
 *
 * @property int $id
 * @property int $team_id
 * @property int $machine_id
 * @property float|null $latitude
 * @property float|null $longitude
 * @property float|null $speed
 * @property float|null $heading
 * @property float|null $altitude
 * @property float|null $engine_rpm
 * @property float|null $engine_temperature
 * @property float|null $coolant_temperature
 * @property float|null $oil_pressure
 * @property float|null $fuel_level
 * @property float|null $fuel_consumption_rate
 * @property float|null $throttle_position
 * @property float|null $battery_voltage
 * @property float|null $total_hours
 * @property float|null $idle_hours
 * @property float|null $operating_hours
 * @property float|null $load_weight
 * @property float|null $payload_capacity_used
 * @property float|null $tire_pressure_front_left
 * @property float|null $tire_pressure_front_right
 * @property float|null $tire_pressure_rear_left
 * @property float|null $tire_pressure_rear_right
 * @property array<string, mixed>|null $raw_data
 * @property Carbon $recorded_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class MachineMetric extends Model
{
    /** @use HasFactory<MachineMetricFactory> */
    use HasFactory, HasTeamFilters;

    /**
     * New telemetry advances the parent machine's sync cursor so the fleet
     * scope of the incremental sync API picks the machine up: clients cache
     * one denormalized fleet row per machine (identity + latest telemetry),
     * so a metric insert IS a fleet-state change. Direct DB update on
     * purpose -- no model events, no touched timestamps.
     */
    protected static function booted(): void
    {
        static::created(function (MachineMetric $metric): void {
            DB::table('machines')
                ->where('id', $metric->machine_id)
                ->update(['sync_version' => SyncSequence::next()]);
        });
    }

    /** @var list<string> */
    protected $fillable = [
        'team_id',
        'machine_id',
        'latitude',
        'longitude',
        'speed',
        'heading',
        'altitude',
        'engine_rpm',
        'engine_temperature',
        'coolant_temperature',
        'oil_pressure',
        'fuel_level',
        'fuel_consumption_rate',
        'throttle_position',
        'battery_voltage',
        'total_hours',
        'idle_hours',
        'operating_hours',
        'load_weight',
        'payload_capacity_used',
        'tire_pressure_front_left',
        'tire_pressure_front_right',
        'tire_pressure_rear_left',
        'tire_pressure_rear_right',
        'raw_data', // JSON for any additional manufacturer-specific data
        'recorded_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'speed' => 'float',
        'heading' => 'float',
        'altitude' => 'float',
        'engine_rpm' => 'float',
        'engine_temperature' => 'float',
        'coolant_temperature' => 'float',
        'oil_pressure' => 'float',
        'fuel_level' => 'float',
        'fuel_consumption_rate' => 'float',
        'throttle_position' => 'float',
        'battery_voltage' => 'float',
        'total_hours' => 'float',
        'idle_hours' => 'float',
        'load_weight' => 'float',
        'payload_capacity_used' => 'float',
        'tire_pressure_front_left' => 'float',
        'tire_pressure_front_right' => 'float',
        'tire_pressure_rear_left' => 'float',
        'tire_pressure_rear_right' => 'float',
        'operating_hours' => 'float',
        'raw_data' => 'json',
        'recorded_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the machine this metric belongs to
     *
     * @return BelongsTo<Machine, $this>
     */
    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }

    /**
     * Get the team this metric belongs to
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
