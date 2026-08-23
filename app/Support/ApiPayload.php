<?php

namespace App\Support;

/**
 * Typed coercion for untyped third-party API payload nodes -- the one
 * place mixed JSON becomes provably-shaped arrays for both analyzers.
 */
final class ApiPayload
{
    /**
     * @return array<string, mixed>
     */
    public static function assoc(mixed $value): array
    {
        /** @var array<string, mixed> */
        return is_array($value) ? $value : [];
    }

    /**
     * A string payload value, or the default when absent/mistyped.
     */
    public static function str(mixed $value, string $default = ''): string
    {
        return is_string($value) ? $value : $default;
    }

    /**
     * A clean list of assoc rows; malformed entries are dropped.
     *
     * @return list<array<string, mixed>>
     */
    public static function rows(mixed $value): array
    {
        /** @var list<array<string, mixed>> */
        return is_array($value) ? array_values(array_filter($value, 'is_array')) : [];
    }
}
