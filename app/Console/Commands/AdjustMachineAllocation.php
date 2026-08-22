<?php

namespace App\Console\Commands;

use App\Models\MachineAllocation;
use App\Models\Team;
use App\Services\Billing\MachineEntitlementService;
use Illuminate\Console\Command;

/**
 * Audited manual allocation adjustment (billing brief §17): every change
 * is a reason-carrying ledger row -- the same append-only ledger the
 * webhook grant path writes -- so admin adjustments appear in the same
 * §18 history as purchases, attributable and reversible by a
 * counter-adjustment, never by editing history.
 */
class AdjustMachineAllocation extends Command
{
    protected $signature = 'billing:adjust-allocation
        {team : Team ID}
        {class : Allocation class (adt|heavy)}
        {delta : Signed quantity, e.g. 2 or -1}
        {--reason= : Why this adjustment is being made (required)}
        {--by= : User ID of the administrator making the change}';

    protected $description = 'Grant or revoke machine allocations for a team via an audited ledger entry';

    public function handle(MachineEntitlementService $entitlements): int
    {
        $team = Team::find((int) $this->argument('team'));

        if (! $team) {
            $this->error('Team not found.');

            return self::FAILURE;
        }

        $classArg = $this->argument('class');
        $class = is_string($classArg) ? $classArg : '';

        if (! in_array($class, ['adt', 'heavy'], true)) {
            $this->error("Class must be 'adt' or 'heavy'.");

            return self::FAILURE;
        }

        $delta = (int) $this->argument('delta');

        if ($delta === 0) {
            $this->error('Delta must be a non-zero signed integer.');

            return self::FAILURE;
        }

        $reasonOpt = $this->option('reason');
        $reason = is_string($reasonOpt) ? $reasonOpt : '';

        if (trim($reason) === '') {
            $this->error('A --reason is required: manual adjustments must be auditable.');

            return self::FAILURE;
        }

        MachineAllocation::create([
            'team_id' => $team->id,
            'class' => $class,
            'delta' => $delta,
            'source' => 'admin',
            'reason' => $reason,
            'created_by' => $this->option('by') !== null ? (int) $this->option('by') : null,
        ]);

        $summary = $entitlements->summary($team);

        $this->info(sprintf(
            'Adjusted %s allocations for team %d (%s) by %+d. Reason: %s',
            $class,
            $team->id,
            $team->name,
            $delta,
            $reason,
        ));
        $this->line(sprintf(
            'Now: purchased adt=%d heavy=%d | occupied adt=%d heavy=%d | available adt=%d heavy=%d%s',
            $summary['purchased']['adt'],
            $summary['purchased']['heavy'],
            $summary['occupied']['adt'],
            $summary['occupied']['heavy'],
            $summary['available']['adt'],
            $summary['available']['heavy'],
            $summary['suspended'] ? ' | SUSPENDED (subscription lapsed)' : '',
        ));

        return self::SUCCESS;
    }
}
