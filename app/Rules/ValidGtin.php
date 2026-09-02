<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class ValidGtin implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! preg_match('/\A(?:\d{8}|\d{12}|\d{13}|\d{14})\z/', $value)) {
            $fail('The :attribute must be a valid GTIN-8, UPC, EAN-13, or GTIN-14.');

            return;
        }

        if (! self::passesChecksum($value)) {
            $fail('The :attribute has an invalid GTIN check digit.');
        }
    }

    public static function isValid(?string $value): bool
    {
        return $value !== null
            && preg_match('/\A(?:\d{8}|\d{12}|\d{13}|\d{14})\z/', $value) === 1
            && self::passesChecksum($value);
    }

    private static function passesChecksum(string $value): bool
    {
        $digits = array_map('intval', str_split($value));
        $checkDigit = array_pop($digits);
        $sum = 0;

        foreach (array_reverse($digits) as $position => $digit) {
            $sum += $digit * ($position % 2 === 0 ? 3 : 1);
        }

        return (10 - ($sum % 10)) % 10 === $checkDigit;
    }
}
