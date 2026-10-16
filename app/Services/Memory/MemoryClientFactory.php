<?php

namespace App\Services\Memory;

use App\Support\ApiPayload;

/**
 * The token leaves configuration only here, straight into the client.
 * Null when the loop is not configured: callers treat that as inert.
 */
final class MemoryClientFactory
{
    public static function configured(): bool
    {
        return trim(ApiPayload::str(config('services.memory.url'))) !== '' && trim(ApiPayload::str(config('services.memory.token'))) !== '';
    }

    public function fromConfig(): ?MemoryClient
    {
        if (! self::configured()) {
            return null;
        }

        return new MemoryClient(trim(ApiPayload::str(config('services.memory.url'))), trim(ApiPayload::str(config('services.memory.token'))), max(1, ApiPayload::int(config('services.memory.timeout'), 10)));
    }
}
