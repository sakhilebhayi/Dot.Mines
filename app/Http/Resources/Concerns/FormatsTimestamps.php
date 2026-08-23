<?php

namespace App\Http\Resources\Concerns;

use App\Support\ApiPayload;

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
        return ApiPayload::iso($value);
    }
}
