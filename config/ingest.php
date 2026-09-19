<?php

return [
    'market_provider' => env('MARKET_DATA_PROVIDER', 'fixture'),
    'holdings_provider' => env('HOLDINGS_PROVIDER', 'fixture'),
    'log_samples' => env('INGEST_LOG_SAMPLES', false),

    'tefas' => [
        'base_url' => env('TEFAS_BASE_URL', 'https://www.tefas.gov.tr/api/funds'),
        'timeout_ms' => (int) env('TEFAS_TIMEOUT_MS', 90000),
        'max_retries' => (int) env('TEFAS_MAX_RETRIES', 3),
        'concurrency' => (int) env('TEFAS_CONCURRENCY', 3),
        'rate_limit' => (int) env('TEFAS_RATE_LIMIT', 8),
        'rate_window_ms' => (int) env('TEFAS_RATE_WINDOW_MS', 60000),
        'fund_types' => env('TEFAS_FUND_TYPES', 'YAT,EMK,BYF'),
        'user_agent' => env('TEFAS_USER_AGENT', 'FondeksBot/1.0 (+https://fondeks.com; market data sync)'),
    ],

    'tcmb' => [
        'base_url' => env('TCMB_BASE_URL', 'https://www.tcmb.gov.tr/kurlar'),
        'evds_key' => env('TCMB_EVDS_API_KEY', ''),
        'evds_base_url' => env('EVDS_BASE_URL', 'https://evds3.tcmb.gov.tr/igmevdsms-dis'),
        'evds_series' => env('EVDS_SERIES', ''),
    ],

    'kap' => [
        'base_url' => env('KAP_BASE_URL', 'https://www.kap.org.tr'),
        'timeout_ms' => (int) env('KAP_TIMEOUT_MS', 25000),
        'concurrency' => (int) env('KAP_CONCURRENCY', 3),
        // Conservative pacing — KAP will IP-block a source that requests too
        // fast. Default ~30 requests/min; runs on a dedicated single worker.
        'rate_limit' => (int) env('KAP_RATE_LIMIT', 30),
        'rate_window_ms' => (int) env('KAP_RATE_WINDOW_MS', 60000),
        'submit_limit' => (int) env('KAP_SUBMIT_LIMIT', 200),
        'document_limit' => (int) env('KAP_DOCUMENT_LIMIT', 500),
        'disclosure_limit' => (int) env('KAP_DISCLOSURE_LIMIT', 500),
        'disclosure_days' => (int) env('KAP_DISCLOSURE_DAYS', 7),
        'extract_batch_size' => (int) env('KAP_EXTRACT_BATCH_SIZE', 200),
        'inception_limit' => (int) env('KAP_INCEPTION_LIMIT', 200),
        'directory_timeout_ms' => (int) env('KAP_DIRECTORY_TIMEOUT_MS', 90000),
        'user_agent' => env('KAP_USER_AGENT', 'FondeksBot/1.0 (+https://fondeks.com; portfolio disclosures)'),
    ],

    'anthropic' => [
        'api_key' => env('ANTHROPIC_API_KEY', ''),
        'auth_token' => env('ANTHROPIC_AUTH_TOKEN', ''),
        'max_retries' => (int) env('ANTHROPIC_MAX_RETRIES', 3),
    ],
];
