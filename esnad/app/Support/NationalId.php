<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * Structure check for the 14-digit Egyptian national ID. The check-digit
 * formula is not public, so the last digit is not validated.
 *
 * Layout: C YYMMDD GG SSS G K
 *   C   century (2 = 1900s, 3 = 2000s)
 *   GG  governorate code
 *   SSS sequence, G gender (odd male, even female), K check digit
 */
final class NationalId
{
    public const GOVERNORATE_CODES = [
        '01', '02', '03', '04', '11', '12', '13', '14', '15', '16', '17', '18', '19',
        '21', '22', '23', '24', '25', '26', '27', '28', '29', '31', '32', '33', '34', '35', '88',
    ];

    public static function normalise(?string $input): ?string
    {
        if ($input === null) {
            return null;
        }

        return preg_replace('/\D+/', '', Digits::toWestern($input));
    }

    public static function isValidStructure(?string $input): bool
    {
        $id = self::normalise($input);
        if ($id === null || strlen($id) !== 14) {
            return false;
        }
        if (! in_array($id[0], ['2', '3'], true)) {
            return false;
        }
        if (! in_array(substr($id, 7, 2), self::GOVERNORATE_CODES, true)) {
            return false;
        }

        return self::birthDate($id) !== null;
    }

    public static function birthDate(string $id): ?CarbonImmutable
    {
        $century = $id[0] === '2' ? 1900 : 2000;
        $year = $century + (int) substr($id, 1, 2);
        $month = (int) substr($id, 3, 2);
        $day = (int) substr($id, 5, 2);
        if (! checkdate($month, $day, $year)) {
            return null;
        }
        $date = CarbonImmutable::create($year, $month, $day);

        return $date->isFuture() ? null : $date;
    }
}
