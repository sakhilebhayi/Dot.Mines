<?php

namespace App\Events;

use App\Models\BellEquipment;
use Carbon\Carbon;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a Bell machine's fuel level drops below the configured threshold
 * (default: 20%). Drives refuelling alerts and dispatch re-planning.
 */
class BellFuelLowDetected
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly BellEquipment $equipment,
        public readonly float $fuelRemainingPercent,
        public readonly Carbon $detectedAt,
    ) {}
}
