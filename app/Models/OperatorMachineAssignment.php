<?php

namespace App\Models;

use App\Traits\HasTeamFilters;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One stretch of one operator on one machine.
 *
 * Open = unassigned_at NULL. Rows are never deleted: the closed rows ARE the
 * answer to "who operated this machine yesterday?".
 *
 * @property int $id
 * @property int $team_id
 * @property int $operator_id
 * @property int $machine_id
 * @property string|null $shift
 * @property Carbon $assigned_at
 * @property Carbon|null $unassigned_at
 * @property int|null $assigned_by
 * @property int|null $unassigned_by
 * @property string|null $reason
 * @property bool $was_override
 * @property string|null $override_reason
 * @property array<int, string>|null $overridden_failures
 */
class OperatorMachineAssignment extends Model
{
    use HasTeamFilters;

    /** @var array<int, string> */
    protected $fillable = [
        'team_id',
        'operator_id',
        'machine_id',
        'shift',
        'assigned_at',
        'unassigned_at',
        'assigned_by',
        'unassigned_by',
        'reason',
        'was_override',
        'override_reason',
        'overridden_failures',
    ];

    /**
     * @return array<string, string>
     */
    #[\Override]
    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
            'unassigned_at' => 'datetime',
            'was_override' => 'boolean',
            'overridden_failures' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Operator,$this>
     */
    public function operator(): BelongsTo
    {
        return $this->belongsTo(Operator::class);
    }

    /**
     * @return BelongsTo<Machine,$this>
     */
    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }

    /**
     * @return BelongsTo<User,$this>
     */
    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    /**
     * @return BelongsTo<User,$this>
     */
    public function unassignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'unassigned_by');
    }

    public function isOpen(): bool
    {
        return $this->unassigned_at === null;
    }
}
