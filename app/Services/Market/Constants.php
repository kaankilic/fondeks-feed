<?php

namespace App\Services\Market;

class Constants
{
    /** The asset-class buckets a securities mutual fund falls into. */
    public const FUND_CATEGORIES = [
        'Hisse Senedi',
        'Kıymetli Maden',
        'Serbest',
        'Değişken',
        'Para Piyasası',
        'Borçlanma',
    ];

    /** Buckets only a pension fund carries. */
    public const PENSION_CATEGORIES = [
        'Standart',
        'Başlangıç',
        'Katkı',
        'Fon Sepeti',
        'Karma',
        'Katılım',
        'Endeks',
    ];

    /** The universes TEFAS files funds under, by its own fonTipi. */
    public const FUND_TYPES = ['YAT', 'EMK', 'BYF'];

    public static function allCategories(): array
    {
        return array_merge(self::FUND_CATEGORIES, self::PENSION_CATEGORIES);
    }
}
