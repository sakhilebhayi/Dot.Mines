<?php

namespace App\Exceptions;

use Exception;

/**
 * Thrown by MachineProvisioningService when a team attempts to register a
 * machine without an available allocation. Carries the honest numbers so
 * every surface (Livewire flash, API error, UI banner) can explain exactly
 * where the customer stands and what to do next.
 */
class InsufficientAllocationException extends Exception
{
    public function __construct(
        public readonly int $occupied,
        public readonly int $capacity,
        public readonly bool $trial,
    ) {
        parent::__construct(sprintf(
            'No machine allocations available. You currently have %d active machine%s and %s. Purchase an additional machine allocation to add another machine.',
            $occupied,
            $occupied === 1 ? '' : 's',
            $trial
                ? sprintf('a trial allowance of %d', $capacity)
                : sprintf('%d purchased allocation%s', $capacity, $capacity === 1 ? '' : 's'),
        ));
    }
}
