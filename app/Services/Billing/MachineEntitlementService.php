<?php

namespace App\Services\Billing;

use App\Models\MachineAllocation;
use App\Models\Subscription;
use App\Models\Team;
use App\Support\ApiPayload;
use Illuminate\Support\Facades\DB;

/**
 * Answers exactly one question, from the ledger and the machines table:
 * how many machines is this team entitled to run, and how many is it
 * running? (Brief's invariant: Available = Purchased - Active.)
 *
 * Two capacity modes:
 *  - Purchased: the team has ledger rows; capacity is SUM(delta) per
 *    class ('adt' / 'heavy'), enforced per class.
 *  - Trial: empty ledger; capacity is a single class-agnostic allowance
 *    (config billing.trial_machine_allowance) across all machines.
 *
 * Occupancy counts machines with allocation_state = 'occupying' whatever
 * their operational status -- a machine in maintenance still holds its
 * allocation (brief §14); only pending_activation and released rows are
 * free of charge.
 */
class MachineEntitlementService
{
    public const STATE_OCCUPYING = 'occupying';

    public const STATE_PENDING = 'pending_activation';

    public const STATE_RELEASED = 'released';

    /**
     * Map a machine_type onto the class its allocation is priced under.
     */
    public function classFor(string $machineType): string
    {
        /** @var array<string, string> $map */
        $map = config('billing.machine_class_map', []);

        $fallback = ApiPayload::str(config('billing.machine_class_fallback'), 'adt');

        return $map[$machineType] ?? $fallback;
    }

    /**
     * @return array{purchased: array{adt: int, heavy: int}, occupied: array{adt: int, heavy: int}, available: array{adt: int, heavy: int}, trial: bool, trial_allowance: int, over_allocated: bool, suspended: bool}
     */
    public function summary(Team $team): array
    {
        $purchased = $this->purchasedBalances($team);
        $trial = $purchased['adt'] === 0 && $purchased['heavy'] === 0 && ! $this->hasLedger($team);
        $occupied = $this->occupiedCounts($team);
        $allowance = config('billing.trial_machine_allowance', 2);

        if ($trial) {
            $availableTotal = $allowance - ($occupied['adt'] + $occupied['heavy']);

            return [
                'purchased' => $purchased,
                'occupied' => $occupied,
                // Trial capacity is class-agnostic; expose the shared pool
                // under both keys so UI code has one shape to render.
                'available' => ['adt' => $availableTotal, 'heavy' => $availableTotal],
                'trial' => true,
                'trial_allowance' => $allowance,
                'over_allocated' => $availableTotal < 0,
                'suspended' => false,
            ];
        }

        // Brief §11: a lapsed subscription makes PURCHASED allocations
        // unavailable -- machines are never touched, the ledger is never
        // rewritten, and renewal restores capacity with no data movement.
        // Canceled-but-paid stays entitled until the period actually ends.
        $suspended = ! $this->subscriptionEntitled($team);

        $available = $suspended
            ? ['adt' => 0, 'heavy' => 0]
            : [
                'adt' => $purchased['adt'] - $occupied['adt'],
                'heavy' => $purchased['heavy'] - $occupied['heavy'],
            ];

        return [
            'purchased' => $purchased,
            'occupied' => $occupied,
            'available' => $available,
            'trial' => false,
            'trial_allowance' => $allowance,
            'over_allocated' => $suspended
                ? ($occupied['adt'] + $occupied['heavy']) > 0
                : ($available['adt'] < 0 || $available['heavy'] < 0),
            'suspended' => $suspended,
        ];
    }

    /**
     * Whether the team's purchased balances currently count. Ledger rows
     * with no subscription row at all stay entitled -- admin adjustments
     * and grandfathered grants must not require a Paystack subscription;
     * Paystack purchases always create one via the subscription webhook,
     * which is when lapse-gating engages.
     */
    private function subscriptionEntitled(Team $team): bool
    {
        $subscription = Subscription::query()
            ->where('team_id', $team->id)
            ->latest('id')
            ->first();

        if ($subscription === null) {
            return true;
        }

        if ($subscription->isActive() || $subscription->onTrial()) {
            return true;
        }

        // Canceled or lapsed, but the paid period hasn't ended yet.
        return $subscription->current_period_end !== null
            && $subscription->current_period_end->isFuture();
    }

    /**
     * @return array{adt: int, heavy: int}
     */
    private function purchasedBalances(Team $team): array
    {
        $sums = MachineAllocation::query()
            ->withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->groupBy('class')
            ->select('class', DB::raw('SUM(delta) as balance'))
            ->pluck('balance', 'class');

        return [
            'adt' => (int) ($sums['adt'] ?? 0),
            'heavy' => (int) ($sums['heavy'] ?? 0),
        ];
    }

    private function hasLedger(Team $team): bool
    {
        return MachineAllocation::query()
            ->withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->exists();
    }

    /**
     * @return array{adt: int, heavy: int}
     */
    private function occupiedCounts(Team $team): array
    {
        $counts = ['adt' => 0, 'heavy' => 0];

        $rows = $team->machines()
            ->where('allocation_state', self::STATE_OCCUPYING)
            ->select('machine_type', DB::raw('COUNT(*) as total'))
            ->groupBy('machine_type')
            ->pluck('total', 'machine_type');

        /** @psalm-suppress MixedAssignment */
        foreach ($rows as $machineType => $total) {
            if ($this->classFor($machineType) === 'heavy') {
                $counts['heavy'] += (int) $total;
            } else {
                $counts['adt'] += (int) $total;
            }
        }

        return $counts;
    }
}
