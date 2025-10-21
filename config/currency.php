<?php

return [
    'base' => env('APP_BASE_CURRENCY', 'IDR'),

    'default' => env('APP_DEFAULT_CURRENCY', env('APP_BASE_CURRENCY', 'IDR')),

    'supported' => [
        'IDR' => 'Indonesian Rupiah',
        'USD' => 'United States Dollar',
    ],

    'precision' => [
        'IDR' => 0,
        'USD' => 2,
    ],

    'update_source' => env('CURRENCY_RATE_SOURCE', 'https://api.exchangerate.host/latest'),
];
