<?php

namespace App\Console\Commands;

use App\Models\Team;
use App\Services\Operators\ComplianceAlertService;
use App\Support\ApiPayload;
use Illuminate\Console\Command;

/**
 * The daily operator-compliance sweep.
 *
 * Scheduled in routes/console.php. All the real logic -- including the
 * idempotency that stops the same warning arriving every morning -- lives in
 * ComplianceAlertService, so tests exercise the service directly and this
 * stays a thin loop over teams.
 */
class CheckOperatorCompliance extends Command
{
    protected $signature = 'operators:check-compliance';

    protected $description = 'Alert admins about operator licences, medicals and training that are expiring or expired';

    public function handle(ComplianceAlertService $alerts): int
    {
        $total = 0;

        foreach (ApiPayload::intList(Team::query()->pluck('id')->all()) as $teamId) {
            $total += $alerts->sweepTeam($teamId);
        }

        $this->info("Operator compliance sweep complete: {$total} new alert(s).");

        return self::SUCCESS;
    }
}
