<?php

namespace App\Events;

use App\Models\BellEquipment;
use Carbon\Carbon;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a Bell machine reports an engine condition other than 'Normal'
 * (i.e. 'Warning' or 'Error'). Drives predictive maintenance workflows and
 * machine health scoring.
 */
class BellEngineWarningDetected
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly BellEquipment $equipment,
        public readonly string $conditionStatus,
        public readonly Carbon $detectedAt,
    ) {}
}
