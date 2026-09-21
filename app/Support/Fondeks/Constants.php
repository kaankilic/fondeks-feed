<?php

namespace App\Support\Fondeks;

/**
 * Client-safe constants, ported from the Next.js client's
 * `src/lib/fondeks/constants.ts` so the API speaks the same vocabulary.
 */
final class Constants
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

    /** The universes TEFAS files funds under, by its own `fonTipi`. */
    public const FUND_TYPES = ['YAT', 'EMK', 'BYF'];

    /** The universe the fund lists cover — Keşfet, the screener, İzleme Listem. */
    public const PRODUCT_FUND_TYPE = 'YAT';

    /** The universe behind /emeklilik-fonlari. */
    public const PENSION_FUND_TYPE = 'EMK';

    /** The universe behind /borsa-yatirim-fonlari. */
    public const ETF_FUND_TYPE = 'BYF';

    /** The universes with pages of their own. */
    public const PAGED_FUND_TYPES = ['YAT', 'EMK', 'BYF'];

    /** Shown in place of a künye figure no source publishes. */
    public const UNKNOWN = 'Bilinmiyor';

    /** TEFAS risk scale. */
    public const RISK_MIN = 1;

    public const RISK_MAX = 7;
}
