<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Money is handled in integer cents (CLAUDE.md: never floats). DECIMAL strings from the
 * database are converted with bcmath.
 */
final class Money
{
    /** Currencies the public site can show (Settings → display_currency). */
    public const DISPLAY_CURRENCIES = ['LKR', 'USD', 'EUR'];

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
            'LKR' => 'LKR ',
            default => $currency.' ',
        };

        return $symbol.number_format($cents / 100, $currency === 'LKR' ? 0 : 2);
    }

    /**
     * Currency travellers see (Admin → Settings → Currency). Prices are stored in USD.
     */
    public static function displayCurrency(): string
    {
        $currency = strtoupper((string) Setting::get('display_currency', 'LKR'));

        return in_array($currency, self::DISPLAY_CURRENCIES, true) ? $currency : 'LKR';
    }

    /**
     * A stored USD amount converted to the display currency, e.g. "LKR 45,300".
     */
    public static function show(string|int|float|null $usd, ?string $currency = null): string
    {
        $currency ??= self::displayCurrency();

        return self::format(self::convert(self::cents($usd), $currency), $currency);
    }

    /**
     * A stored USD amount as entered by admins, e.g. "USD 151.00".
     */
    public static function usd(string|int|float|null $usd): string
    {
        return 'USD '.number_format(self::cents($usd) / 100, 2);
    }

    /**
     * "USD 151.00 (LKR 45,300)" for back-office screens, where amounts are entered in USD.
     */
    public static function both(string|int|float|null $usd): string
    {
        $currency = self::displayCurrency();

        return $currency === 'USD' ? self::usd($usd) : self::usd($usd).' ('.self::show($usd, $currency).')';
    }

    /**
     * "≈ USD 151" next to a rupee total, for visitors who think in dollars. Empty when the
     * display currency is already USD.
     */
    public static function approxUsd(string|int|float|null $usd): string
    {
        return self::displayCurrency() === 'USD' ? '' : '≈ USD '.number_format(self::cents($usd) / 100);
    }

    /**
     * USD cents → cents of $currency, using the exchange rates in Settings.
     */
    public static function convert(int $usdCents, string $currency): int
    {
        $rate = match ($currency) {
            'LKR' => (string) Setting::get('usd_lkr', '300'),
            'EUR' => (string) Setting::get('usd_eur', '0.92'),
            default => '1',
        };

        // Round half up to the target currency's smallest unit.
        return (int) bcadd(bcmul((string) $usdCents, $rate !== '' ? $rate : '1', 2), $usdCents >= 0 ? '0.5' : '-0.5', 0);
    }
}
