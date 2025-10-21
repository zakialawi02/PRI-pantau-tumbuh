<?php

return [
    'default' => env('CURRENCY_DEFAULT', 'IDR'),

    'supported' => ['IDR', 'USD'],

    'exchange_api' => [
        'base_url' => env('CURRENCY_EXCHANGE_BASE_URL', 'https://cdn.jsdelivr.net/npm/@fawazahmed0/currency-api@latest/v1/currencies'),
        'timeout' => env('CURRENCY_EXCHANGE_TIMEOUT', 10),
        'weekly_refresh_days' => (int) env('CURRENCY_EXCHANGE_REFRESH_DAYS', 7),
    ],
];
