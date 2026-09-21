<?php

namespace App\Support\Fondeks;

/**
 * Branding fallbacks and allocation colours, ported from the client's
 * `src/lib/fondeks/palette.ts`.
 */
final class Palette
{
    /** Shown when a founder has no published logo. */
    public const FALLBACK_INITIALS = '··';

    public const FALLBACK_BACKGROUND = '#3F3F46';

    /** Asset-allocation slices are coloured by position, largest first. */
    private const ALLOCATION_PALETTE = [
        'var(--brand)',
        'var(--action)',
        'var(--pos)',
        '#52525b',
        '#3f3f46',
    ];

    public static function allocationColor(int $index): string
    {
        return self::ALLOCATION_PALETTE[$index % count(self::ALLOCATION_PALETTE)];
    }
}
