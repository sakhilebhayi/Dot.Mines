<?php

namespace App\Models;

use Carbon\Carbon;
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
 * @property Carbon $assigned_at
 * @property Carbon|null $unassigned_at
 * @property string|null $reason
 * @property string|null $notes
 * @property Carbon $created_at
 * @property-read MineArea|null $mineArea
 * @property Carbon $updated_at
 */
class MachineAreaAssignment extends Model
{
    protected $table = 'machine_mine_area_assignments';

    /** @var array<int, string> */
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

    /** @var array<string, string> */
    protected $casts = [
        'assigned_at' => 'datetime',
        'unassigned_at' => 'datetime',
    ];

    /** @return BelongsTo<Team,$this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /** @return BelongsTo<Machine,$this> */
    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }

    /** @return BelongsTo<MineArea,$this> */
    public function mineArea(): BelongsTo
    {
        return $this->belongsTo(MineArea::class);
    }

    /** @return BelongsTo<User,$this> */
    public function assignedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeActive($query)
    {
        $query->whereNull('unassigned_at');

        return $query;
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForTeam($query, int $teamId)
    {
        return $query->where('team_id', $teamId);
    }
}
