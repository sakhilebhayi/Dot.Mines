<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * MachineAreaAssignment Model
 *
 * @property int $id
 * @property int $team_id
 * @property int $machine_id
 * @property int $mine_area_id
 * @property int|null $assigned_by
 * @property \Carbon\Carbon $assigned_at
 * @property \Carbon\Carbon|null $unassigned_at
 * @property string|null $reason
 * @property string|null $notes
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder|MachineAreaAssignment where(string $column, mixed $operator = null, mixed $value = null)
 * @method static \Illuminate\Database\Eloquent\Builder|MachineAreaAssignment whereIn(string $column, array $values)
 * @method static \Illuminate\Database\Eloquent\Builder|MachineAreaAssignment orderBy(string $column, string $direction = 'asc')
 * @method static MachineAreaAssignment|null find(mixed $id, array $columns = ['*'])
 * @method static MachineAreaAssignment findOrFail(mixed $id, array $columns = ['*'])
 * @method static \Illuminate\Database\Eloquent\Collection all(array $columns = ['*'])
 */
class MachineAreaAssignment extends Model
{
    protected $table = 'machine_mine_area_assignments';

    protected $fillable = [
        'team_id',
        'machine_id',
        'mine_area_id',
        'assigned_by',
        'assigned_at',
        'unassigned_at',
        'reason',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
            'unassigned_at' => 'datetime',
        ];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }

    public function mineArea(): BelongsTo
    {
        return $this->belongsTo(MineArea::class);
    }

    public function assignedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('unassigned_at');
    }

    public function scopeForTeam(Builder $query, $teamId): Builder
    {
        return $query->where('team_id', $teamId);
    }
}
