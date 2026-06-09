<?php

namespace App\Events;

use App\Models\BellEquipment;
use Carbon\Carbon;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a Bell machine's cumulative payload total exceeds the configured
 * threshold for the current shift or day. Used by production intelligence and
 * dispatch optimisation agents to rebalance haul assignments.
 */
class BellPayloadThresholdExceeded
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly BellEquipment $equipment,
        public readonly float $payloadTonnes,
        public readonly float $thresholdTonnes,
        public readonly Carbon $detectedAt,
    ) {}
}
