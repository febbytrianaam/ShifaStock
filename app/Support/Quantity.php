<?php

namespace App\Support;

final class Quantity
{
    public static function input(int|float|string|null $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $formatted = rtrim(rtrim(number_format((float) $value, 4, '.', ''), '0'), '.');

        return $formatted === '-0' ? '0' : $formatted;
    }

    public static function format(int|float|string|null $value): string
    {
        $number = self::input($value);
        $decimals = str_contains($number, '.') ? strlen(explode('.', $number)[1]) : 0;

        return number_format((float) $value, $decimals, ',', '.');
    }
}
