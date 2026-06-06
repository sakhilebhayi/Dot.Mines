<?php

namespace App\Observers;

use App\Models\FuelTransaction;
use App\Services\NotificationService;

class FuelTransactionObserver
{
    /** Minimum litres for a refuel transaction to trigger a notification. */
    private const LARGE_REFUEL_THRESHOLD = 500;

    public function created(FuelTransaction $transaction): void
    {
        // Only notify on significant refuel transactions to avoid noise
        if ($transaction->transaction_type !== 'refuel') {
            return;
        }

        if ($transaction->quantity_liters < self::LARGE_REFUEL_THRESHOLD) {
            return;
        }

        $machineName = $transaction->machine?->name ?? 'Unknown machine';

        NotificationService::notifyManagers(
            teamId: $transaction->team_id,
            type: NotificationService::TYPE_FUEL,
            title: "Large Fuel Refuel: {$transaction->quantity_liters}L",
            message: "{$transaction->quantity_liters} litres refuelled for {$machineName}.",
            alertLevel: NotificationService::LEVEL_INFO,
            data: [
                'transaction_id' => $transaction->id,
                'machine' => $machineName,
                'quantity_liters' => $transaction->quantity_liters,
                'total_cost' => $transaction->total_cost,
                'transaction_type' => $transaction->transaction_type,
                'event' => 'created',
            ],
            actionUrl: '/fuel',
        );
    }

    public function deleted(FuelTransaction $transaction): void
    {
        NotificationService::notifyAdmins(
            teamId: $transaction->team_id,
            type: NotificationService::TYPE_FUEL,
            title: 'Fuel Transaction Deleted',
            message: "A fuel transaction ({$transaction->transaction_type}, {$transaction->quantity_liters}L) has been deleted.",
            alertLevel: NotificationService::LEVEL_WARNING,
            data: [
                'transaction_id' => $transaction->id,
                'transaction_type' => $transaction->transaction_type,
                'quantity_liters' => $transaction->quantity_liters,
                'total_cost' => $transaction->total_cost,
                'event' => 'deleted',
            ],
        );
    }
}
