<?php

namespace App\Events;

use App\Models\BellEquipment;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired after a successful per-machine telemetry pull from any Bell endpoint.
 * Allows listeners to update knowledge graphs, agent memory, and data quality scores.
 */
class BellTelemetryReceived
{
    use Dispatchable, SerializesModels;

    /**
     * @param  string  $signal  The Bell API signal name (e.g. 'Locations', 'CumulativeFuelUsed')
     * @param  array<string, mixed>  $payload  Parsed payload data from the response
     */
    public function __construct(
        public readonly BellEquipment $equipment,
        public readonly string $signal,
        public readonly array $payload,
    ) {}
}
