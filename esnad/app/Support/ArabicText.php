<?php

namespace App\Support;

/**
 * Arabic normaliser for search (section 6.2): folds أ إ آ ٱ to ا, ة to ه,
 * ى to ي, removes tashkeel and tatweel, and maps Eastern digits to Western.
 */
final class ArabicText
{
    public static function normalise(string $text): string
    {
        $text = Digits::toWestern($text);
        $text = preg_replace('/[\x{064B}-\x{065F}\x{0670}\x{06D6}-\x{06ED}]/u', '', $text); // tashkeel
        $text = str_replace('ـ', '', $text); // tatweel
        $text = str_replace(['أ', 'إ', 'آ', 'ٱ'], 'ا', $text);
        $text = str_replace('ة', 'ه', $text);
        $text = str_replace('ى', 'ي', $text);
        $text = preg_replace('/\s+/u', ' ', $text);

        return mb_strtolower(trim($text));
    }
}
