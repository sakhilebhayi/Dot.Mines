<?php

namespace App\Models;

use App\Traits\HasTeamFilters;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One ledger row in the machine-allocation entitlement history.
 * Append-only: balances are SUM(delta); rows are never edited or deleted,
 * so the ledger doubles as the billing audit trail.
 *
 * @property int $id
 * @property int $team_id
 * @property string $class
 * @property int $delta
 * @property string $source
 * @property int|null $payment_id
 * @property int|null $subscription_id
 * @property string|null $reason
 * @property int|null $created_by
 * @property Carbon $created_at
 */
class MachineAllocation extends Model
{
    use HasTeamFilters;

    protected $fillable = [
        'team_id',
        'class',
        'delta',
        'source',
        'payment_id',
        'subscription_id',
        'reason',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'delta' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
