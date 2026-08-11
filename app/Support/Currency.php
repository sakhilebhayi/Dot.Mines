<?php

namespace App\Support;

/**
 * Symbol formatting for the currency codes teams can select in Settings
 * (see App\Livewire\Settings::getCurrencies()). This only formats an
 * already-known amount in an already-known currency -- it does not convert
 * between currencies, since the app has no real exchange-rate source.
 */
class Currency
{
    /**
     * @var array<string, string>
     */
    public const SYMBOLS = [
        'USD' => '$',
        'EUR' => '€',
        'GBP' => '£',
        'ZAR' => 'R',
        'AUD' => 'A$',
        'CAD' => 'C$',
        'JPY' => '¥',
        'CNY' => '¥',
        'INR' => '₹',
        'BRL' => 'R$',
    ];

    public static function symbol(?string $code): string
    {
        $code = strtoupper((string) $code);

        return self::SYMBOLS[$code] ?? ($code !== '' ? $code.' ' : 'R');
    }

    public static function format(float|string|null $amount, ?string $code, int $decimals = 2): string
    {
        return self::symbol($code).number_format((float) $amount, $decimals);
    }
}
