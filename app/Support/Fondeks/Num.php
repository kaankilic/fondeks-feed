<?php

namespace App\Support\Fondeks;

/**
 * Numeric coercion for JSON output that mirrors JavaScript's `Number`/`JSON`
 * behaviour: Postgres returns numerics as strings, and the client casts them
 * with `Number(...)`, which serialises an integral value with no trailing
 * ".0". These helpers keep the API's numbers shaped the same way.
 */
final class Num
{
    /** `Number(x)` then JSON: null stays null, whole values become ints. */
    public static function json(string|int|float|null $value): int|float|null
    {
        if ($value === null) {
            return null;
        }

        $number = (float) $value;

        if (is_finite($number) && floor($number) === $number && abs($number) < PHP_INT_MAX) {
            return (int) $number;
        }

        return $number;
    }

    /**
     * Percentage change between two prices rounded to two decimals, or 0 when
     * there is no history — the client's `changePct`.
     */
    public static function changePct(float $current, string|int|float|null $past): int|float
    {
        if ($past === null || $past === '') {
            return 0;
        }

        $previous = (float) $past;
        if ($previous == 0.0) {
            return 0;
        }

        return self::json(round((($current / $previous) - 1) * 100, 2));
    }
}
