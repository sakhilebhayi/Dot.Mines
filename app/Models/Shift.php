<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Shift Model
 *
 * @property int $id
 * @property int $team_id
 * @property string $shift_type
 * @property Carbon $started_at
 * @property Carbon|null $ended_at
 * @property array<string, mixed>|null $previous_assignments
 * @property array<string, mixed>|null $productivity_metrics
 * @property array<string, mixed>|null $performance_summary
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $deleted_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Shift extends Model
{
    use SoftDeletes;

    protected $table = 'shifts';

    protected $fillable = [
        'team_id',
        'shift_type',
        'started_at',
        'ended_at',
        'previous_assignments',
        'productivity_metrics',
        'performance_summary',
        'metadata',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'previous_assignments' => 'array',
            'productivity_metrics' => 'array',
            'performance_summary' => 'array',
            'metadata' => 'array',
        ];
    }
}
