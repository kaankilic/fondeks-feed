<?php

namespace App\Support\Fondeks;

/**
 * Fund URL slugs, ported from the client's `src/lib/fondeks/slug.ts`.
 *
 * Fund URLs read as `AFT-ak-portfoy-teknoloji-yabanci-hisse-fonu`: the TEFAS
 * code stays upper-case and addressable, the name is there for humans.
 */
final class Slug
{
    private const TURKISH_MAP = [
        'ı' => 'i', 'İ' => 'i',
        'ş' => 's', 'Ş' => 's',
        'ğ' => 'g', 'Ğ' => 'g',
        'ü' => 'u', 'Ü' => 'u',
        'ö' => 'o', 'Ö' => 'o',
        'ç' => 'c', 'Ç' => 'c',
    ];

    public static function slugify(string $text): string
    {
        $text = strtr($text, self::TURKISH_MAP);
        $text = mb_strtolower($text, 'UTF-8');

        // Strip remaining combining marks (NFD → drop diacritics).
        $normalized = \Normalizer::isNormalized($text, \Normalizer::FORM_D)
            ? $text
            : \Normalizer::normalize($text, \Normalizer::FORM_D);
        if ($normalized !== false) {
            $text = preg_replace('/\p{Mn}+/u', '', $normalized);
        }

        $text = preg_replace('/[^a-z0-9]+/', '-', $text);

        return trim($text, '-');
    }

    public static function fundSlug(string $code, string $name): string
    {
        return strtoupper($code).'-'.self::slugify($name);
    }

    /** The code is everything before the first dash, so old `/fon/AFT` links work. */
    public static function codeFromSlug(string $slug): string
    {
        $decoded = rawurldecode($slug);

        return strtoupper(explode('-', $decoded)[0]);
    }
}
