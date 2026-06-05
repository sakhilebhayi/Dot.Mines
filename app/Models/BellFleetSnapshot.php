<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * BellFleetSnapshot Model
 *
 * Stores the raw JSON payload and metadata for each API call.
 *
 * @property int $snapshot_id
 * @property Carbon|null $snapshot_time
 * @property string|null $fleet_version
 * @property int $equipment_count
 * @property string|null $raw_json
 * @property Carbon $created_date
 */
class BellFleetSnapshot extends Model
{
    protected $primaryKey = 'snapshot_id';

    protected $table = 'bell_fleet_snapshots';

    public $timestamps = false;

    protected $fillable = [
        'snapshot_time',
        'fleet_version',
        'equipment_count',
        'raw_json',
        'created_date',
    ];

    /**
     * @return array<mixed>
     */
    protected function casts(): array
    {
        return [
            'snapshot_time' => 'datetime',
            'created_date' => 'datetime',
        ];
    }
}
