<?php

namespace App\Support\Fondeks;

/**
 * Turkish number formatting, ported from the client's `src/lib/fondeks/format.ts`:
 * comma decimal separator, dot thousands grouping, explicit sign on percentages.
 * Only the formatters the API's `compare` table needs are ported here.
 */
final class Format
{
    /** Fixed decimals with Turkish grouping: "11.492,41", not "11492,41". */
    private static function decimal(float $value, int $digits): string
    {
        return number_format($value, $digits, ',', '.');
    }

    /** "+62,00%" — Intl writes its own minus, so only the plus is added. */
    public static function percent(float $value, int $digits = 2): string
    {
        $sign = $value > 0 ? '+' : '';

        return $sign.self::decimal($value, $digits).'%';
    }

    /** Percentage written the Turkish way, with the sign in front: "%62,0". */
    public static function percentPrefixed(float $value, int $digits = 1): string
    {
        return '%'.self::decimal($value, $digits);
    }

    /** A plain Turkish-grouped integer: "12.345". */
    public static function count(int $value): string
    {
        return number_format($value, 0, ',', '.');
    }
}
