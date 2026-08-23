<?php

namespace App\Http\Resources\Concerns;

use Carbon\CarbonInterface;

/**
 * One date format for the whole API.
 *
 * Some date columns are cast to Carbon and some are typed loosely as
 * `string|Carbon|null` (inspection and service dates). Routing them through
 * here means consumers parse ISO-8601 everywhere instead of guessing per
 * field.
 */
trait FormatsTimestamps
{
    protected function iso(mixed $value): ?string
    {
        if ($value instanceof CarbonInterface) {
            return $value->toIso8601String();
        }

        if (is_string($value) && $value !== '') {
            return $value;
        }

        return null;
    }
}
