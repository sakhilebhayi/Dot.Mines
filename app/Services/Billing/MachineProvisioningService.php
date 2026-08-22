<?php

namespace App\Services\Billing;

use App\Exceptions\InsufficientAllocationException;
use App\Models\Machine;
use App\Models\Team;
use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * The ONLY way a machine may come into existence. Every creation path --
 * the Fleet page, the REST API, OEM integrations -- routes through here,
 * so the entitlement invariant cannot be bypassed by calling a different
 * endpoint (brief §4).
 *
 * Race safety (brief §15): a per-team atomic lock (cache_locks table in
 * production) serialises provisioning, and capacity is re-verified INSIDE
 * the lock + transaction, so two concurrent requests can never both take
 * the last allocation.
 */
class MachineProvisioningService
{
    public function __construct(private MachineEntitlementService $entitlements) {}

    /**
     * Provision a machine, consuming one allocation of the type's class.
     *
     * @param  Closure(): Machine  $create  creates and returns the machine row
     *
     * @throws InsufficientAllocationException when no capacity is available
     */
    public function provision(Team $team, string $machineType, Closure $create): Machine
    {
        return Cache::lock($this->lockName($team), 10)->block(5, function () use ($team, $machineType, $create): Machine {
            return DB::transaction(function () use ($team, $machineType, $create): Machine {
                $class = $this->entitlements->classFor($machineType);
                $summary = $this->entitlements->summary($team);

                if ($summary['available'][$class] <= 0) {
                    $capacity = $summary['trial']
                        ? $summary['trial_allowance']
                        : $summary['purchased'][$class];

                    throw new InsufficientAllocationException(
                        occupied: $summary['occupied']['adt'] + $summary['occupied']['heavy'],
                        capacity: $capacity,
                        trial: $summary['trial'],
                    );
                }

                $machine = $create();
                $machine->forceFill(['allocation_state' => MachineEntitlementService::STATE_OCCUPYING])->save();

                return $machine;
            });
        });
    }

    /**
     * Integration variant (brief §23): discovery must never be refused or
     * silently dropped, but it must not consume capacity the team doesn't
     * have either. Beyond capacity, the machine is recorded as
     * pending_activation -- visible, not billable, activatable later.
     *
     * @param  Closure(): Machine  $create
     */
    public function provisionOrPend(Team $team, string $machineType, Closure $create): Machine
    {
        try {
            return $this->provision($team, $machineType, $create);
        } catch (InsufficientAllocationException) {
            $machine = $create();
            $machine->forceFill(['allocation_state' => MachineEntitlementService::STATE_PENDING])->save();

            return $machine;
        }
    }

    /**
     * Activate a previously pending machine once capacity exists.
     *
     * @throws InsufficientAllocationException
     */
    public function activate(Machine $machine): Machine
    {
        /** @var Team $team */
        $team = $machine->team;

        return Cache::lock($this->lockName($team), 10)->block(5, function () use ($team, $machine): Machine {
            return DB::transaction(function () use ($team, $machine): Machine {
                $class = $this->entitlements->classFor($machine->machine_type);
                $summary = $this->entitlements->summary($team);

                if ($summary['available'][$class] <= 0) {
                    $capacity = $summary['trial'] ? $summary['trial_allowance'] : $summary['purchased'][$class];

                    throw new InsufficientAllocationException(
                        occupied: $summary['occupied']['adt'] + $summary['occupied']['heavy'],
                        capacity: $capacity,
                        trial: $summary['trial'],
                    );
                }

                $machine->forceFill(['allocation_state' => MachineEntitlementService::STATE_OCCUPYING])->save();

                return $machine;
            });
        });
    }

    private function lockName(Team $team): string
    {
        return "machine-provision-{$team->id}";
    }
}
