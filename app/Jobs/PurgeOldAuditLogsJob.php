<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Deletes audit log entries older than AUDIT_LOG_RETENTION_DAYS (default 365).
 */
class PurgeOldAuditLogsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 300;

    /** @var array<int> */
    public array $backoff = [60, 300];

    public function handle(): void
    {
        $retentionDays = (int) env('AUDIT_LOG_RETENTION_DAYS', 365);
        $cutoff = now()->subDays($retentionDays);

        $deleted = DB::table('audit_logs')
            ->where('created_at', '<', $cutoff)
            ->delete();

        Log::info('PurgeOldAuditLogsJob completed', [
            'cutoff' => $cutoff->toDateString(),
            'deleted_rows' => $deleted,
        ]);
    }
}
