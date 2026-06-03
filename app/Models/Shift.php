<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Shift Model
 *
 * @property int $id
 * @property int $team_id
 * @property string $shift_type
 * @property \Carbon\Carbon $started_at
 * @property \Carbon\Carbon|null $ended_at
 * @property array|null $previous_assignments
 * @property array|null $productivity_metrics
 * @property array|null $performance_summary
 * @property array|null $metadata
 * @property \Carbon\Carbon|null $deleted_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder|Shift where(string $column, mixed $operator = null, mixed $value = null)
 * @method static \Illuminate\Database\Eloquent\Builder|Shift whereIn(string $column, array $values)
 * @method static \Illuminate\Database\Eloquent\Builder|Shift orderBy(string $column, string $direction = 'asc')
 * @method static Shift|null find(mixed $id, array $columns = ['*'])
 * @method static Shift findOrFail(mixed $id, array $columns = ['*'])
 * @method static \Illuminate\Database\Eloquent\Collection all(array $columns = ['*'])
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
