<?php

namespace App\Events;

use App\Models\BellEquipment;
use Carbon\Carbon;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when the computed health score for a Bell machine changes by more than
 * the configured threshold (default: ±10 points). Drives maintenance planning,
 * predictive maintenance alerts, and the machine health dashboard.
 */
class BellMachineHealthChanged
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly BellEquipment $equipment,
        public readonly float $previousScore,
        public readonly float $newScore,
        public readonly string $changeReason,
        public readonly Carbon $detectedAt,
    ) {}
}
