<?php

namespace App\Support;

class StockSnapshot
{
    public static function matches(array $saved, array $current): bool
    {
        return self::ordered($saved) === self::ordered($current);
    }

    private static function ordered(array $value): array
    {
        foreach ($value as &$item) {
            if (is_array($item)) $item = self::ordered($item);
        }
        unset($item);
        ksort($value, SORT_STRING);

        return $value;
    }
}
