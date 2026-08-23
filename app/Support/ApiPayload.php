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
    /**
     * Coerce an untyped value into a clean list of strings, dropping
     * anything that is not a string. Also launders config values whose
     * literal types psalm's Laravel plugin would otherwise fold into
     * always-true/always-false guards.
     *
     * @return list<string>
     */
    public static function strings(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $out = [];

        /** @psalm-suppress MixedAssignment */
        foreach ($value as $item) {
            if (is_string($item)) {
                $out[] = $item;
            }
        }

        return $out;
    }

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
