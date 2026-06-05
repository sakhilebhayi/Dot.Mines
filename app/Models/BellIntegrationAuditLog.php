<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * BellIntegrationAuditLog Model
 *
 * Tracks each execution of the Bell fleet sync job.
 *
 * @property int $log_id
 * @property Carbon|null $execution_date
 * @property bool $success
 * @property int $records_processed
 * @property int $records_inserted
 * @property int $records_updated
 * @property string|null $error_message
 */
class BellIntegrationAuditLog extends Model
{
    protected $primaryKey = 'log_id';

    protected $table = 'bell_integration_audit_logs';

    public $timestamps = false;

    protected $fillable = [
        'execution_date',
        'success',
        'records_processed',
        'records_inserted',
        'records_updated',
        'error_message',
    ];

    /**
     * @return array<mixed>
     */
    protected function casts(): array
    {
        return [
            'execution_date' => 'datetime',
            'success' => 'boolean',
        ];
    }
}
