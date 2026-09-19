<?php

namespace App\Services\Market;

/**
 * Tolerant parsing for upstream payloads. The feed mixes Turkish-formatted
 * numbers, several date shapes and both SCREAMING and camelCase field names,
 * so values are read by candidate key rather than by a single fixed name.
 */
class Parse
{
    /** First present, non-empty value among the candidate keys. */
    public static function pick(array $row, array $keys): mixed
    {
        foreach ($keys as $key) {
            $value = $row[$key] ?? null;
            if ($value !== null && $value !== '') {
                return $value;
            }
        }
        return null;
    }

    /** Accepts 1234.56, "1234.56", "1.234,56" and "%1,91". */
    public static function toNumber(mixed $value): ?float
    {
        if (is_int($value) || is_float($value)) {
            return is_finite((float) $value) ? (float) $value : null;
        }
        if (!is_string($value)) {
            return null;
        }

        $cleaned = trim(preg_replace('/[%\s]/u', '', $value));
        if ($cleaned === '') {
            return null;
        }

        // A trailing comma group is a decimal, however many digits follow it.
        $turkish = (bool) preg_match('/,\d+$/', $cleaned);
        $normalised = $turkish
            ? str_replace(',', '.', str_replace('.', '', $cleaned))
            : str_replace(',', '', $cleaned);

        if (!is_numeric($normalised)) {
            return null;
        }

        $parsed = (float) $normalised;
        return is_finite($parsed) ? $parsed : null;
    }

    public static function toInteger(mixed $value): ?int
    {
        $parsed = self::toNumber($value);
        return $parsed === null ? null : (int) round($parsed);
    }

    /** Accepts epoch millis, "dd.mm.yyyy", "yyyy-mm-dd" and ISO timestamps. */
    public static function toIsoDate(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_int($value) || is_float($value)) {
            $seconds = (int) ($value / 1000);
            return date('Y-m-d', $seconds);
        }

        if (!is_string($value)) {
            return null;
        }

        $text = trim($value);
        if ($text === '') {
            return null;
        }

        if (preg_match('#^(\d{2})[./](\d{2})[./](\d{4})$#', $text, $m)) {
            return "{$m[3]}-{$m[2]}-{$m[1]}";
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $text)) {
            return $text;
        }

        if (preg_match('/^\d+$/', $text)) {
            return self::toIsoDate((int) $text);
        }

        $ts = strtotime($text);
        return $ts === false ? null : date('Y-m-d', $ts);
    }

    public static function toBoolean(mixed $value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value) || is_float($value)) {
            return $value != 0;
        }
        if (!is_string($value)) {
            return null;
        }

        $text = mb_strtolower(trim($value), 'UTF-8');
        if (in_array($text, ['e', 'evet', 'true', '1', 'y', 'yes'], true)) {
            return true;
        }
        if (in_array($text, ['h', 'hayır', 'hayir', 'false', '0', 'n', 'no'], true)) {
            return false;
        }
        return null;
    }

    private const CATEGORY_KEYWORDS = [
        ['/hisse/iu', 'Hisse Senedi'],
        ['/kıymetli|kiymetli|altın|altin|maden/iu', 'Kıymetli Maden'],
        ['/serbest/iu', 'Serbest'],
        ['/değişken|degisken/iu', 'Değişken'],
        ['/para piyasası|para piyasasi|likit/iu', 'Para Piyasası'],
        ['/borçlanma|borclanma|tahvil|bono/iu', 'Borçlanma'],
        ['/başlangıç|baslangic/iu', 'Başlangıç'],
        ['/standart/iu', 'Standart'],
        ['/katkı|katki/iu', 'Katkı'],
        ['/fon sepeti/iu', 'Fon Sepeti'],
        ['/karma/iu', 'Karma'],
        ['/endeks/iu', 'Endeks'],
        ['/kira sertifika|katılım|katilim/iu', 'Katılım'],
    ];

    /** Maps the source's fund-type text onto the categories the product shows. */
    public static function toCategory(mixed $value): string
    {
        $text = is_string($value) ? $value : '';

        foreach (self::CATEGORY_KEYWORDS as [$pattern, $category]) {
            if (preg_match($pattern, $text)) {
                return $category;
            }
        }

        foreach (Constants::FUND_CATEGORIES as $category) {
            if (mb_strtolower($category, 'UTF-8') === mb_strtolower($text, 'UTF-8')) {
                return $category;
            }
        }

        return 'Değişken';
    }
}
