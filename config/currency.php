<?php

return [
    'default' => env('CURRENCY_DEFAULT', 'IDR'),
    'supported' => array_values(array_filter(array_map(
        static fn (string $currency) => strtoupper(trim($currency)),
        explode(',', env('CURRENCY_SUPPORTED', 'IDR,USD'))
    ))),

    'api' => [
        'endpoint' => env('CURRENCY_API_ENDPOINT', 'https://api.exchangerate.host/latest'),
        'base' => env('CURRENCY_API_BASE', 'IDR'),
        'symbols' => env('CURRENCY_API_SYMBOLS', 'USD'),
        'timeout' => (int) env('CURRENCY_API_TIMEOUT', 10),
    ],

    'fallback_rates' => [
        'USD' => (float) env('CURRENCY_FALLBACK_USD_RATE', 0.000064),
    ],

    'cache_key' => 'currency_rates',
    'cache_ttl' => 60 * 60, // 1 hour
];
