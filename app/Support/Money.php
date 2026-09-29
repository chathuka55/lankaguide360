<?php

namespace App\Support;

/**
 * Money is handled in integer cents (CLAUDE.md: never floats). DECIMAL strings from the
 * database are converted with bcmath.
 */
final class Money
{
    public static function cents(string|int|float|null $amount): int
    {
        if ($amount === null || $amount === '') {
            return 0;
        }

        return (int) bcmul(is_float($amount) ? number_format($amount, 2, '.', '') : (string) $amount, '100', 0);
    }

    public static function decimal(int $cents): string
    {
        return bcdiv((string) $cents, '100', 2);
    }

    /**
     * Percentage of an amount, rounded half up to the cent.
     */
    public static function percent(int $cents, string|int|float $percent): int
    {
        return (int) round($cents * (float) $percent / 100);
    }

    public static function format(int $cents, string $currency = 'USD'): string
    {
        $symbol = match ($currency) {
            'USD' => '$',
            'EUR' => '€',
            'LKR' => 'Rs ',
            default => $currency.' ',
        };

        return $symbol.number_format($cents / 100, $currency === 'LKR' ? 0 : 2);
    }
}
