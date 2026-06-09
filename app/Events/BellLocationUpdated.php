<?php

namespace App\Events;

use App\Models\BellEquipment;
use Carbon\Carbon;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a new location record is received from the Bell Locations endpoint.
 * Distinct from MachineLocationUpdated (which fires from the ISO15143-3 snapshot);
 * this event carries the richer Bell-specific location payload (heading, speed).
 * Used by the live map, route replay, dispatch optimisation, and geofencing.
 */
class BellLocationUpdated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly BellEquipment $equipment,
        public readonly float $latitude,
        public readonly float $longitude,
        public readonly ?float $headingDegrees,
        public readonly ?float $speedKmh,
        public readonly Carbon $recordedAt,
    ) {}
}
