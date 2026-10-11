<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Fund income withholding (stopaj)
    |--------------------------------------------------------------------------
    |
    | Rates used to derive each fund's net-of-withholding return from its gross
    | return: net% = gross% * (1 - rate). Rates are fractions (0.10 = 10%).
    |
    | IMPORTANT — this is a policy assumption, not per-fund reported data. TEFAS
    | does not publish a usable per-fund stopaj figure (the `withholding_tax`
    | column is empty), and the real rate depends on fund type, the investor and
    | the holding period, and changes by regulation. These defaults encode a
    | single blanket assumption per category; review them against the current
    | tax regime and override via config before treating `netReturn` as
    | authoritative. An unlisted category falls back to `default`.
    |
    */

    'fund_withholding' => [
        'default' => (float) env('STOPAJ_DEFAULT_RATE', 0.10),

        // Per-category overrides keyed by App\Support\Fondeks\Constants::FUND_CATEGORIES.
        'categories' => [
            'Hisse Senedi' => (float) env('STOPAJ_RATE_HISSE', 0.0),
            'Kıymetli Maden' => (float) env('STOPAJ_RATE_KIYMETLI_MADEN', 0.10),
            'Serbest' => (float) env('STOPAJ_RATE_SERBEST', 0.10),
            'Değişken' => (float) env('STOPAJ_RATE_DEGISKEN', 0.10),
            'Para Piyasası' => (float) env('STOPAJ_RATE_PARA_PIYASASI', 0.10),
            'Borçlanma' => (float) env('STOPAJ_RATE_BORCLANMA', 0.10),
        ],
    ],
];
