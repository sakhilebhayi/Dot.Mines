<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Stores periodic data quality snapshots per domain.
 *
 * Used by the MEGA V2 "Data Quality / Trust Score" domain.
 * Records completeness, corruption, duplicate rates, and reconciliation accuracy.
 *
 * @property int $id
 * @property string $domain
 * @property string $metric_name
 * @property float $score
 * @property int $total_records
 * @property int $missing_count
 * @property int $corrupt_count
 * @property int $duplicate_count
 * @property float|null $reconciliation_accuracy
 * @property string|null $notes
 * @property array<mixed>|null $metadata
 * @property Carbon $snapshot_at
 */
class DataQualitySnapshot extends Model
{
    protected $table = 'data_quality_snapshots';

    protected $fillable = [
        'domain',
        'metric_name',
        'score',
        'total_records',
        'missing_count',
        'corrupt_count',
        'duplicate_count',
        'reconciliation_accuracy',
        'notes',
        'metadata',
        'snapshot_at',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'float',
            'reconciliation_accuracy' => 'float',
            'metadata' => 'array',
            'snapshot_at' => 'datetime',
        ];
    }
}
