<?php

namespace App\Support;

/**
 * Egyptian mobile numbers. Accepts 01XXXXXXXXX, 1XXXXXXXXX, 201XXXXXXXXX,
 * +201XXXXXXXXX and 00201XXXXXXXXX, with Eastern or Western digits, spaces
 * and dashes. Stores E.164 (+201XXXXXXXXX).
 */
final class Phone
{
    // 010 Vodafone, 011 e&, 012 Orange, 015 WE.
    private const PREFIXES = ['10', '11', '12', '15'];

    public static function normalise(?string $input): ?string
    {
        if ($input === null) {
            return null;
        }
        $digits = preg_replace('/\D+/', '', Digits::toWestern($input));

        if (str_starts_with($digits, '0020')) {
            $digits = substr($digits, 4);
        } elseif (str_starts_with($digits, '20') && strlen($digits) === 12) {
            $digits = substr($digits, 2);
        }
        if (str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }

        if (strlen($digits) !== 10 || ! in_array(substr($digits, 0, 2), self::PREFIXES, true)) {
            return null;
        }

        return '+20'.$digits;
    }

    public static function isValid(?string $input): bool
    {
        return self::normalise($input) !== null;
    }

    /** "+201001234567" to "0100 123 4567" for display. */
    public static function display(string $e164): string
    {
        $local = '0'.substr($e164, 3);

        return substr($local, 0, 4).' '.substr($local, 4, 3).' '.substr($local, 7);
    }

    /** "+201001234567" to "+20 10 •••• 4567" for screens that must not show the full number. */
    public static function mask(string $e164): string
    {
        return '+20 '.substr($e164, 3, 2).' •••• '.substr($e164, -4);
    }
}
