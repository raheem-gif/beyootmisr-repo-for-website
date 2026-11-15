<?php

namespace App\Support;

/**
 * Money is stored as integer piastres (EGP × 100). Display uses Western
 * digits: "350 جنيه" in Arabic and "EGP 350" in English.
 */
final class Money
{
    public static function format(int $piastres, ?string $locale = null): string
    {
        $locale ??= app()->getLocale();
        $pounds = $piastres / 100;
        $decimals = $piastres % 100 === 0 ? 0 : 2;
        $formatter = new \NumberFormatter($locale === 'ar' ? 'ar-EG-u-nu-latn' : 'en-GB', \NumberFormatter::DECIMAL);
        $formatter->setAttribute(\NumberFormatter::MIN_FRACTION_DIGITS, $decimals);
        $formatter->setAttribute(\NumberFormatter::MAX_FRACTION_DIGITS, $decimals);
        // Arabic locale data uses the Arabic thousands separator; keep a plain comma.
        $number = str_replace(['٬', '٫'], [',', '.'], $formatter->format($pounds));

        return $locale === 'ar' ? $number.' جنيه' : 'EGP '.$number;
    }
}
