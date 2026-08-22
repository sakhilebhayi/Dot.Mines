<?php

namespace App\Actions\Fuel;

use App\Exceptions\FuelDispenseException;
use App\Models\FuelTank;
use App\Services\FuelManagementService;

/**
 * Records a refill delivery and raises the tank level, clamping at
 * capacity (refactor R3: extracted from the FuelManagement component --
 * the overflow rule is a business invariant, not UI behaviour).
 */
final class RefuelTank
{
    /**
     * @return array{tank: FuelTank, overflow: float}
     *
     * @throws FuelDispenseException
     */
    public function execute(int $teamId, int $tankId, float $quantity, float $unitPrice, ?string $notes, ?int $userId): array
    {
        $tank = FuelTank::query()->where('team_id', $teamId)->find($tankId);

        if ($tank === null) {
            throw new FuelDispenseException('Selected tank not found.');
        }

        (new FuelManagementService)->recordTransaction([
            'team_id' => $tank->team_id,
            'fuel_tank_id' => $tank->id,
            'machine_id' => null,
            'user_id' => $userId,
            'transaction_type' => 'refill',
            'quantity_liters' => $quantity,
            'unit_price' => $unitPrice,
            'total_cost' => round($unitPrice * $quantity, 2),
            'fuel_type' => $tank->fuel_type,
            'transaction_date' => now(),
            'monthly_allocation_id' => null,
            'notes' => $notes,
        ]);

        $newLevel = (float) $tank->current_level_liters + $quantity;
        $overflow = 0.0;

        if ($newLevel > (float) $tank->capacity_liters) {
            $overflow = $newLevel - (float) $tank->capacity_liters;
            $newLevel = (float) $tank->capacity_liters;
        }

        $tank->current_level_liters = $newLevel;
        $tank->save();

        return ['tank' => $tank, 'overflow' => $overflow];
    }
}
