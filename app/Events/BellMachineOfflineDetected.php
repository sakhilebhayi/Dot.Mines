<?php

namespace App\Events;

use App\Models\BellEquipment;
use Carbon\Carbon;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a Bell machine has produced no telemetry for an extended period
 * (default threshold: 2 hours). Used to drive machine-offline alerts and
 * agent health scoring.
 */
class BellMachineOfflineDetected
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly BellEquipment $equipment,
        public readonly Carbon $lastSeenAt,
        public readonly int $offlineMinutes,
    ) {}
}
