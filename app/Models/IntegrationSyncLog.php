<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * IntegrationSyncLog Model
 *
 * Audit trail for every sync attempt on an Integration.
 *
 * @property int $id
 * @property int $integration_id
 * @property int $team_id
 * @property Carbon $started_at
 * @property Carbon|null $finished_at
 * @property string $status running|success|partial|failed
 * @property int $machines_synced
 * @property int $records_inserted
 * @property string|null $error_message
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Integration $integration
 */
class IntegrationSyncLog extends Model
{
    protected $fillable = [
        'integration_id',
        'team_id',
        'started_at',
        'finished_at',
        'status',
        'machines_synced',
        'records_inserted',
        'error_message',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Integration, $this> */
    public function integration(): BelongsTo
    {
        return $this->belongsTo(Integration::class);
    }
}
