<?php

namespace App\Support;

final class Digits
{
    private const EASTERN = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];

    // Persian digits, which some Android keyboards produce.
    private const PERSIAN = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];

    /** Converts Eastern Arabic and Persian digits to Western digits. */
    public static function toWestern(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $western = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

        return str_replace(self::PERSIAN, $western, str_replace(self::EASTERN, $western, $value));
    }
}
