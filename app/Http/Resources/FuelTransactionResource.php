<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\FormatsTimestamps;
use App\Models\FuelTransaction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A fuel movement: dispensing, delivery, transfer, or a recorded loss.
 *
 * `receipt_file_path` is absent -- an internal storage location, not part
 * of the transaction record.
 *
 * @mixin FuelTransaction
 */
class FuelTransactionResource extends JsonResource
{
    use FormatsTimestamps;

    /**
     * @return array<string, mixed>
     */
    #[\Override]
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'transaction_type' => $this->transaction_type,
            'transaction_date' => $this->iso($this->transaction_date),

            'fuel_tank_id' => $this->fuel_tank_id,
            'machine_id' => $this->machine_id,
            'from_tank_id' => $this->from_tank_id,
            'to_tank_id' => $this->to_tank_id,
            'user_id' => $this->user_id,

            'quantity_liters' => $this->quantity_liters,
            'unit_price' => $this->unit_price,
            'total_cost' => $this->total_cost,
            'currency' => $this->currency,
            'fuel_type' => $this->fuel_type,

            'odometer_reading' => $this->odometer_reading,
            'machine_hours' => $this->machine_hours,
            'supplier' => $this->supplier,
            'invoice_number' => $this->invoice_number,
            'notes' => $this->notes,

            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),

            'machine' => MachineResource::make($this->whenLoaded('machine')),
            'fuel_tank' => FuelTankResource::make($this->whenLoaded('fuelTank')),
            'recorded_by' => UserSummaryResource::make($this->whenLoaded('user')),
        ];
    }
}
