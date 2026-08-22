<?php

namespace App\Actions\Fuel;

use App\Exceptions\FuelDispenseException;
use App\Models\FuelMonthlyAllocation;
use App\Models\FuelTank;
use App\Models\FuelTransaction;
use App\Models\Machine;
use App\Services\FuelManagementService;
use Illuminate\Support\Facades\Log;

/**
 * The dispensing business rules (refactor R3: extracted from the
 * FuelManagement Livewire component): resolve the applicable monthly
 * allocation (machine's area first, tank's area second, team-level
 * fallback third), enforce the remaining-litres budget, price from the
 * allocation, record the transaction, refresh consumption. Refusals
 * throw FuelDispenseException with an operator-facing message.
 */
final class RecordFuelDispense
{
    /**
     * @throws FuelDispenseException
     *
     * @psalm-suppress PossiblyUnusedReturnValue -- the transaction is the natural API even where callers only need success/refusal
     */
    public function execute(int $teamId, int $tankId, float $quantity, ?int $machineId, ?int $userId): FuelTransaction
    {
        $tank = FuelTank::query()->where('team_id', $teamId)->find($tankId);

        if ($tank === null) {
            throw new FuelDispenseException('Selected tank not found.');
        }

        $allocation = $this->resolveAllocation($tank, $machineId);

        if ($allocation === null) {
            throw new FuelDispenseException('No monthly allocation set for this mine area.');
        }

        $remaining = (float) $allocation->remaining_liters;

        if ($quantity > $remaining) {
            throw new FuelDispenseException(
                'Dispensing this amount would exceed the monthly allocation for this mine area. Remaining: '
                .number_format($remaining, 2).'L.',
            );
        }

        $unitPrice = (float) ($allocation->fuel_price_per_liter ?? 0);

        $transaction = (new FuelManagementService)->recordTransaction([
            'team_id' => $tank->team_id,
            'fuel_tank_id' => $tank->id,
            'machine_id' => ($machineId !== null && $machineId !== 0) ? $machineId : null,
            'user_id' => $userId,
            'transaction_type' => 'dispensing',
            'quantity_liters' => $quantity,
            'unit_price' => $unitPrice,
            'total_cost' => round($unitPrice * $quantity, 2),
            'fuel_type' => $tank->fuel_type,
            'transaction_date' => now(),
            'monthly_allocation_id' => $allocation->id,
            'notes' => null,
        ]);

        $allocation->updateConsumption();

        return $transaction;
    }

    /**
     * Area precedence: the dispensing machine's area, else the tank's
     * area, else a team-level (NULL-area) allocation for this month.
     */
    private function resolveAllocation(FuelTank $tank, ?int $machineId): ?FuelMonthlyAllocation
    {
        $year = (int) now()->format('Y');
        $month = (int) now()->format('n');

        $mineAreaId = null;

        if ($machineId !== null && $machineId !== 0) {
            $machine = Machine::query()->where('team_id', $tank->team_id)->find($machineId);
            $mineAreaId = $machine?->mine_area_id;
        }

        $mineAreaId ??= $tank->mine_area_id;

        /** @var FuelMonthlyAllocation|null $allocation */
        $allocation = null;

        if ($mineAreaId !== null) {
            /** @psalm-suppress UnnecessaryVarAnnotation */
            /** @var FuelMonthlyAllocation|null $allocation */
            $allocation = FuelMonthlyAllocation::query()
                ->where('team_id', $tank->team_id)
                ->where('year', $year)
                ->where('month', $month)
                ->where('mine_area_id', $mineAreaId)
                ->first();
        }

        if ($allocation === null) {
            /** @psalm-suppress UnnecessaryVarAnnotation */
            /** @var FuelMonthlyAllocation|null $allocation */
            $allocation = FuelMonthlyAllocation::query()
                ->where('team_id', $tank->team_id)
                ->whereNull('mine_area_id')
                ->where('year', $year)
                ->where('month', $month)
                ->first();

            if ($allocation !== null) {
                Log::info('Falling back to team-level fuel allocation for dispense', [
                    'team_id' => $tank->team_id,
                    'requested_mine_area_id' => $mineAreaId,
                    'allocation_id' => $allocation->id,
                    'year' => $year,
                    'month' => $month,
                ]);
            }
        }

        return $allocation;
    }
}
