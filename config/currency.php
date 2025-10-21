<?php

return [
    'default' => env('CURRENCY_DEFAULT', 'IDR'),

    'base' => env('CURRENCY_BASE', 'IDR'),

    'supported' => [
        'IDR',
        'USD',
    ],

    'labels' => [
        'IDR' => 'Indonesian Rupiah',
        'USD' => 'United States Dollar',
    ],

    'provider' => [
        'url' => env('CURRENCY_PROVIDER_URL', 'https://api.exchangerate.host/latest'),
        'timeout' => env('CURRENCY_PROVIDER_TIMEOUT', 10),
    ],

    'fallback_rates' => [
        'IDR' => [
            'USD' => (float) env('CURRENCY_FALLBACK_IDR_USD', 0.000064),
        ],
        'USD' => [
            'IDR' => (float) env('CURRENCY_FALLBACK_USD_IDR', 15625),
        ],
    ],
];
