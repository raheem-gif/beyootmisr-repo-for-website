<?php

use App\Support\ArabicText;
use App\Support\Digits;
use App\Support\Money;
use App\Support\NationalId;
use App\Support\Phone;

it('normalises Egyptian mobile numbers to E.164', function (string $input, ?string $expected) {
    expect(Phone::normalise($input))->toBe($expected);
})->with([
    ['01001234567', '+201001234567'],
    ['٠١٠٠١٢٣٤٥٦٧', '+201001234567'],
    ['۰۱۱۲۳۴۵۶۷۸۹', '+201123456789'],
    ['+20 12 3456 7890', '+201234567890'],
    ['00201512345678', '+201512345678'],
    ['201001234567', '+201001234567'],
    ['1001234567', '+201001234567'],
    ['0100-123-4567', '+201001234567'],
    ['01301234567', null],    // no such prefix
    ['0100123456', null],     // too short
    ['0223456789', null],     // landline
    ['', null],
]);

it('masks and displays phone numbers', function () {
    expect(Phone::display('+201001234567'))->toBe('0100 123 4567')
        ->and(Phone::mask('+201001234567'))->toBe('+20 10 •••• 4567');
});

it('converts Eastern Arabic digits', function () {
    expect(Digits::toWestern('٠١٢٣٤٥٦٧٨٩'))->toBe('0123456789');
});

it('checks national ID structure only', function (string $id, bool $valid) {
    expect(NationalId::isValidStructure($id))->toBe($valid);
})->with([
    ['29001010101234', true],    // 1990-01-01, Cairo
    ['٢٩٠٠١٠١٠١٠١٢٣٤', true],  // Eastern digits
    ['30502152101239', true],    // 2005-02-15, Giza
    ['19001010101234', false],   // century digit
    ['29013010101234', false],   // month 13
    ['29002300101234', false],   // 30 February
    ['29001019901234', false],   // governorate 99
    ['2900101010123', false],    // 13 digits
    ['39901010101234', false],   // in the future
]);

it('formats money with Western digits in both languages', function () {
    expect(Money::format(35000, 'ar'))->toBe('350 جنيه')
        ->and(Money::format(35000, 'en'))->toBe('EGP 350')
        ->and(Money::format(123450, 'ar'))->toBe('1,234.50 جنيه')
        ->and(Money::format(100000000, 'en'))->toBe('EGP 1,000,000');
});

it('normalises Arabic text for search', function () {
    expect(ArabicText::normalise('إعلامُ وراثةٍ'))->toBe('اعلام وراثه')
        ->and(ArabicText::normalise('أحــوال  شخصيّة'))->toBe('احوال شخصيه')
        ->and(ArabicText::normalise('مستشفى ٣'))->toBe('مستشفي 3');
});
