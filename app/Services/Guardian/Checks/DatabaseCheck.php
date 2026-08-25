<?php

namespace App\Services\Guardian\Checks;

use App\Services\Guardian\CheckResult;
use App\Services\Guardian\Contracts\GuardianCheck;
use Illuminate\Support\Facades\DB;

/**
 * Database connectivity and latency probe.
 */
class DatabaseCheck implements GuardianCheck
{
    #[\Override]
    public function key(): string
    {
        return 'database';
    }

    #[\Override]
    public function run(): CheckResult
    {
        $startedAt = microtime(true);

        try {
            DB::select('SELECT 1');
        } catch (\Throwable $e) {
            return CheckResult::critical('Database unreachable.', [
                'error' => $e::class,
            ]);
        }

        $latencyMs = (int) round((microtime(true) - $startedAt) * 1000.0);

        return CheckResult::healthy('Database answering.', [
            'latency_ms' => $latencyMs,
        ]);
    }
}
