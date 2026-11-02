<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Sri Lankan National Identity Card number.
 *
 * - Old format: 9 digits + V or X (e.g. 853400937V): YY DDD SSS C.
 * - New format (2016+): 12 digits (e.g. 198534000937): YYYY DDD SSSS C.
 *
 * DDD is the day of the year of birth (001–366), plus 500 for women (501–866).
 */
class SriLankanNic implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $nic = strtoupper(preg_replace('/\s+/', '', (string) $value));

        if (preg_match('/^(\d{2})(\d{3})\d{4}[VX]$/', $nic, $m)) {
            $day = (int) $m[2];
        } elseif (preg_match('/^(19|20)\d{2}(\d{3})\d{5}$/', $nic, $m)) {
            $day = (int) $m[2];
        } else {
            $fail('Enter a valid NIC number: 9 digits and V or X (old), or 12 digits (new).');

            return;
        }

        $day = $day > 500 ? $day - 500 : $day;
        if ($day < 1 || $day > 366) {
            $fail('This NIC number is not valid: check the birthday digits.');
        }
    }

    /** Store NICs in one shape: no spaces, upper-case V/X. */
    public static function normalise(?string $nic): ?string
    {
        return $nic === null || trim($nic) === '' ? null : strtoupper(preg_replace('/\s+/', '', $nic));
    }
}
